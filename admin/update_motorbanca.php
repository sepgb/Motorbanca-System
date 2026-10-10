<?php
// PCG only: save changes to a registered motorbanca (called by the edit modal on the Register page).
require_once '../config/live.php';
need('admin');
header('Content-Type: application/json');
function out($a)
{
    echo json_encode($a);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'Invalid request.']);

$id = (int)($_POST['mid'] ?? 0);
$rest = trim(preg_replace('#^\s*M\s*/\s*B\s*[-.:]?\s*#i', '', trim($_POST['name'] ?? '')));   // the M/B prefix is fixed
$owner = trim($_POST['owner'] ?? '');
$cap = (int)($_POST['capacity'] ?? 0);
$cid = (int)($_POST['cluster'] ?? 0);
if ($rest === '' || strlen($rest) > 45 || $owner === '' || strlen($owner) > 100 || $cap < 1 || $cap > 500)
    out(['error' => 'Please fill in every field. Capacity must be 1 to 500.']);

$s = db()->prepare("SELECT m.status, m.cluster_id, " . PAX_SQL . " pax FROM motorbancas m WHERE m.id=?");
$s->execute([$id]);
$cur = $s->fetch();
if (!$cur) out(['error' => 'Motorbanca not found.']);
if ($cap < (int)$cur['pax']) out(['error' => 'This boat already has ' . (int)$cur['pax'] . ' passengers on its trip, so capacity cannot be lower than that.']);

if ($cur['status'] === 'Not Queued') {        // the cluster can only change while the boat is not on the queue
    $ok = db()->prepare("SELECT 1 FROM clusters WHERE id=?");
    $ok->execute([$cid]);
    if (!$ok->fetch()) out(['error' => 'Please choose a cluster.']);
} else $cid = (int)$cur['cluster_id'];

try {
    db()->prepare("UPDATE motorbancas SET motorbanca_name=?, owner_name=?, capacity=?, cluster_id=? WHERE id=?")
        ->execute(['M/B ' . $rest, $owner, $cap, $cid, $id]);
} catch (PDOException $x) {
    out(['error' => $x->getCode() == 23000 ? 'That motorbanca name already exists.' : 'Could not save. Try again.']);
}
out(['ok' => true]);
