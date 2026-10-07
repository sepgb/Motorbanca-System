<?php
// Shared "one source of truth" helpers used by admin, operator and passenger pages.
require_once __DIR__ . '/database.php';

function badge($s)
{
    $m = [
        'Not Queued' => ['⚪', 'gray'],
        'Queued' => ['🟢', 'green'],
        'Boarding' => ['🟡', 'yellow'],
        'Departed' => ['🔵', 'blue'],
        'Arrived' => ['🔵', 'teal'],
        'Completed' => ['⚪', 'gray']
    ];
    [$i, $c] = $m[$s] ?? ['⚪', 'gray'];
    return "<span class='badge $c'>$i " . strtoupper(e($s)) . "</span>";
}
function count_html($p, $c)
{
    $full = $p >= $c;
    $w = $c ? min(100, round($p / $c * 100)) : 0;
    return "<div class='count" . ($full ? ' full' : '') . "'><b>$p</b><span>/ $c</span>" .
        ($full ? "<em>🔴 FULL</em>" : '') . "</div><div class='bar'><i style='width:$w%'></i></div>";
}
// Passengers shown for a boat = those on its latest (non-cancelled) trip.
const PAX_SQL = "IF(m.status='Not Queued',0,(SELECT COUNT(*) FROM passengers p WHERE p.trip_id=(SELECT MAX(t.id) FROM trips t WHERE t.motorbanca_id=m.id AND t.status<>'Cancelled')))";
// A departed boat stays on the board for 2 minutes, then it is removed.
const DEP_RECENT = "EXISTS(SELECT 1 FROM trips t WHERE t.motorbanca_id=m.id AND t.status='Departed' AND t.departure_time > NOW() - INTERVAL 2 MINUTE)";

