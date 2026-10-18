<?php
// Operator: this boat's trip history. Phones: grouped Year > Month > Week > Day. Desktop: one table with Year / Month / Day filters.
require_once '../config/layout.php';
need('operator');
$m = my_mb();
if (!$m) {
    header('Location: ../logout.php');
    exit;
}
// A trip shows in the history only after it has departed, on the day it left.
$q = db()->prepare("SELECT t.id, DATE(COALESCE(t.departure_time, t.trip_date)) trip_date, t.start_time, t.departure_time, t.status, COUNT(p.id) pax FROM trips t
    LEFT JOIN passengers p ON p.trip_id=t.id
    WHERE t.motorbanca_id=? AND t.status IN ('Departed','Arrived','Completed')
    GROUP BY t.id ORDER BY COALESCE(t.departure_time, t.trip_date) DESC, t.id DESC");
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
foreach ([(int)date('Y'), (int)date('Y') + 1] as $yy) if (!isset($tree[$yy])) $tree[$yy] = ['trips' => 0, 'pax' => 0, 'kids' => []];   // this year and next year are always shown
ksort($tree);   // years side by side, oldest on the left
function th_sum($n)
{
    return "<span class='th-sum'>" . $n['trips'] . ' trip' . ($n['trips'] == 1 ? '' : 's') . ' &middot; ' . $n['pax'] . ' passenger' . ($n['pax'] == 1 ? '' : 's') . "</span>";
}
$chev = '<svg class="th-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 6l6 6-6 6"/></svg>';
$tm = fn($v) => $v ? date('g:i A', strtotime($v)) : '&mdash;';
page_head('Trip History', 'operator');
?>
<?php $yrs = array_values(array_unique(array_map(fn($r) => date('Y', strtotime($r['trip_date'])), $rows))); ?>
<h1>Trip history</h1>
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
                                                    <th>Queued</th>
                                                    <th>Departed</th>
                                                    <th>Passengers</th>
                                                </tr>
                                                <?php foreach ($dn['rows'] as $r) echo "<tr class='th-row' data-trip='" . (int)$r['id'] . "' data-name='" . e(date('M j, Y', strtotime($r['trip_date']))) . "' data-hint='Double-click to see documents'><td>" . $tm($r['start_time']) . "</td><td>" . ($r['status'] === 'Departed' || $r['departure_time'] ? $tm($r['departure_time']) : '<span class="muted">On queue</span>') . "</td><td>" . (int)$r['pax'] . "</td></tr>"; ?>
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
                    <th>Queued</th>
                    <th>Departed</th>
                    <th>Passengers</th>
                </tr>
                <?php foreach ($rows as $r) {
                    $ts = strtotime($r['trip_date']);
                    echo "<tr class='th-row' data-trip='" . (int)$r['id'] . "' data-name='" . e(date('M j, Y', $ts)) . "' data-hint='Double-click to see documents' data-y='" . date('Y', $ts) . "' data-m='" . (int)date('n', $ts) . "' data-d='" . (int)date('j', $ts) . "' data-pax='" . (int)$r['pax'] . "'><td>" . date('M j, Y', $ts) . "</td><td>" . $tm($r['start_time']) . "</td><td>" . ($r['status'] === 'Departed' || $r['departure_time'] ? $tm($r['departure_time']) : '<span class="muted">On queue</span>') . "</td><td>" . (int)$r['pax'] . "</td></tr>";
                } ?>
                <tr class="th-empty th-off">
                    <td colspan="4" class="muted">No trips on these dates.</td>
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
<div class="mdl" id="tr-mdl" role="dialog" aria-modal="true" aria-labelledby="tr-t">
    <div class="mdl-box wide">
        <div class="mdl-head">
            <div class="mdl-title">
                <h3 id="tr-t"></h3>
                <p class="sub"></p>
            </div>
            <div class="mdl-tools">
                <button type="button" class="btn sm ghost pdfbtn" data-doc="crew"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 4v11M7 11l5 5 5-5M5 20h14" />
                    </svg><span>Crew list</span></button>
                <button type="button" class="btn sm ghost pdfbtn" data-doc="manifest"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 4v11M7 11l5 5 5-5M5 20h14" />
                    </svg><span>Manifest</span></button>
                <button type="button" class="btn sm ghost pdfbtn" data-doc="mdsd" disabled><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 4v11M7 11l5 5 5-5M5 20h14" />
                    </svg><span>MDSD</span></button>
                <button type="button" class="btn sm ghost pdfbtn" data-doc="pdic" disabled><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 4v11M7 11l5 5 5-5M5 20h14" />
                    </svg><span>PDIC</span></button>
            </div>
            <button type="button" class="x" aria-label="Close">&times;</button>
        </div>
        <div class="mdl-body"></div>
    </div>
</div>
<div class="mdl" id="dl-mdl" role="dialog" aria-modal="true" aria-labelledby="dl-t">
    <div class="mdl-box">
        <div class="mdl-ic"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 4v11M7 11l5 5 5-5M5 20h14" />
            </svg></div>
        <h3 id="dl-t">Download document?</h3>
        <p id="dl-p">This document will be saved as a PDF.</p>
        <div class="mdl-act"><button type="button" class="no">Cancel</button><button type="button" class="go">Download</button></div>
    </div>
</div>
<script>
    // Trip history: double-click a trip (tap it on a phone) to see its passengers and download its 4 documents as PDF files.
    (function() {
        var box = document.getElementById('tr-mdl'),
            docs = {
                crew: 'print_crew.php',
                manifest: 'print.php',
                mdsd: '../admin/mdsd_print.php',
                pdic: '../admin/pdic_print.php'
            };
        if (!box) return;
        var h = box.querySelector('h3'),
            sub = box.querySelector('.sub'),
            body = box.querySelector('.mdl-body');

        function bt(k) {
            return box.querySelector('[data-doc="' + k + '"]')
        }

        function mobile() {
            return window.innerWidth < 1000
        }

        function open(r) {
            var id = r.getAttribute('data-trip');
            if (window.getSelection) window.getSelection().removeAllRanges();
            h.textContent = r.getAttribute('data-name') || 'Trip';
            sub.textContent = 'Loading\u2026';
            body.innerHTML = '';
            Object.keys(docs).forEach(function(k) {
                var b = bt(k);
                b.setAttribute('data-pdf', docs[k] + '?trip=' + encodeURIComponent(id));
                if (k === 'mdsd' || k === 'pdic') {
                    b.disabled = true;
                    b.title = ''
                }
            });
            void box.offsetWidth;
            box.classList.add('open');
            fetch('trip_pax.php?trip=' + encodeURIComponent(id), {
                    credentials: 'same-origin'
                })
                .then(function(x) {
                    return x.ok ? x.json() : Promise.reject()
                })
                .then(function(d) {
                    h.textContent = d.title;
                    sub.textContent = d.sub;
                    body.innerHTML = d.html; // built and escaped on the server
                    ['mdsd', 'pdic'].forEach(function(k) {
                        var b = bt(k),
                            on = !!d[k];
                        b.disabled = !on;
                        b.title = on ? '' : 'No ' + k.toUpperCase() + ' was made for this trip'
                    })
                })
                .catch(function() {
                    sub.textContent = 'Could not load the passengers.'
                })
        }

        function close() {
            box.classList.remove('open')
        }
        document.addEventListener('dblclick', function(e) { // desktop: double-click, like the PCG Trip History
            if (mobile()) return;
            var r = e.target.closest && e.target.closest('.th-row[data-trip]');
            if (r) open(r)
        });
        document.addEventListener('click', function(e) { // phones and tablets: one tap
            if (!mobile()) return;
            var r = e.target.closest && e.target.closest('.th-row[data-trip]');
            if (r) open(r)
        });
        box.addEventListener('click', function(e) {
            if (e.target === box || e.target.classList.contains('x')) close()
        });

        // a question comes first: Download? Cancel / Download
        var dm = document.getElementById('dl-mdl'),
            pick = null,
            ok = false;

        function name(b) {
            return b.querySelector('span').textContent
        }

        function askNo() {
            dm.classList.remove('open')
        }
        box.addEventListener('click', function(e) {
            var b = e.target.closest && e.target.closest('[data-pdf]');
            if (!b) return;
            if (ok) { // confirmed: let the click go on to the download
                ok = false;
                return
            }
            e.stopPropagation();
            if (b.disabled) return;
            pick = b;
            var n = name(b),
                l = (n === 'MDSD' || n === 'PDIC') ? n : n.toLowerCase();
            document.getElementById('dl-t').textContent = 'Download ' + l + '?';
            document.getElementById('dl-p').textContent = 'The ' + l + ' of this trip will be saved as a PDF.';
            void dm.offsetWidth;
            dm.classList.add('open');
            dm.querySelector('.go').focus()
        });
        dm.querySelector('.no').addEventListener('click', askNo);
        dm.querySelector('.go').addEventListener('click', function() {
            askNo();
            if (pick) {
                ok = true;
                pick.click();
                ok = false
            }
        });
        dm.addEventListener('click', function(e) {
            if (e.target === dm) askNo()
        });
        document.addEventListener('keydown', function(e) {
            if (e.key !== 'Escape') return;
            if (dm.classList.contains('open')) askNo();
            else close()
        })
    })();
</script>
<?php page_foot('operator');
