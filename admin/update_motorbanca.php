<?php
require_once '../config/live.php';
need('admin');
mb_schema();
header('Content-Type: application/json');
function out($a)
{
    echo json_encode($a);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') out(['error' => 'Invalid request.']);

$id = (int)($_POST['mid'] ?? 0);
$rest = trim(preg_replace('#^\s*M\s*/\s*B\s*[-.:]?\s*#i', '', trim($_POST['name'] ?? '')));
$owner = trim($_POST['owner'] ?? '');
$addr = trim($_POST['address'] ?? '');
$con = trim($_POST['contact'] ?? '');
$cap = (int)($_POST['capacity'] ?? 0);
$cid = (int)($_POST['cluster'] ?? 0);
if ($rest === '' || strlen($rest) > 45 || $owner === '' || strlen($owner) > 100 || $cap < 1 || $cap > 500)
    out(['error' => 'Please fill in every field. Capacity must be 1 to 500.']);
if (strlen($addr) < 2 || strlen($addr) > 160) out(['error' => 'Enter the address.']);
if (!preg_match('/^[0-9+\-\s()]{7,25}$/', $con)) out(['error' => 'Enter a valid contact number.']);

$s = db()->prepare("SELECT m.status, m.cluster_id, " . PAX_SQL . " pax FROM motorbancas m WHERE m.id=?");
$s->execute([$id]);
$cur = $s->fetch();
if (!$cur) out(['error' => 'Motorbanca not found.']);
if ($cap < (int)$cur['pax']) out(['error' => 'This boat already has ' . (int)$cur['pax'] . ' passengers on its trip, so capacity cannot be lower than that.']);

if ($cur['status'] === 'Not Queued') {
    $ok = db()->prepare("SELECT 1 FROM clusters WHERE id=?");
    $ok->execute([$cid]);
    if (!$ok->fetch()) out(['error' => 'Please choose a cluster.']);
} else $cid = (int)$cur['cluster_id'];

try {
    db()->prepare("UPDATE motorbancas SET motorbanca_name=?, owner_name=?, address=?, contact=?, capacity=?, cluster_id=? WHERE id=?")
        ->execute(['M/B ' . $rest, $owner, $addr, $con, $cap, $cid, $id]);
} catch (PDOException $x) {
    out(['error' => $x->getCode() == 23000 ? 'That motorbanca name already exists.' : 'Could not save. Try again.']);
}
out(['ok' => true]);
