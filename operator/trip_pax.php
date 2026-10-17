<?php
// Operator: the passenger list of one past trip of this boat (shown in the Trip History popup).
require_once '../config/pdic_helpers.php';
need('operator');
$m = my_mb();
$id = (int)($_GET['trip'] ?? 0);
$t = db()->prepare("SELECT t.trip_date, m.motorbanca_name n, m.capacity cap, c.name cname FROM trips t
    JOIN motorbancas m ON m.id=t.motorbanca_id JOIN clusters c ON c.id=t.cluster_id
    WHERE t.id=? AND t.motorbanca_id=? AND t.status NOT IN ('Cancelled','Inspection')");
$t->execute([$id, $m['id'] ?? 0]);
$trip = $t->fetch();
if (!$m || !$trip) {
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
    'pdic' => (bool)pdic_get($id),   // and a PDIC
    'html' => $html
]);
