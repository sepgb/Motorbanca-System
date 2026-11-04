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
mdsd_save($tid, $ans);
db()->prepare("UPDATE motorbancas SET depart_req=NOW() WHERE id=? AND status='Queued'")->execute([$m['id']]);
dr_out(['ok' => true]);
