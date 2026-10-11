<?php
// PCG only: result of the physical inspection. Approve = the boat joins the queue; Reject = the boat goes back to Not Queued.
require_once '../config/live.php';
need('admin');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mid = (int)($_POST['mid'] ?? 0);
    $c = db()->prepare("SELECT status FROM motorbancas WHERE id=?");
    $c->execute([$mid]);
    if ($c->fetchColumn() === 'Inspection') set_status($mid, ($_POST['do'] ?? '') === 'reject' ? 'Not Queued' : 'Queued');
}
header('Location: queue.php');
