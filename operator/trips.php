<?php
require_once '../config/layout.php';
need('operator');
$m = my_mb();
if (!$m) {
    header('Location: ../logout.php');
    exit;
}
$q = db()->prepare("SELECT t.id, DATE(COALESCE(t.departure_time, t.trip_date)) trip_date, t.start_time, t.departure_time, t.status, COUNT(p.id) pax FROM trips t
    LEFT JOIN passengers p ON p.trip_id=t.id
    WHERE t.motorbanca_id=? AND t.status IN ('Departed','Arrived','Completed')
    GROUP BY t.id ORDER BY COALESCE(t.departure_time, t.trip_date) DESC, t.id DESC");
$q->execute([$m['id']]);
$rows = $q->fetchAll();
$tree = [];
foreach ($rows as $r) {
    $ts = strtotime($r['trip_date']);
    $tree[(string)date('Y', $ts)][(int)date('n', $ts)][$r['trip_date']][] = $r;
}
function hx_tally($days)
{
    $t = 0;
    $p = 0;
    foreach ($days as $list) {
        foreach ($list as $r) {
            $t++;
            $p += (int)$r['pax'];
        }
    }
    return [$t, $p];
}
function hx_plural($n, $w)
{
    return $n . ' ' . $w . ($n == 1 ? '' : 's');
}
$tm = fn($v) => $v ? date('g:i A', strtotime($v)) : '&mdash;';
$first = $tree ? (string)array_key_first($tree) : '';
$arw = '<svg class="hx-arw" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M13 6l6 6-6 6"/></svg>';
$chev = '<svg class="hx-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>';
page_head('Trip History', 'operator');
?>
<h1>Trip history</h1>
<?php if (!$rows): ?>
    <p class="hx-none">No trips yet.</p>
<?php else: ?>
    <div class="hx" id="hx">
        <div class="hx-mos">
            <select class="hx-ys on" id="hx-y" aria-label="Year">
                <?php foreach ($tree as $y => $months): ?>
                    <option value="<?= e($y) ?>"><?= e($y) ?></option>
                <?php endforeach; ?>
            </select>
            <div class="hx-ms" aria-label="Month">
                <?php foreach ($tree as $y => $months): foreach ($months as $mo => $days): ?>
                        <button type="button" class="hx-mb" data-y="<?= e($y) ?>" data-m="<?= $mo ?>" <?= (string)$y === $first ? '' : 'hidden' ?>><?= date('F', mktime(0, 0, 0, $mo, 1)) ?></button>
                <?php endforeach;
                endforeach; ?>
            </div>
        </div>
        <?php foreach ($tree as $y => $months): foreach ($months as $mo => $days): [$mt, $mp] = hx_tally($days); ?>
                <section class="hx-mon" data-y="<?= e($y) ?>" data-m="<?= $mo ?>" <?= (string)$y === $first ? '' : 'hidden' ?>>
                    <header>
                        <h2><?= date('F', mktime(0, 0, 0, $mo, 1)) ?></h2>
                        <span><?= hx_plural($mt, 'trip') ?> &middot; <?= hx_plural($mp, 'passenger') ?></span>
                    </header>
                    <?php foreach ($days as $d => $list): ?>
                        <div class="hx-day">
                            <div class="hx-date"><b><?= date('j', strtotime($d)) ?></b><span><?= date('D', strtotime($d)) ?></span></div>
                            <ul class="hx-rows">
                                <?php foreach ($list as $r):
                                    $gone = $r['status'] === 'Departed' || $r['departure_time']; ?>
                                    <li>
                                        <button type="button" class="hx-row" data-trip="<?= (int)$r['id'] ?>" data-name="<?= e(date('M j, Y', strtotime($r['trip_date']))) ?>">
                                            <span class="hx-tm"><b><?= $tm($r['start_time']) ?></b><i>Queued</i></span>
                                            <?= $arw ?>
                                            <span class="hx-tm<?= $gone ? '' : ' off' ?>"><b><?= $gone ? $tm($r['departure_time']) : 'On queue' ?></b><i>Departed</i></span>
                                            <span class="hx-px"><b><?= (int)$r['pax'] ?></b><i><?= (int)$r['pax'] == 1 ? 'passenger' : 'passengers' ?></i></span>
                                            <?= $chev ?>
                                        </button>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endforeach; ?>
                </section>
        <?php endforeach;
        endforeach; ?>
    </div>
    <script>
        (function() {
            var root = document.getElementById('hx');
            if (!root) return;
            var ys = document.getElementById('hx-y'),
                mb = [].slice.call(root.querySelectorAll('.hx-mb')),
                secs = [].slice.call(root.querySelectorAll('.hx-mon')),
                m = '';

            function run() {
                var y = ys.value;
                ys.classList.toggle('on', !m);
                mb.forEach(function(b) {
                    b.hidden = b.getAttribute('data-y') !== y;
                    b.classList.toggle('on', b.getAttribute('data-m') === m)
                });
                secs.forEach(function(s) {
                    s.hidden = s.getAttribute('data-y') !== y || (!!m && s.getAttribute('data-m') !== m)
                })
            }
            ys.addEventListener('change', function() {
                m = '';
                run()
            });
            mb.forEach(function(b) {
                b.addEventListener('click', function() {
                    var k = b.getAttribute('data-m');
                    m = m === k ? '' : k;
                    run()
                })
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

        function open(r) {
            var id = r.getAttribute('data-trip');
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
                    body.innerHTML = d.html;
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
        document.addEventListener('click', function(e) {
            var r = e.target.closest && e.target.closest('.hx-row[data-trip]');
            if (r) open(r)
        });
        box.addEventListener('click', function(e) {
            if (e.target === box || e.target.classList.contains('x')) close()
        });

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
            if (ok) {
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
