<?php
// PCG only: depart the boat that is ON QUEUE, then go back to the page the button was on.
require_once '../config/live.php';
need('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mid = (int)($_POST['mid'] ?? 0);
    $c = db()->prepare("SELECT status, cluster_id FROM motorbancas WHERE id=?");
    $c->execute([$mid]);
    $cur = $c->fetch();
    if ($cur && $cur['status'] === 'Queued' && on_queue_id($cur['cluster_id']) == $mid) set_status($mid, 'Departed');
}
$ref = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?: '';
header('Location: ' . (strpos($ref, BASE . '/admin/') === 0 ? $ref : 'dashboard.php'));
