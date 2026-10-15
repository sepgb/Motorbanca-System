<?php
// PCG only: the passenger list of one trip (shown in the Trip history modal).
require_once '../config/mdsd_helpers.php';
need('admin');
$id = (int)($_GET['trip'] ?? 0);
$t = db()->prepare("SELECT t.trip_date, m.motorbanca_name n, m.capacity cap, c.name cname FROM trips t
    JOIN motorbancas m ON m.id=t.motorbanca_id JOIN clusters c ON c.id=t.cluster_id WHERE t.id=?");
$t->execute([$id]);
$trip = $t->fetch();
if (!$trip) {
    http_response_code(404);
    exit;
}
$p = db()->prepare("SELECT name, age, barangay FROM passengers WHERE trip_id=? ORDER BY id");
$p->execute([$id]);
$rows = $p->fetchAll();
if ($rows) {
    $html = "<table><tr><th>No.</th><th>Name</th><th>Age</th><th>Barangay</th></tr>";
    foreach ($rows as $i => $r) $html .= "<tr><td>" . ($i + 1) . "</td><td>" . e($r['name']) . "</td><td>" . (int)$r['age'] . "</td><td>" . e($r['barangay']) . "</td></tr>";
    $html .= "</table>";
} else $html = "<p class='none'>No passengers were added to this trip.</p>";
header('Content-Type: application/json');
echo json_encode([
    'title' => $trip['n'] . ' · ' . $trip['cname'],
    'sub' => $trip['trip_date'] . ' · ' . count($rows) . ' / ' . (int)$trip['cap'] . ' passengers',
    'mdsd' => (bool)mdsd_get($id),   // an MDSD was made when this trip departed
    'html' => $html
]);
