<?php
require_once '../config/layout.php';
need('operator');
$m = my_mb();
if (!$m) {
  header('Location: ../logout.php');
  exit;
}
$idle = in_array($m['status'], ['Not Queued', 'Departed'], true);
$susp = $idle && travel_suspended();
$wait = $idle && !$susp ? next_trip_at($m['id']) : null;   // a departed boat waits 1 hour before a new trip
page_head('Home', 'operator');
?>
<h1><?= e($m['motorbanca_name']) ?></h1>
<p class="lead"><?= e($m['cluster']) ?> &middot; Owner: <?= e($m['owner_name']) ?></p>
<div class="opgrid">
  <section class="card">
    <h3>Boat status</h3>
    <div data-live="<?= BASE ?>/api/live.php?v=op"><?php op_card($m); ?></div>
  </section>
  <section class="card">
    <h3><?= $susp ? 'Travel suspended' : ($idle ? ($wait ? 'Trip departed' : 'Ready for a trip?') : 'Passenger list') ?></h3>
    <?php if (!$wait && !$susp): ?><p class="muted" style="margin:0 0 16px"><?= $idle ? 'Join the queue to start a new trip.' : 'Add and view the passengers on your trip.' ?></p><?php endif; ?>
    <?php if ($susp): ?>
      <div class="dep-wait big susp"><span><?= $m['status'] === 'Departed' ? 'Your boat has departed. Due to the suspension of travel, you cannot start a new trip yet.' : 'Travel is suspended. You cannot start a new trip yet.' ?></span></div>
      <button type="button" class="btn big" style="width:100%;opacity:.5;cursor:not-allowed;box-shadow:none" disabled>New trip</button>
    <?php elseif ($wait): ?>
      <div class="dep-wait big"><span>Your boat has departed. You can start a new trip at least</span><b><?= e($wait) ?></b></div>
      <button type="button" class="btn big" style="width:100%;opacity:.5;cursor:not-allowed;box-shadow:none" disabled>New trip</button>
    <?php else: ?>
      <a class="btn big" style="width:100%" href="<?= $idle ? 'crew.php' : 'manifest.php' ?>"><?= $idle ? 'New trip' : 'Open passenger list' ?></a>
    <?php endif; ?>
  </section>
</div>
<h2 class="sub"><i class="dot"></i>Live queue</h2>
<div class="clusters" data-live="<?= BASE ?>/api/live.php?v=passenger"><?php render_live(); ?></div>
<?php page_foot('operator');
