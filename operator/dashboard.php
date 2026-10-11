<?php
require_once '../config/layout.php';
need('operator');
$m = my_mb();
if (!$m) {
  header('Location: ../logout.php');
  exit;
}
$idle = in_array($m['status'], ['Not Queued', 'Departed'], true);
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
    <p class="muted" style="margin:0 0 16px"><?= $idle ? 'Join the queue to start a new trip.' : 'Add and view the passengers on your trip.' ?></p>
    <a class="btn big" style="width:100%" href="<?= $idle ? 'crew.php' : 'manifest.php' ?>"><?= $idle ? 'New trip' : 'Open passenger list' ?></a>
    <?php if ($m['status'] === 'Queued'):
      $etd = null;
      foreach (live_rows() as $r) if ($r['id'] == $m['id']) $etd = $r['etd'] ?? null; ?>
      <div class="alert ok" style="margin:16px 0 0">Your boat is on the queue<?= $etd ? ' until <b>' . e($etd) . '</b>' : '' ?>.</div>
    <?php endif; ?>
  </section>
</div>
<?php page_foot('operator');
