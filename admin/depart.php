<?php
require_once '../config/live.php';
need('admin');
$ref = parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?: '';
$back = strpos($ref, BASE . '/admin/') === 0 ? $ref : 'dashboard.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mid = (int)($_POST['mid'] ?? 0);
    mb_schema();
    $c = db()->prepare("SELECT status, cluster_id, depart_req FROM motorbancas WHERE id=?");
    $c->execute([$mid]);
    $cur = $c->fetch();
    if ($cur && $cur['status'] === 'Queued' && on_queue_id($cur['cluster_id']) == $mid && ($cur['depart_req'] || travel_suspended())) {
        header('Location: mdsd.php?mid=' . $mid . '&back=' . urlencode($back));
        exit;
    }
}
header('Location: ' . $back);
