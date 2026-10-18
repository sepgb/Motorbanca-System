<?php
// PCG: trip history of every boat. Phones: grouped Year > Month > Week > Day. Desktop: one table with Year / Month / Day filters.
require_once '../config/layout.php';
need('admin');
// A trip shows in the history only after it has departed, on the day it left.
$rows = db()->query("SELECT t.*, DATE(COALESCE(t.departure_time, t.trip_date)) trip_date, m.motorbanca_name n, c.name cname, COUNT(p.id) pax FROM trips t
    JOIN motorbancas m ON m.id=t.motorbanca_id JOIN clusters c ON c.id=t.cluster_id
    LEFT JOIN passengers p ON p.trip_id=t.id WHERE t.status IN ('Departed','Arrived','Completed') GROUP BY t.id ORDER BY COALESCE(t.departure_time, t.trip_date) DESC, t.id DESC")->fetchAll();
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
foreach ([(int)date('Y'), (int)date('Y') + 1] as $yy) if (!isset($tree[$yy])) $tree[$yy] = ['trips' => 0, 'pax' => 0, 'kids' => []];   // this year and next year are always shown
ksort($tree);   // years side by side, oldest on the left
function th_sum($n)
{
    return "<span class='th-sum'>" . $n['trips'] . ' trip' . ($n['trips'] == 1 ? '' : 's') . ' &middot; ' . $n['pax'] . ' passenger' . ($n['pax'] == 1 ? '' : 's') . "</span>";
}
$chev = '<svg class="th-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>';
$tm = fn($v) => $v ? date('g:i A', strtotime($v)) : '&mdash;';
page_head('Trip History', 'admin');
?>
<?php $yrs = array_values(array_unique(array_map(fn($r) => date('Y', strtotime($r['trip_date'])), $rows))); ?>
<h1 style="margin-bottom:18px">Trip history</h1>
<?php if (!$rows): ?>
    <div class="card">
        <p class="muted" style="margin:0">No trips yet.</p>
    </div>
<?php else: ?>
    <div class="thx th-mob">
        <?php $fy = true;
        foreach ($tree as $y => $yn): ?>
            <details class="th th-y">
                <summary><?= $chev ?><b><?= e($y) ?></b><?= th_sum($yn) ?></summary>
                <?php if (!$yn['kids']) echo '<p class="muted th-none">No trips yet.</p>'; ?>
                <?php $fm = true;
                foreach ($yn['kids'] as $mo => $mn): ?>
                    <details class="th th-m">
                        <summary><?= $chev ?><b><?= date('F', strtotime($mo . '-01')) ?></b><?= th_sum($mn) ?></summary>
                        <?php $fw = true;
                        foreach ($mn['kids'] as $wk => $wn): $we = strtotime($wk . ' +6 days'); ?>
                            <details class="th th-w">
                                <summary><?= $chev ?><b><?= date('M j', strtotime($wk)) ?> &ndash; <?= date('M j', $we) ?></b><?= th_sum($wn) ?></summary>
                                <?php $fd = true;
                                foreach ($wn['kids'] as $d => $dn): ?>
                                    <details class="th th-d">
                                        <summary><?= $chev ?><b><?= date('l, F j', strtotime($d)) ?></b><?= th_sum($dn) ?></summary>
                                        <div class="tablewrap th-t">
                                            <table>
                                                <tr>
                                                    <th>Motorbanca</th>
                                                    <th>Cluster</th>
                                                    <th>Queued</th>
                                                    <th>Departed</th>
                                                    <th>Passengers</th>
                                                </tr>
                                                <?php foreach ($dn['rows'] as $r) echo "<tr class='trip-row' data-hint='Double-click to see documents' data-name='" . e($r['n']) . "' data-url='" . BASE . "/admin/passengers.php?trip=" . (int)$r['id'] . "'><td>" . e($r['n']) . "</td><td>" . e($r['cname']) . "</td><td>" . $tm($r['start_time']) . "</td><td>" . ($r['status'] === 'Departed' || $r['departure_time'] ? $tm($r['departure_time']) : '<span class=\"muted\">On queue</span>') . "</td><td>" . (int)$r['pax'] . "</td></tr>"; ?>
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
    <div class="th-desk">
        <form class="thf" onsubmit="return false" aria-label="Filter trips by date">
            <label>Year<select id="f-y">
                    <option value="">All years</option>
                    <?php foreach ($yrs as $yy) echo "<option value='" . e($yy) . "'>" . e($yy) . "</option>"; ?>
                </select></label>
            <label>Month<select id="f-m">
                    <option value="">All months</option>
                    <?php foreach (range(1, 12) as $i) echo "<option value='$i'>" . date('F', mktime(0, 0, 0, $i, 1)) . "</option>"; ?>
                </select></label>
            <label>Day<select id="f-d">
                    <option value="">All days</option>
                    <?php foreach (range(1, 31) as $i) echo "<option value='$i'>$i</option>"; ?>
                </select></label>
            <button type="button" class="btn sm ghost" id="f-clear">Clear</button>
            <span class="thf-sum" id="f-sum"></span>
        </form>
        <div class="tablewrap th-t">
            <table id="th-tbl">
                <tr>
                    <th>Date</th>
                    <th>Motorbanca</th>
                    <th>Cluster</th>
                    <th>Queued</th>
                    <th>Departed</th>
                    <th>Passengers</th>
                </tr>
                <?php foreach ($rows as $r) {
                    $ts = strtotime($r['trip_date']);
                    echo "<tr class='trip-row' data-y='" . date('Y', $ts) . "' data-m='" . (int)date('n', $ts) . "' data-d='" . (int)date('j', $ts) . "' data-pax='" . (int)$r['pax'] . "' data-hint='Double-click to see documents' data-name='" . e($r['n']) . "' data-url='" . BASE . "/admin/passengers.php?trip=" . (int)$r['id'] . "'><td>" . date('M j, Y', $ts) . "</td><td>" . e($r['n']) . "</td><td>" . e($r['cname']) . "</td><td>" . $tm($r['start_time']) . "</td><td>" . ($r['status'] === 'Departed' || $r['departure_time'] ? $tm($r['departure_time']) : '<span class="muted">On queue</span>') . "</td><td>" . (int)$r['pax'] . "</td></tr>";
                } ?>
                <tr class="th-empty th-off">
                    <td colspan="6" class="muted">No trips on these dates.</td>
                </tr>
            </table>
        </div>
    </div>
    <script>
        (function() {
            var f = {
                    y: document.getElementById('f-y'),
                    m: document.getElementById('f-m'),
                    d: document.getElementById('f-d')
                },
                rows = [].slice.call(document.querySelectorAll('#th-tbl tr[data-y]')),
                none = document.querySelector('#th-tbl .th-empty'),
                sum = document.getElementById('f-sum');

            function run() {
                var n = 0,
                    p = 0;
                rows.forEach(function(r) {
                    var ok = (!f.y.value || r.dataset.y === f.y.value) && (!f.m.value || r.dataset.m === f.m.value) && (!f.d.value || r.dataset.d === f.d.value);
                    r.classList.toggle('th-off', !ok);
                    if (ok) {
                        n++;
                        p += +r.dataset.pax
                    }
                });
                none.classList.toggle('th-off', n > 0);
                sum.textContent = n + ' trip' + (n === 1 ? '' : 's') + ' \u00b7 ' + p + ' passenger' + (p === 1 ? '' : 's')
            }
            [f.y, f.m, f.d].forEach(function(s) {
                s.addEventListener('change', run)
            });
            document.getElementById('f-clear').addEventListener('click', function() {
                f.y.value = f.m.value = f.d.value = '';
                run()
            });
            run()
        })();
    </script>
<?php endif; ?>
<?php page_foot('admin');