function live_rows()
{
    return db()->query("SELECT m.id, m.motorbanca_name name, m.capacity, m.status, m.cluster_id, " . PAX_SQL . " pax,
        (SELECT 120 - TIMESTAMPDIFF(SECOND, MAX(t.departure_time), NOW()) FROM trips t WHERE t.motorbanca_id=m.id AND t.status='Departed') left_s
        FROM motorbancas m WHERE m.status='Queued' OR (m.status='Departed' AND " . DEP_RECENT . ")
        ORDER BY (m.status='Departed'), m.queued_at, m.id")->fetchAll();
}
// Live queue, grouped by cluster (used on passenger page and admin pages).
function render_live($admin = false)
{
    process_departures();
    $rows = live_rows();
    foreach (db()->query("SELECT * FROM clusters ORDER BY id")->fetchAll() as $c) {
        [$dep, $q, $next] = split_cluster($rows, $c['id']);
        echo "<section class='cluster'><h2>" . e($c['name']) . "</h2>";
        echo $next
            ? "<div class='next yes'>⏭ Next Trip: <b>" . e($next['name']) . "</b></div>"
            : "<div class='next no'>⛔ No Next Trip Available</div>";
        echo "<div style='padding:12px 18px 0;font-size:.8rem;font-weight:700;color:#5b6e7c;letter-spacing:.06em'>ON QUEUE / DEPARTED</div>";
        if (!$dep && !$q) echo "<div class='mb'><p class='muted'>No motorbanca on queue right now.</p></div>";
        $pos = 0;
        foreach ($dep as $r) echo mb_card($r, ++$pos, '', $admin);
        // only the ON QUEUE boat gets a card; the next-trip boat is shown in the banner above
        foreach ($q as $i => $r) if (tag_for((bool)$dep, $i) === 'ON QUEUE') echo mb_card($r, ++$pos, 'ON QUEUE', $admin);
        echo "</section>";
    }
}
function stats_html()
{
    $q = fn($s) => (int)db()->query($s)->fetchColumn();
    $rows = live_rows();
    $nextCount = 0;
    foreach (db()->query("SELECT id FROM clusters")->fetchAll() as $c) if (split_cluster($rows, $c['id'])[2]) $nextCount++;
    $s = [
        'Total Motorbancas' => $q("SELECT COUNT(*) FROM motorbancas"),
        'Available' => $q("SELECT COUNT(*) FROM motorbancas m WHERE m.status='Not Queued' OR (m.status='Departed' AND NOT " . DEP_RECENT . ")"),
        'Currently Queued' => $q("SELECT COUNT(*) FROM motorbancas WHERE status='Queued'"),
        'Currently Departed' => $q("SELECT COUNT(*) FROM motorbancas m WHERE m.status='Departed' AND " . DEP_RECENT),
        'Next Trips' => $nextCount,
        'Passengers Today' => $q("SELECT COUNT(*) FROM passengers p JOIN trips t ON t.id=p.trip_id
                                WHERE t.trip_date=CURDATE() AND t.status<>'Cancelled'"),
    ];
    $icons = array_values(STAT_ICONS);
    $n = 0;
    foreach ($s as $k => $v) echo "<div class='stat'>" . ico($icons[$n++]) . "<b>$v</b><span>" . e($k) . "</span></div>";
}
function my_mb()
{
    $s = db()->prepare("SELECT m.*, c.name cluster, " . PAX_SQL . " pax FROM motorbancas m
                        JOIN clusters c ON c.id=m.cluster_id WHERE m.id=?");
    $s->execute([$_SESSION['mid'] ?? 0]);
    return $s->fetch();
}
function op_card($m)
{
    echo "<div class='row'>" . badge($m['status']) . "</div>" . count_html($m['pax'], $m['capacity']) .
        "<p class='muted'>Capacity " . (int)$m['capacity'] . "</p>";
    if ($m['status'] === 'Queued') {   // tell the operator where they are in the queue
        [$dep, $q] = split_cluster(live_rows(), $m['cluster_id']);
        foreach ($q as $i => $r) if ($r['id'] == $m['id'])
            echo "<p style='font-weight:800;color:#0b7285'>📍 " . tag_for((bool)$dep, $i) . "</p>" .
                (($i + ($dep ? 1 : 0)) > 0 ? "<p class='muted'>You can add passengers once you are ON QUEUE.</p>" : '');
    }
}
// The ONLY place a status changes: keeps motorbanca + trip in sync.
function set_status($mid, $st)
{
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE motorbancas SET status=?, queued_at=IF(?='Queued',NOW(),queued_at) WHERE id=?")->execute([$st, $st, $mid]);
    $new = "INSERT INTO trips(motorbanca_id,cluster_id,status,trip_date%s) SELECT id,cluster_id,'%s',CURDATE()%s FROM motorbancas WHERE id=?";
    if ($st === 'Queued') {            // joining the queue = a new trip (unless one is already waiting)
        $x = $pdo->prepare("SELECT 1 FROM trips WHERE motorbanca_id=? AND status='Queued'");
        $x->execute([$mid]);
        if (!$x->fetch()) $pdo->prepare(sprintf($new, '', 'Queued', ''))->execute([$mid]);
    } elseif ($st === 'Departed') {
        $u = $pdo->prepare("UPDATE trips SET status='Departed', departure_time=NOW() WHERE motorbanca_id=? AND status='Queued'");
        $u->execute([$mid]);
        if (!$u->rowCount()) $pdo->prepare(sprintf($new, ',departure_time', 'Departed', ',NOW()'))->execute([$mid]);
    }
    $pdo->commit();
}

const STAT_ICONS = [
    '<path d="M3 17l2 3h14l2-3H3z"/><path d="M12 3v10M12 5l6 8H6"/>',
    '<circle cx="12" cy="12" r="9"/><path d="M8 12l3 3 5-6"/>',
    '<path d="M8 6h12M8 12h12M8 18h12M4 6h.01M4 12h.01M4 18h.01"/>',
    '<path d="M5 19L19 5M9 5h10v10"/>',
    '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
    '<rect x="3" y="6" width="18" height="12" rx="3"/><path d="M9 6v12M6 10h.01"/>'
];
function ico($p)
{
    return "<svg class='ico' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'>$p</svg>";
}
// "Fleet status" card (admin dashboard)
function fleet_html()
{
    $q = fn($s) => (int)db()->query($s)->fetchColumn();
    $t = $q("SELECT COUNT(*) FROM motorbancas");
    $dep = $q("SELECT COUNT(*) FROM motorbancas m WHERE m.status='Departed' AND " . DEP_RECENT);
    $que = $q("SELECT COUNT(*) FROM motorbancas WHERE status='Queued'");
    $a = $que + $dep;
    $pct = $t ? round($a / $t * 100) : 0;
    echo "<h3>Fleet status</h3><p class='muted'>$a of $t motorbancas in service</p><div class='bar'><i style='width:$pct%'></i></div>" .
        "<p class='muted'>Queued: <b>$que</b> &nbsp;·&nbsp; Departed: <b>$dep</b></p>";
}

function cd_html($s)
{
    $s = max(0, (int)$s);
    return sprintf("<p style='margin:.3em 0;color:#12468f;font-weight:600'>⏱ Leaves the board in <b class='cd' data-s='%d'>%d:%02d</b></p>", $s, intdiv($s, 60), $s % 60);
}
// Runs on every board refresh: 2 minutes after the PCG marks a boat Departed it is removed from the
// board (status goes back to Not Queued). The next boat then moves up by itself, because
// "on queue" and "next trip" are worked out from the queue order (see split_cluster).
function process_departures()
{
    db()->exec("UPDATE motorbancas m SET m.status='Not Queued' WHERE m.status='Departed' AND NOT " . DEP_RECENT);
}
// Queue order = the order operators tapped NEW TRIP. While a departed boat is still on the board
// (its 2 minutes) it holds the "on queue" spot, so the first Queued boat is the Next Trip.
// Otherwise the first Queued boat is on queue and the second one is the Next Trip.
function split_cluster($rows, $cid)
{
    $in = array_values(array_filter($rows, fn($r) => $r['cluster_id'] == $cid));
    $dep = array_values(array_filter($in, fn($r) => $r['status'] === 'Departed'));
    $q = array_values(array_filter($in, fn($r) => $r['status'] === 'Queued'));
    return [$dep, $q, $dep ? ($q[0] ?? null) : ($q[1] ?? null)];
}
function tag_for($hasDep, $i)
{
    $p = $i + ($hasDep ? 1 : 0);
    return $p === 0 ? 'ON QUEUE' : ($p === 1 ? 'NEXT TRIP' : 'WAITING');
}
function mb_card($r, $pos, $tag, $admin = false)
{
    $wait = in_array($tag, ['NEXT TRIP', 'WAITING'], true);   // not boarding yet: just shown as available
    return "<article class='mb'><div class='mb-top'><span class='pos'>$pos</span><h3>" . e($r['name']) . "</h3>" . badge($r['status']) . "</div>" .
        ($tag ? "<p style='margin:.3em 0;font-size:.75rem;font-weight:800;letter-spacing:.06em;color:#0b7285'>$tag</p>" : '') .
        ($wait ? "<p class='muted' style='font-size:1rem'>✅ Available · capacity " . (int)$r['capacity'] . "</p>"
            : count_html($r['pax'], $r['capacity']) . "<p class='muted'>passengers on board</p>") .
        ($r['status'] === 'Departed' ? cd_html($r['left_s']) : '') .
        ($admin && $tag === 'ON QUEUE' ? depart_form($r['id']) : '') . "</article>";
}
// The one boat in a cluster that is ON QUEUE: it is the only one that takes passengers and can be departed.
function on_queue_id($cid)
{
    [$dep, $q] = split_cluster(live_rows(), $cid);
    return (!$dep && $q) ? $q[0]['id'] : null;
}
// Depart button (bottom-right of the ON QUEUE card). Only shown on admin pages; admin/depart.php re-checks everything.
function depart_form($id)
{
    return "<form method='post' action='" . BASE . "/admin/depart.php' style='text-align:right;margin-top:8px'>" .
        "<input type='hidden' name='mid' value='" . (int)$id . "'>" .
        "<button class='btn sm red' onclick=\"return confirm('Mark this motorbanca as departed?')\">Depart</button></form>";
}
