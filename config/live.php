<?php
// Shared "one source of truth" helpers used by admin, operator and passenger pages.
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/announcements.php';

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
        "</div><div class='bar'><i style='width:$w%'></i></div>";
}
// Passengers shown for a boat = those on its latest (non-cancelled) trip.
const PAX_SQL = "IF(m.status='Not Queued',0,(SELECT COUNT(*) FROM passengers p WHERE p.trip_id=(SELECT MAX(t.id) FROM trips t WHERE t.motorbanca_id=m.id AND t.status<>'Cancelled')))";
// A departed boat stays on the board for 2 minutes, then it is removed.
const DEP_RECENT = "EXISTS(SELECT 1 FROM trips t WHERE t.motorbanca_id=m.id AND t.status='Departed' AND t.departure_time > NOW() - INTERVAL 2 MINUTE)";

function live_rows()
{
    return db()->query("SELECT m.id, m.motorbanca_name name, m.capacity, m.status, m.cluster_id, " . PAX_SQL . " pax,
        (SELECT 120 - TIMESTAMPDIFF(SECOND, MAX(t.departure_time), NOW()) FROM trips t WHERE t.motorbanca_id=m.id AND t.status='Departed') left_s,
        (SELECT DATE_FORMAT(DATE_ADD(t.start_time, INTERVAL 70 MINUTE), '%l:%i %p') FROM trips t WHERE t.motorbanca_id=m.id AND t.status='Queued' AND t.start_time IS NOT NULL ORDER BY t.id DESC LIMIT 1) etd
        FROM motorbancas m WHERE m.status='Queued' OR (m.status='Departed' AND " . DEP_RECENT . ")
        ORDER BY (m.status='Departed'), m.queued_at, m.id")->fetchAll();
}
// Boats that pressed New trip and are waiting for the PCG's physical inspection (admin queue page only).
function inspection_html()
{
    try {
        $rows = db()->query("SELECT m.id, m.motorbanca_name n, c.name cname, t.id tid FROM motorbancas m
            JOIN clusters c ON c.id=m.cluster_id JOIN trips t ON t.motorbanca_id=m.id AND t.status='Inspection'
            WHERE m.status='Inspection' ORDER BY t.id")->fetchAll();
    } catch (PDOException $x) {
        return '';
    }
    if (!$rows) return '';
    $h = "<section class='insp'><h2>For inspection <span>" . count($rows) . "</span></h2>";
    foreach ($rows as $r) {
        $tc = trip_crew_get($r['tid']);
        $cap = $tc['crew'][0]['name'] ?? '';
        $h .= "<div class='insp-row'><div class='insp-info'><b>" . e($r['n']) . "</b><small>" . e($r['cname']) . ($cap ? " &middot; Captain " . e($cap) : '') . "</small></div>" .
            "<form method='post' action='" . BASE . "/admin/inspect.php' class='insp-act'><input type='hidden' name='mid' value='" . (int)$r['id'] . "'>" .
            "<button name='do' value='reject' class='insp-no'>Reject</button>" .
            "<button name='do' value='approve' class='approve'>Approve<span>" . ico('<path d="M5 12.5l4.5 4.5L19 7.5"/>') . "</span></button></form></div>";
    }
    return $h . "</section>";
}
// Live queue, grouped by cluster (used on passenger page and admin pages).
function render_live($admin = false)
{
    process_departures();
    if ($admin) echo inspection_html();
    $rows = live_rows();
    $bmap = [];
    try {
        foreach (db()->query("SELECT cluster_id, name FROM barangays ORDER BY id")->fetchAll() as $b) $bmap[$b['cluster_id']][] = $b['name'];
    } catch (PDOException $x) {
    }
    foreach (db()->query("SELECT * FROM clusters ORDER BY id")->fetchAll() as $c) {
        [$dep, $q, $next] = split_cluster($rows, $c['id']);
        echo "<section class='cluster'><h2>" . e($c['name']) . "</h2>";
        if (!empty($bmap[$c['id']])) echo "<div class='brgy'><small>Barangays</small>" .
            implode('', array_map(fn($b) => "<span>" . e($b) . "</span>", $bmap[$c['id']])) . "</div>";
        if (!$dep && !$q) echo travel_suspended()
            ? "<div class='mb empty susp'><b>TRAVEL SUSPENDED</b><p>No trips until the Coast Guard lifts the suspension.</p></div>"
            : "<div class='mb empty'><p class='muted'>No motorbanca on queue right now.</p></div>";
        $pos = 0;
        foreach ($dep as $r) echo mb_card($r, ++$pos, 'DEPARTED');
        // only the ON QUEUE boat gets a card; the next-trip boat is shown in the footer below
        foreach ($q as $i => $r) if (tag_for((bool)$dep, $i) === 'ON QUEUE') echo mb_card($r, ++$pos, 'ON QUEUE');
        if (!$dep && $q && (!empty($q[0]['etd']) || $admin)) {   // bottom-right, above the footer line: expected departure, then the Depart button
            echo "<div class='etd-row'>" . (!empty($q[0]['etd']) ? "<div class='etd'>Expected departure <b>" . e($q[0]['etd']) . "</b></div>" : '');
            if ($admin) echo depart_form($q[0]['id'], $q[0]['name']);   // PCG only
            echo "</div>";
        }
        if (!$dep && !$q && travel_suspended()) {
            echo "<div class='cfoot' style='visibility:hidden'><div class='next no'>&nbsp;</div></div></section>";
            continue;
        }   // suspended: no next trip, footer kept blank so the card height stays the same
        echo "<div class='cfoot'>" . ($next
            ? "<div class='next yes'>⏭ Next Trip: <b>" . e($next['name']) . "</b></div>"
            : "<div class='next no'>⛔ No Next Trip Available</div>");
        echo "</div></section>";
    }
}
function stats_html()
{
    $q = fn($s) => (int)db()->query($s)->fetchColumn();
    $rows = live_rows();
    $queuedCount = 0;   // boats ON QUEUE (the one boarding in each cluster). The NEXT TRIP and WAITING boats are not counted here.
    $nextCount = 0;     // boats tagged NEXT TRIP (one per cluster at most)
    foreach (db()->query("SELECT id FROM clusters")->fetchAll() as $c) {
        [$dep, $qd, $next] = split_cluster($rows, $c['id']);
        if (!$dep && $qd) $queuedCount++;
        if ($next) $nextCount++;
    }
    // Passengers are counted on the day they were added (operator input or QR scan), so the number moves live, before the boat departs.
    $paxSql = "SELECT COUNT(*) FROM passengers p JOIN trips t ON t.id=p.trip_id WHERE t.status<>'Cancelled' AND DATE(p.created_at)=";
    $today = $q($paxSql . "CURDATE()");
    $yday = $q($paxSql . "CURDATE() - INTERVAL 1 DAY");
    if ($yday > 0) {
        $d = round(($today - $yday) / $yday * 100);
        $delta = "<b class='" . ($d >= 0 ? 'up' : 'down') . "'>" . ($d >= 0 ? '+' : '') . "$d%</b> than yesterday";
    } else $delta = $today > 0 ? "<b class='up'>+$today</b> than yesterday" : 'no passengers yet';
    $cards = [
        ['Currently Queued', $queuedCount, 'on queue, waiting to depart'],
        ['Currently Departed', $q("SELECT COUNT(*) FROM trips WHERE status='Departed' AND DATE(departure_time)=CURDATE()"), 'left the port today'],
        ['Next Trips', $nextCount, 'clusters with a next boat'],
        ['Passengers Today', $today, $delta],
    ];
    $icons = array_slice(array_values(STAT_ICONS), 2);   // icons for the four cards
    foreach ($cards as $i => [$label, $v, $foot])
        echo "<div class='stat'>" . ico($icons[$i]) . "<span>" . e($label) . "</span><b>$v</b><p>$foot</p></div>";
}
// A boat that has departed can start a new trip only after 1 hour. Returns the time it may start again, or null if it can now.
const TRIP_GAP = '1 HOUR';
function next_trip_at($mid)
{
    $q = db()->prepare("SELECT DATE_ADD(MAX(departure_time), INTERVAL " . TRIP_GAP . ") FROM trips WHERE motorbanca_id=? AND status='Departed'");
    $q->execute([$mid]);
    $at = $q->fetchColumn();
    if (!$at) return null;
    $n = db()->query("SELECT NOW()")->fetchColumn();
    return strtotime($at) > strtotime($n) ? date('g:i A', strtotime($at)) : null;
}
function my_mb()
{
    $s = db()->prepare("SELECT m.*, c.name cluster, " . PAX_SQL . " pax FROM motorbancas m
                        JOIN clusters c ON c.id=m.cluster_id WHERE m.id=?");
    $s->execute([$_SESSION['mid'] ?? 0]);
    return $s->fetch();
}
// Crew list (filled in by the operator before the boat can join the queue). One row per trip.
// The table is created on first use, so no manual SQL import is needed.
function crew_table()
{
    static $done = false;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS trip_crew (
        trip_id INT PRIMARY KEY, address VARCHAR(160) NOT NULL, contact VARCHAR(30) NOT NULL, crew TEXT NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE) ENGINE=InnoDB");
    } catch (PDOException $x) {
    }
}
function trip_crew_get($tid)
{
    crew_table();
    $s = db()->prepare("SELECT * FROM trip_crew WHERE trip_id=?");
    $s->execute([(int)$tid]);
    $r = $s->fetch();
    if (!$r) return null;
    $r['crew'] = json_decode($r['crew'], true) ?: [];
    return $r;
}
// The boat's most recent crew list, used to pre-fill the form so the operator does not retype it every trip.
function crew_last($mid)
{
    crew_table();
    $s = db()->prepare("SELECT c.* FROM trip_crew c JOIN trips t ON t.id=c.trip_id WHERE t.motorbanca_id=? ORDER BY c.trip_id DESC LIMIT 1");
    $s->execute([(int)$mid]);
    $r = $s->fetch();
    if (!$r) return null;
    $r['crew'] = json_decode($r['crew'], true) ?: [];
    return $r;
}
// Operator: the boat's own state as a coloured label (same look as the admin cards).
function op_tag($m)
{
    $tag = $m['status'] === 'Departed' ? 'DEPARTED' : ($m['status'] === 'Inspection' ? 'FOR INSPECTION' : 'NOT QUEUED');
    $pos = null;
    if ($m['status'] === 'Queued') {
        [$dep, $q] = split_cluster(live_rows(), $m['cluster_id']);
        foreach ($q as $i => $r) if ($r['id'] == $m['id']) {
            $tag = tag_for((bool)$dep, $i);
            $pos = $i + ($dep ? 1 : 0);
        }
    }
    $cls = $tag === 'ON QUEUE' ? 'g' : ($tag === 'DEPARTED' ? 'b' : (in_array($tag, ['NEXT TRIP', 'WAITING', 'FOR INSPECTION'], true) ? 'o' : 'n'));
    return [$tag, $cls, $pos];
}
function op_card($m)
{
    [$tag, $cls, $pos] = op_tag($m);
    echo "<div class='optag $cls'>$tag</div>" . count_html($m['pax'], $m['capacity']) .
        "<p class='muted'>passengers on board</p>";
    if (($pos !== null && $pos > 0) || $tag === 'FOR INSPECTION') echo "<p class='muted' style='margin-top:10px'>You can add passengers once you are ON QUEUE.</p>";
}
// The "Inspection" status is added to the two status columns the first time a boat needs it (no manual SQL).
function inspection_schema()
{
    static $done = false;
    if ($done) return;
    $done = true;
    foreach (['motorbancas', 'trips'] as $t) {
        $c = db()->query("SHOW COLUMNS FROM $t LIKE 'status'")->fetch();
        if ($c && stripos($c['Type'], "'Inspection'") === false)
            db()->exec("ALTER TABLE $t MODIFY status " . substr($c['Type'], 0, -1) . ",'Inspection') NOT NULL DEFAULT '" . $c['Default'] . "'");
    }
}
// The ONLY place a status changes: keeps motorbanca + trip in sync.
function set_status($mid, $st)
{
    if (($st === 'Inspection' || $st === 'Queued') && travel_suspended()) return;   // no new trips while travel is suspended
    if ($st === 'Inspection') inspection_schema();   // ALTER TABLE commits implicitly, so it runs before the transaction
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare("UPDATE motorbancas SET status=?, queued_at=IF(?='Queued',NOW(),queued_at) WHERE id=?")->execute([$st, $st, $mid]);
    $new = "INSERT INTO trips(motorbanca_id,cluster_id,status,trip_date%s) SELECT id,cluster_id,'%s',CURDATE()%s FROM motorbancas WHERE id=?";
    if ($st === 'Inspection') {        // New trip: the boat waits for the PCG inspection (no queued time yet)
        $x = $pdo->prepare("SELECT 1 FROM trips WHERE motorbanca_id=? AND status IN ('Inspection','Queued')");
        $x->execute([$mid]);
        if (!$x->fetch()) $pdo->prepare(sprintf($new, '', 'Inspection', ''))->execute([$mid]);
    } elseif ($st === 'Not Queued') {  // inspection rejected: the waiting trip is cancelled
        $pdo->prepare("UPDATE trips SET status='Cancelled' WHERE motorbanca_id=? AND status='Inspection'")->execute([$mid]);
    } elseif ($st === 'Queued') {      // PCG approved the inspection (or joining directly): the boat joins the queue
        $x = $pdo->prepare("SELECT id FROM trips WHERE motorbanca_id=? AND status='Queued'");
        $x->execute([$mid]);
        $ins = $pdo->prepare("SELECT id FROM trips WHERE motorbanca_id=? AND status='Inspection' ORDER BY id DESC LIMIT 1");
        $ins->execute([$mid]);
        $iid = $ins->fetchColumn();
        if ($iid && !$x->fetch()) {
            $o = $pdo->prepare("SELECT COUNT(*) FROM motorbancas WHERE cluster_id=(SELECT cluster_id FROM motorbancas WHERE id=?) AND status='Queued' AND id<>?");
            $o->execute([$mid, $mid]);
            $first = (int)$o->fetchColumn() === 0;   // the queued time is stamped only for the first boat in line
            $pdo->prepare("UPDATE trips SET status='Queued', start_time=" . ($first ? 'NOW()' : 'NULL') . " WHERE id=?")->execute([$iid]);
        } elseif (!$iid && !$x->fetch()) {
            // The queued time is recorded only for the first boat in line. A boat that joins behind another
            // queued boat (NEXT TRIP / WAITING) gets its time when the boat ahead of it departs.
            $o = $pdo->prepare("SELECT COUNT(*) FROM motorbancas WHERE cluster_id=(SELECT cluster_id FROM motorbancas WHERE id=?) AND status='Queued' AND id<>?");
            $o->execute([$mid, $mid]);
            if ((int)$o->fetchColumn() === 0) $pdo->prepare(sprintf($new, ',start_time', 'Queued', ',NOW()'))->execute([$mid]);
            else $pdo->prepare(sprintf($new, '', 'Queued', ''))->execute([$mid]);
        }
    } elseif ($st === 'Departed') {
        $u = $pdo->prepare("UPDATE trips SET status='Departed', departure_time=NOW() WHERE motorbanca_id=? AND status='Queued'");
        $u->execute([$mid]);
        if (!$u->rowCount()) $pdo->prepare(sprintf($new, ',departure_time', 'Departed', ',NOW()'))->execute([$mid]);
        // the boat now first in line (the next trip) gets its queued time
        $f = $pdo->prepare("SELECT m.id FROM motorbancas m WHERE m.cluster_id=(SELECT cluster_id FROM motorbancas WHERE id=?) AND m.status='Queued' ORDER BY m.queued_at, m.id LIMIT 1");
        $f->execute([$mid]);
        if ($nid = $f->fetchColumn())
            $pdo->prepare("UPDATE trips SET start_time=NOW() WHERE motorbanca_id=? AND status='Queued' AND start_time IS NULL")->execute([$nid]);
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
    return sprintf("<p style='margin:.3em 0;color:var(--cdc,#12468f);font-weight:600'>⏱ Leaving the port in <b class='cd' data-s='%d'>%d:%02d</b></p>", $s, intdiv($s, 60), $s % 60);
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
    return "<article class='mb'><div class='mb-top'><div style='flex:1;min-width:0'><h3>" . e($r['name']) . "</h3>" .
        ($tag ? "<div style='margin-top:1px;line-height:1.2;font-size:.72rem;font-weight:800;letter-spacing:.06em;color:" . ($tag === 'DEPARTED' ? 'var(--depc,#12468f)' : 'var(--tagc,#15803d)') . "'>$tag</div>" : '') .
        "</div></div>" .
        ($wait ? "<p class='muted' style='font-size:1rem'>✅ Available · capacity " . (int)$r['capacity'] . "</p>"
            : count_html($r['pax'], $r['capacity']) . "<p class='muted'>passengers on board</p>") .
        ($r['status'] === 'Departed' ? cd_html($r['left_s']) : '') .
        "</article>";
}
// The one boat in a cluster that is ON QUEUE: it is the only one that takes passengers and can be departed.
function on_queue_id($cid)
{
    [$dep, $q] = split_cluster(live_rows(), $cid);
    return (!$dep && $q) ? $q[0]['id'] : null;
}
// Depart button (bottom-right of the ON QUEUE card). Only shown on admin pages; admin/depart.php re-checks everything.
function depart_form($id, $name = '')
{
    return "<form method='post' action='" . BASE . "/admin/depart.php' style='margin-left:auto' data-name='" . e($name) . "'>" .
        "<input type='hidden' name='mid' value='" . (int)$id . "'>" .
        "<button class='depart'>Depart<span>" .
        ico('<path d="M7 17L17 7M8 7h9v9"/>') . "</span></button></form>";
}
