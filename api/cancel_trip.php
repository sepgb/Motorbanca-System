<?php
require_once '../config/live.php';
require_once '../config/profile.php';
require_once '../config/qr.php';
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['ok' => false, 'error' => 'Not allowed.']);
    exit;
}
process_departures();
qr_ensure();
mb_schema();
$d = db()->prepare("DELETE p FROM passengers p
    JOIN trips t ON t.id=p.trip_id AND t.status='Queued'
    JOIN motorbancas m ON m.id=t.motorbanca_id AND m.status='Queued' AND m.depart_req IS NULL
    WHERE p.device_token=?");
$d->execute([pax_device()]);
if (!$d->rowCount()) {
    echo json_encode(['ok' => false, 'error' => 'You are not on a boat that can still be cancelled. It may have already left.']);
    exit;
}
echo json_encode(['ok' => true]);
