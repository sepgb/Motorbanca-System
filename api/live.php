<?php
// Read-only HTML fragments polled by script.js. Passengers can call v=passenger; nothing here writes data.
require_once '../config/live.php';
header('Cache-Control: no-store');
process_departures();
$v = $_GET['v'] ?? 'passenger';
$role = $_SESSION['role'] ?? '';
if ($v === 'stats' && $role === 'admin') stats_html();
elseif ($v === 'fleet' && $role === 'admin') fleet_html();
elseif ($v === 'op' && $role === 'operator') {
    $m = my_mb();
    if ($m) op_card($m);
} elseif ($v === 'admin' && $role === 'admin') render_live(true);
else render_live();
