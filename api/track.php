<?php
// Public, read-only: is this passenger on the manifest of a boat that is on the queue? Answers with the boat and cluster only.
require_once '../config/live.php';
require_once '../config/profile.php';
require_once '../config/qr.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
$in = $_POST ?: (json_decode(file_get_contents('php://input'), true) ?: []);
$name = trim((string)($in['name'] ?? ''));
$age = trim((string)($in['age'] ?? ''));
$brgy = trim((string)($in['barangay'] ?? ''));
if (strlen($name) < 2 || strlen($name) > 80 || !ctype_digit($age) || (int)$age > 120 || strlen($brgy) < 2 || strlen($brgy) > 60) {
    echo json_encode(['ok' => false, 'error' => 'Enter your name, your age and your barangay.']);
    exit;
}
process_departures();
qr_ensure();   // makes sure the passengers.device_token column exists
// only boats that are Queued, and only the passengers on that queued trip
$s = db()->prepare("SELECT m.motorbanca_name boat, c.id cid, c.name cluster, p.device_token dtok
    FROM passengers p JOIN trips t ON t.id=p.trip_id AND t.status='Queued'
    JOIN motorbancas m ON m.id=t.motorbanca_id AND m.status='Queued'
    JOIN clusters c ON c.id=m.cluster_id
    WHERE LOWER(TRIM(p.name))=LOWER(?) AND p.age=? AND LOWER(TRIM(p.barangay))=LOWER(?)
    ORDER BY (p.device_token = ?) DESC LIMIT 1");   // a listing made by scanning on this device comes first
$dev = pax_device();
$s->execute([$name, (int)$age, $brgy, $dev]);
$r = $s->fetch();
if (!$r) {
    echo json_encode(['ok' => true, 'found' => false]);
    exit;
}
$piers = is_file('../config/piers.php') ? (require '../config/piers.php') : [];
$pl = [];
foreach ($piers as $k => $v) $pl[mb_strtolower($k)] = $v;
$b = db()->prepare("SELECT name FROM barangays WHERE cluster_id=? ORDER BY id");
$b->execute([$r['cid']]);
$list = array_map(fn($x) => ['name' => $x['name'], 'pier' => $pl[mb_strtolower($x['name'])] ?? null], $b->fetchAll());
echo json_encode(['ok' => true, 'found' => true, 'boat' => $r['boat'], 'cluster' => $r['cluster'], 'scanned' => !empty($r['dtok']) && hash_equals($r['dtok'], $dev), 'barangays' => $list]);
