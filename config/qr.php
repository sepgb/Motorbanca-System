<?php
// Per-trip QR code: every trip gets its own random code. Passengers scan it (or type the code) and their saved
// Profile is added to that trip's manifest. Columns are added automatically the first time they are needed.
require_once __DIR__ . '/database.php';

function qr_ensure()
{
    static $d;
    if ($d) return;
    $d = true;
    try {
        if (!db()->query("SHOW COLUMNS FROM trips LIKE 'qr_token'")->fetch()) db()->exec("ALTER TABLE trips ADD COLUMN qr_token VARCHAR(16) NULL");
        if (!db()->query("SHOW COLUMNS FROM passengers LIKE 'device_token'")->fetch()) db()->exec("ALTER TABLE passengers ADD COLUMN device_token VARCHAR(32) NULL");
    } catch (PDOException $x) {
    }
}
function trip_qr_token($tid)
{
    qr_ensure();
    $q = db()->prepare("SELECT qr_token FROM trips WHERE id=?");
    $q->execute([$tid]);
    $t = $q->fetchColumn();
    if (!$t) {
        $t = bin2hex(random_bytes(8));
        db()->prepare("UPDATE trips SET qr_token=? WHERE id=?")->execute([$t, $tid]);
    }
    return $t;
}
function trip_join_url($tok)
{
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['SERVER_PORT'] ?? '') == 443);
    return ($https ? 'https' : 'http') . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . BASE . '/passenger/join.php?t=' . $tok;
}
function qr_trip_by_token($tok)
{
    qr_ensure();
    $q = db()->prepare("SELECT t.id, t.status, t.motorbanca_id, m.motorbanca_name, m.capacity, m.cluster_id, m.status mstatus
                        FROM trips t JOIN motorbancas m ON m.id=t.motorbanca_id WHERE t.qr_token=?");
    $q->execute([$tok]);
    return $q->fetch() ?: null;
}
// Passengers can board only while the trip is queued and the boat is ON QUEUE (same rule as the operator's Add passenger form).
function qr_is_open($t)
{
    return $t && $t['status'] === 'Queued' && $t['mstatus'] === 'Queued' && on_queue_id($t['cluster_id']) == $t['motorbanca_id'];
}
