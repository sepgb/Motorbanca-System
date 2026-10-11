<?php
require_once '../config/layout.php';
need('admin');
$rows = db()->query("SELECT t.*, m.motorbanca_name n, c.name cname, COUNT(p.id) pax FROM trips t
    JOIN motorbancas m ON m.id=t.motorbanca_id JOIN clusters c ON c.id=t.cluster_id
    LEFT JOIN passengers p ON p.trip_id=t.id WHERE t.status<>'Inspection' GROUP BY t.id ORDER BY t.id DESC LIMIT 200")->fetchAll();
$tm = fn($v) => $v ? date('g:i A', strtotime($v)) : '—';
page_head('Trip History', 'admin');
?>
<h1 style="margin-bottom:18px">Trip history</h1>
<div class="tablewrap">
    <table>
        <tr>
            <th>Date</th>
            <th>Motorbanca</th>
            <th>Cluster</th>
            <th>Passengers</th>
            <th>Queued</th>
            <th>Departed</th>
        </tr>
        <?php foreach ($rows as $t) echo "<tr class='trip-row' data-hint='Double-click to see the passengers' data-name='" . e($t['n']) .
            "' data-url='" . BASE . "/admin/passengers.php?trip=" . (int)$t['id'] . "'><td>" . e($t['trip_date']) . "</td><td>" . e($t['n']) . "</td><td>" .
            e($t['cname']) . "</td><td>{$t['pax']}</td><td>" . $tm($t['start_time']) . "</td><td>" . $tm($t['departure_time']) . "</td></tr>"; ?>
    </table>
</div>
<?php if (!$rows) echo "<p class='muted'>No trips yet.</p>";
page_foot('admin');
