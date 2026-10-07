<?php
require_once '../config/layout.php'; need('admin');
$rows = db()->query("SELECT t.*, m.motorbanca_name n, c.name cname, COUNT(p.id) pax FROM trips t
    JOIN motorbancas m ON m.id=t.motorbanca_id JOIN clusters c ON c.id=t.cluster_id
    LEFT JOIN passengers p ON p.trip_id=t.id GROUP BY t.id ORDER BY t.id DESC LIMIT 200")->fetchAll();
page_head('Trip History', 'admin');
?>
<h1>Trip history</h1>
<div class="tablewrap"><table>
  <tr><th>Date</th><th>Motorbanca</th><th>Cluster</th><th>Passengers</th><th>Status</th><th>Boarding</th><th>Departed</th><th>Arrived</th></tr>
  <?php foreach ($rows as $t) echo "<tr><td>{$t['trip_date']}</td><td>" . e($t['n']) . "</td><td>" . e($t['cname']) . "</td><td>{$t['pax']}</td><td>" .
      e($t['status']) . "</td><td>" . e(substr((string)$t['start_time'], 11, 5)) . "</td><td>" . e(substr((string)$t['departure_time'], 11, 5)) .
      "</td><td>" . e(substr((string)$t['arrival_time'], 11, 5)) . "</td></tr>"; ?>
</table></div>
<?php if (!$rows) echo "<p class='muted'>No trips yet.</p>"; page_foot('admin');
