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
            <details class="th th-y">
                <summary><?= $chev ?><b><?= e($y) ?></b><?= th_sum($yn) ?></summary>
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
                                                    <th>Queued</th>
                                                    <th>Departed</th>
                                                    <th>Passengers</th>
                                                    <th></th>
                                                </tr>
                                                <?php foreach ($dn['rows'] as $r) echo "<tr class='th-row'><td>" . $tm($r['start_time']) . "</td><td>" . ($r['status'] === 'Departed' || $r['departure_time'] ? $tm($r['departure_time']) : '<span class="muted">On queue</span>') . "</td><td>" . (int)$r['pax'] . "</td><td class='th-act'><button type='button' class='btn sm ghost pdfbtn' data-pdf='print.php?trip=" . (int)$r['id'] . "'><svg class=\"ico\" viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\"><path d=\"M6 9V3h12v6\"/><path d=\"M6 18H4a1 1 0 0 1-1-1v-6a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2v6a1 1 0 0 1-1 1h-2\"/><path d=\"M6 14h12v7H6z\"/></svg><span>Print manifest</span></button></td></tr>"; ?>
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
<?php endif; ?>
<div class="mdl" id="pdf-mdl" role="dialog" aria-modal="true" aria-labelledby="pdf-t">
    <div class="mdl-box">
        <div class="mdl-ic"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 4v11M7 11l5 5 5-5M5 20h14" />
            </svg></div>
        <h3 id="pdf-t">Download manifest?</h3>
        <p>The manifest of this trip will be saved as a PDF.</p>
        <div class="mdl-act"><button type="button" class="no">Cancel</button><button type="button" class="go">Download</button></div>
    </div>
</div>
<script>
    (function() {
        var m = document.getElementById('pdf-mdl'),
            btn = null,
            last = null,
            lt = 0;
        if (!m) return;

        function mobile() {
            return window.innerWidth < 1000
        }

        function ask(b) {
            btn = b;
            m.classList.add('open');
            m.querySelector('.go').focus()
        }

        function close() {
            m.classList.remove('open')
        }
        document.addEventListener('touchend', function(e) { // double tap on a trip row (phones and tablets)
            if (!mobile()) return;
            var r = e.target.closest && e.target.closest('.th-row');
            if (!r) return;
            var n = Date.now();
            if (last === r && n - lt < 400) {
                e.preventDefault();
                last = null;
                ask(r.querySelector('[data-pdf]'))
            } else {
                last = r;
                lt = n
            }
        });
        document.addEventListener('dblclick', function(e) {
            if (!mobile()) return;
            var r = e.target.closest('.th-row');
            if (r) ask(r.querySelector('[data-pdf]'))
        });
        m.querySelector('.no').addEventListener('click', close);
        m.querySelector('.go').addEventListener('click', function() {
            close();
            if (btn) btn.click()
        });
        m.addEventListener('click', function(e) {
            if (e.target === m) close()
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') close()
        });
    })();
</script>
<?php page_foot('operator');
