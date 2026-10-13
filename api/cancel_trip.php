<?php
// Passenger side: someone who joined a boat by scanning its trip QR code can take themselves off that boat's list.
// Only works for a listing made on this same device, and only while the trip is still Queued (not departed).
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
process_departures();   // a boat that has just left can no longer be cancelled
qr_ensure();
$d = db()->prepare("DELETE p FROM passengers p
    JOIN trips t ON t.id=p.trip_id AND t.status='Queued'
    JOIN motorbancas m ON m.id=t.motorbanca_id AND m.status='Queued'
    WHERE p.device_token=?");
$d->execute([pax_device()]);
if (!$d->rowCount()) {
    echo json_encode(['ok' => false, 'error' => 'You are not on a boat that can still be cancelled. It may have already left.']);
    exit;
}
echo json_encode(['ok' => true]);
