<?php
// Operator: this boat's trip history, grouped Year > Month > Week > Day.
require_once '../config/layout.php';
need('operator');
$m = my_mb();
if (!$m) {
    header('Location: ../logout.php');
    exit;
}
$q = db()->prepare("SELECT t.id, t.trip_date, t.start_time, t.departure_time, t.status, COUNT(p.id) pax FROM trips t
    LEFT JOIN passengers p ON p.trip_id=t.id
    WHERE t.motorbanca_id=? AND t.status NOT IN ('Inspection','Cancelled')
    GROUP BY t.id ORDER BY t.trip_date DESC, t.id DESC");
$q->execute([$m['id']]);
$rows = $q->fetchAll();
$tree = [];
$tot = ['trips' => 0, 'pax' => 0];
foreach ($rows as $r) {
    $ts = strtotime($r['trip_date']);
    $y = date('Y', $ts);
    $mo = date('Y-m', $ts);
    $wk = date('Y-m-d', strtotime('monday this week', $ts));
    $d = $r['trip_date'];
    $n = (int)$r['pax'];
    foreach ([[$y], [$y, $mo], [$y, $mo, $wk], [$y, $mo, $wk, $d]] as $path) {   // add this trip to every level it belongs to
        $node = &$tree;
        foreach ($path as $i => $k) {
            if (!isset($node[$k])) $node[$k] = ['trips' => 0, 'pax' => 0, 'kids' => []];
            if ($i === count($path) - 1) {
                $node[$k]['trips']++;
                $node[$k]['pax'] += $n;
            }
            $node = &$node[$k]['kids'];
        }
        unset($node);
    }
    $tree[$y]['kids'][$mo]['kids'][$wk]['kids'][$d]['rows'][] = $r;
}
function th_sum($n)
{
    return "<span class='th-sum'>" . $n['trips'] . ' trip' . ($n['trips'] == 1 ? '' : 's') . ' &middot; ' . $n['pax'] . ' passenger' . ($n['pax'] == 1 ? '' : 's') . "</span>";
}
$chev = '<svg class="th-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>';
$tm = fn($v) => $v ? date('g:i A', strtotime($v)) : '&mdash;';
page_head('Trip History', 'operator');
?>
<h1>Trip history</h1>
<?php if (!$tree): ?>
    <div class="card">
        <p class="muted" style="margin:0">No trips yet.</p>
    </div>
<?php else: ?>
    <div class="thx">
        <?php $fy = true;
        foreach ($tree as $y => $yn): ?>
            <details class="th th-y" <?= $fy ? 'open' : '' ?>>
                <summary><?= $chev ?><b><?= e($y) ?></b><?= th_sum($yn) ?></summary>
                <?php $fm = true;
                foreach ($yn['kids'] as $mo => $mn): ?>
                    <details class="th th-m" <?= $fy && $fm ? 'open' : '' ?>>
                        <summary><?= $chev ?><b><?= date('F', strtotime($mo . '-01')) ?></b><?= th_sum($mn) ?></summary>
                        <?php $fw = true;
                        foreach ($mn['kids'] as $wk => $wn): $we = strtotime($wk . ' +6 days'); ?>
                            <details class="th th-w" <?= $fy && $fm && $fw ? 'open' : '' ?>>
                                <summary><?= $chev ?><b>Week of <?= date('M j', strtotime($wk)) ?> &ndash; <?= date('M j', $we) ?></b><?= th_sum($wn) ?></summary>
                                <?php $fd = true;
                                foreach ($wn['kids'] as $d => $dn): ?>
                                    <details class="th th-d" <?= $fy && $fm && $fw && $fd ? 'open' : '' ?>>
                                        <summary><?= $chev ?><b><?= date('l, F j', strtotime($d)) ?></b><?= th_sum($dn) ?></summary>
                                        <div class="tablewrap th-t">
                                            <table>
                                                <tr>
                                                    <th>Queued</th>
                                                    <th>Departed</th>
                                                    <th>Passengers</th>
                                                </tr>
                                                <?php foreach ($dn['rows'] as $r) echo "<tr><td>" . $tm($r['start_time']) . "</td><td>" . ($r['status'] === 'Departed' || $r['departure_time'] ? $tm($r['departure_time']) : '<span class="muted">On queue</span>') . "</td><td>" . (int)$r['pax'] . "</td></tr>"; ?>
                                            </table>
                                        </div>
                                    </details>
                                <?php $fd = false;
                                endforeach; ?>
                            </details>
                        <?php $fw = false;
                        endforeach; ?>
                    </details>
                <?php $fm = false;
                endforeach; ?>
            </details>
        <?php $fy = false;
        endforeach; ?>
    </div>
<?php endif;
page_foot('operator');
