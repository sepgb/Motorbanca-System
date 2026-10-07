<?php
require_once '../config/layout.php';
need('operator');
$m = my_mb();
if (!$m) {
  header('Location: ../logout.php');
  exit;
}
$idle = in_array($m['status'], ['Not Queued', 'Departed'], true);
page_head('Operator', 'operator');
?>
<div class="card hero">
  <h1><?= e($m['motorbanca_name']) ?></h1>
  <p><?= e($m['cluster']) ?> · Owner: <?= e($m['owner_name']) ?></p>
  <div data-live="<?= BASE ?>/api/live.php?v=op"><?php op_card($m); ?></div>
</div>
<form method="post" action="trip.php">
  <input type="hidden" name="action" value="new">
  <button class="btn big"><?= $idle ? 'NEW TRIP' : 'OPEN PASSENGER LIST' ?></button>
</form>
<?php if ($m['status'] === 'Queued'): ?>
  <div class="alert">⏳ Your boat is on the queue. The PCG will mark it as departed.</div>
<?php endif;
page_foot('operator');
