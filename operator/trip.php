<?php
// "New trip" goes through the crew list first (operator/crew.php). Only the PCG can depart a boat.
require_once '../config/live.php';
need('operator');
$m = my_mb();
if ($m && in_array($m['status'], ['Not Queued', 'Departed'], true)) {
    header('Location: crew.php');
    exit;
}
header('Location: ' . ($m ? 'manifest.php' : 'dashboard.php'));
