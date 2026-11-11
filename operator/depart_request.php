<?php
require_once '../config/layout.php';
require_once '../config/mdsd_helpers.php';
need('operator');
header('Content-Type: application/json');
function dr_out($a)
{
    echo json_encode($a);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') dr_out(['ok' => false, 'error' => 'Invalid request.']);
$m = my_mb();
if (!$m) dr_out(['ok' => false, 'error' => 'Please log in again.']);
if ($m['status'] !== 'Queued' || on_queue_id($m['cluster_id']) != $m['id']) dr_out(['ok' => false, 'error' => 'Your boat must be ON QUEUE before it can depart.']);
if (!empty($m['depart_req'])) dr_out(['ok' => true]);
$t = db()->prepare("SELECT id FROM trips WHERE motorbanca_id=? AND status='Queued' ORDER BY id DESC LIMIT 1");
$t->execute([$m['id']]);
$tid = (int)$t->fetchColumn();
if (!$tid) dr_out(['ok' => false, 'error' => 'No trip was found for your boat.']);
$q = (array)($_POST['q'] ?? []);
$ans = [];
foreach (mdsd_keys() as $k) {
    $a = $q[$k] ?? '';
    if (in_array($a, ['yes', 'no', 'na'], true)) $ans[$k] = $a;
}
if (count($ans) < count(mdsd_keys())) dr_out(['ok' => false, 'error' => 'Please answer every item of the declaration.']);
$roles = ['BOAT CAPTAIN', 'MOTORMAN', 'CREW', 'CREW'];
$names = (array)($_POST['cname'] ?? []);
$ages = (array)($_POST['cage'] ?? []);
$crew = [];
$bad = '';
foreach ($roles as $i => $role) {
    $n = trim((string)($names[$i] ?? ''));
    $a = trim((string)($ages[$i] ?? ''));
    $crew[] = ['name' => $n, 'age' => $a, 'position' => $role];
    if ($i === 0 && ($n === '' || $a === '')) $bad = 'The boat captain needs a name and an age.';
    elseif ($n !== '' && (strlen($n) < 2 || strlen($n) > 80 || !ctype_digit($a) || (int)$a > 120)) $bad = 'Check the crew names and ages (age 0 to 120).';
    elseif ($n === '' && $a !== '') $bad = 'Enter the name for every age you filled in.';
}
if ($bad) dr_out(['ok' => false, 'error' => $bad]);
if (count(array_filter(array_slice($crew, 2), fn($c) => $c['name'] !== '')) < 1) dr_out(['ok' => false, 'error' => 'Add at least 1 crew member (Crew 3 or 4) besides the boat captain and the motorman.']);
mdsd_save($tid, $ans);
crew_table();
$keep = array_values(array_filter($crew, fn($c) => $c['name'] !== ''));
db()->prepare("REPLACE INTO trip_crew(trip_id,address,contact,crew) VALUES(?,?,?,?)")
    ->execute([$tid, (string)($m['address'] ?? ''), (string)($m['contact'] ?? ''), json_encode($keep, JSON_UNESCAPED_UNICODE)]);
db()->prepare("UPDATE motorbancas SET depart_req=NOW() WHERE id=? AND status='Queued'")->execute([$m['id']]);
dr_out(['ok' => true]);
