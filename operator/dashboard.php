<?php
require_once '../config/layout.php';
need('operator');
$m = my_mb();
if (!$m) {
  header('Location: ../logout.php');
  exit;
}
$idle = in_array($m['status'], ['Not Queued', 'Departed'], true);
$wait = $idle ? next_trip_at($m['id']) : null;   // a departed boat waits 1 hour before a new trip
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
    <h3><?= $idle ? 'Ready for a trip?' : 'Passenger list' ?></h3>
    <p class="muted" style="margin:0 0 16px"><?= $idle ? ($wait ? 'Your last trip has departed.' : 'Join the queue to start a new trip.') : 'Add and view the passengers on your trip.' ?></p>
    <?php if ($wait): ?>
      <span class="btn big" style="width:100%;opacity:.5;cursor:not-allowed" aria-disabled="true">New trip</span>
      <div class="alert" style="margin:16px 0 0">Your boat has departed. You can start a new trip at <b><?= e($wait) ?></b>.</div>
    <?php else: ?>
      <a class="btn big" style="width:100%" href="<?= $idle ? 'crew.php' : 'manifest.php' ?>"><?= $idle ? 'New trip' : 'Open passenger list' ?></a>
    <?php endif; ?>
    <?php if ($m['status'] === 'Inspection'): ?>
      <div class="alert ok" style="margin:16px 0 0">Waiting for the PCG inspection. Your boat goes on the queue once it is approved.</div>
    <?php endif; ?>
    <?php if ($m['status'] === 'Queued'):
      $etd = null;
      foreach (live_rows() as $r) if ($r['id'] == $m['id']) $etd = $r['etd'] ?? null; ?>
      <div class="alert ok" style="margin:16px 0 0">Your boat is on the queue<?= $etd ? ' until <b>' . e($etd) . '</b>' : '' ?>.</div>
    <?php endif; ?>
  </section>
</div>
<?php page_foot('operator');
