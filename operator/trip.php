<?php
require_once '../config/live.php';
need('operator');
$m = my_mb();
if ($m && $_SERVER['REQUEST_METHOD'] === 'POST' && in_array($m['status'], ['Not Queued', 'Departed'], true) && !travel_suspended() && !next_trip_at($m['id'])) {
    set_status($m['id'], 'Inspection');
    header('Location: manifest.php');
    exit;
}
header('Location: ' . ($m ? 'dashboard.php' : '../logout.php'));