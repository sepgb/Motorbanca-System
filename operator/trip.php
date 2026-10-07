<?php
// Operator button: start a new trip (join the queue). Only the PCG can depart a boat.
require_once '../config/live.php';
need('operator');
$m = my_mb();
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $m && ($_POST['action'] ?? '') === 'new') {
    if (in_array($m['status'], ['Not Queued', 'Departed'], true)) set_status($m['id'], 'Queued');   // creates the trip
    header('Location: manifest.php');
    exit;
}
header('Location: dashboard.php');
