<?php
require_once '../config/layout.php';
need('admin');
$rows = db()->query("SELECT t.id, DATE(COALESCE(t.departure_time, t.trip_date)) trip_date, t.start_time, t.departure_time, t.status, m.motorbanca_name n, c.name cname, MAX(tc.crew) crew, COUNT(p.id) pax FROM trips t
    JOIN motorbancas m ON m.id=t.motorbanca_id JOIN clusters c ON c.id=t.cluster_id
    LEFT JOIN trip_crew tc ON tc.trip_id=t.id LEFT JOIN passengers p ON p.trip_id=t.id WHERE t.status IN ('Departed','Arrived','Completed')
    GROUP BY t.id ORDER BY COALESCE(t.departure_time, t.trip_date) DESC, t.id DESC")->fetchAll();
$tree = [];
foreach ($rows as $r) {
    $ts = strtotime($r['trip_date']);
    $tree[(string)date('Y', $ts)][(int)date('n', $ts)][$r['trip_date']][] = $r;
}
function hx_captain($json)
{
    $c = json_decode((string)$json, true);
    if (is_array($c)) {
        foreach ($c as $m) {
            if (strtoupper(trim($m['position'] ?? '')) === 'BOAT CAPTAIN' && trim($m['name'] ?? '') !== '') return trim($m['name']);
        }
    }
    return '';
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
$k = 0;
$chev = '<svg class="hx-chev" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 6l6 6-6 6"/></svg>';
page_head('Trip History', 'admin');
?>
<h1>Trip history</h1>
<?php if (!$rows): ?>
    <p class="hx-none">No trips yet.</p>
<?php else: ?>
    <div class="hx" id="hx">
        <div class="hx-mos">
            <div class="hx-dd" id="hx-dd">
                <button type="button" class="hx-ys" aria-haspopup="listbox" aria-expanded="false" aria-label="Year"><span id="hx-yv"><?= e($first) ?></span></button>
                <ul class="hx-opts" role="listbox" aria-label="Year" hidden>
                    <?php foreach ($tree as $y => $months): ?>
                        <li role="option" tabindex="-1" style="--i:<?= $k++ ?>" data-y="<?= e($y) ?>" aria-selected="<?= (string)$y === $first ? 'true' : 'false' ?>"><?= e($y) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="hx-ms" aria-label="Month">
                <?php foreach ($tree as $y => $months): $ms = $months;
                    ksort($ms);
                    foreach ($ms as $mo => $days): ?>
                        <button type="button" class="hx-mb" data-y="<?= e($y) ?>" data-m="<?= $mo ?>" <?= (string)$y === $first ? '' : 'hidden' ?>><?= date('F', mktime(0, 0, 0, $mo, 1)) ?></button>
                <?php endforeach;
                endforeach; ?>
            </div>
        </div>
        <?php foreach ($tree as $y => $months): foreach ($months as $mo => $days): [$mt, $mp] = hx_tally($days); ?>
                <section class="hx-mon" data-y="<?= e($y) ?>" data-m="<?= $mo ?>" <?= (string)$y === $first ? '' : 'hidden' ?>>
                    <header class="hx-a">
                        <div class="hx-mt">
                            <h2><?= date('F', mktime(0, 0, 0, $mo, 1)) ?></h2>
                            <small><?= hx_plural($mt, 'trip') ?> &middot; <?= hx_plural($mp, 'passenger') ?></small>
                        </div>
                        <span>Motorbanca</span><span>Captain</span><span>Queued</span><span>Departed</span><span>Passengers</span><span></span>
                    </header>
                    <?php foreach ($days as $d => $list): ?>
                        <div class="hx-day">
                            <ul class="hx-rows">
                                <?php foreach ($list as $n => $r):
                                    $gone = $r['status'] === 'Departed' || $r['departure_time'];
                                    $cap = hx_captain($r['crew'] ?? ''); ?>
                                    <li>
                                        <button type="button" class="hx-row hx-a trip-row" data-name="<?= e($r['n']) ?>" data-url="<?= BASE ?>/admin/passengers.php?trip=<?= (int)$r['id'] ?>">
                                            <span class="hx-date"><?php if ($n === 0): ?><b><?= date('j', strtotime($d)) ?></b><span><?= date('D', strtotime($d)) ?></span><?php endif; ?></span>
                                            <span class="hx-bt"><b><?= e($r['n']) ?></b><i><?= e($r['cname']) ?></i></span>
                                            <span class="hx-tm hx-cp<?= $cap === '' ? ' off' : '' ?>"><b><?= $cap === '' ? '&mdash;' : e($cap) ?></b></span>
                                            <span class="hx-tm"><b><?= $tm($r['start_time']) ?></b></span>
                                            <span class="hx-tm<?= $gone ? '' : ' off' ?>"><b><?= $gone ? $tm($r['departure_time']) : 'On queue' ?></b></span>
                                            <span class="hx-px"><b><?= (int)$r['pax'] ?></b></span>
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
            var dd = document.getElementById('hx-dd'),
                btn = dd.querySelector('.hx-ys'),
                val = document.getElementById('hx-yv'),
                list = dd.querySelector('.hx-opts'),
                opts = [].slice.call(list.children),
                mb = [].slice.call(root.querySelectorAll('.hx-mb')),
                secs = [].slice.call(root.querySelectorAll('.hx-mon')),
                y = val.textContent,
                m = '';

            function run() {
                mb.forEach(function(b) {
                    b.hidden = b.getAttribute('data-y') !== y;
                    b.classList.toggle('on', b.getAttribute('data-m') === m)
                });
                secs.forEach(function(s) {
                    s.hidden = s.getAttribute('data-y') !== y || (!!m && s.getAttribute('data-m') !== m)
                })
            }

            function setOpen(o) {
                list.hidden = !o;
                btn.setAttribute('aria-expanded', o ? 'true' : 'false');
                if (o) {
                    var s = list.querySelector('[aria-selected="true"]');
                    (s || opts[0]).focus()
                }
            }

            function pick(o) {
                y = o.getAttribute('data-y');
                opts.forEach(function(x) {
                    x.setAttribute('aria-selected', x === o ? 'true' : 'false')
                });
                val.textContent = y;
                m = '';
                setOpen(false);
                btn.focus();
                run()
            }
            btn.addEventListener('click', function() {
                setOpen(list.hidden)
            });
            opts.forEach(function(o) {
                o.addEventListener('click', function() {
                    pick(o)
                });
                o.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        pick(o)
                    } else if (e.key === 'ArrowDown') {
                        e.preventDefault();
                        (o.nextElementSibling || o).focus()
                    } else if (e.key === 'ArrowUp') {
                        e.preventDefault();
                        (o.previousElementSibling || o).focus()
                    }
                })
            });
            document.addEventListener('click', function(e) {
                if (!list.hidden && !dd.contains(e.target)) setOpen(false)
            });
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && !list.hidden) {
                    setOpen(false);
                    btn.focus()
                }
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
<?php page_foot('admin');