<?php
require_once '../config/layout.php';
need('admin');
$S = ['Queued', 'Departed'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $a = $_POST['action'] ?? '';
  if ($a === 'status') {   // the PCG's only action: depart the boat that is ON QUEUE
    $mid = (int)($_POST['mid'] ?? 0);
    $c = db()->prepare("SELECT status, cluster_id FROM motorbancas WHERE id=?");
    $c->execute([$mid]);
    $cur = $c->fetch();
    if ($cur && ($_POST['status'] ?? '') === 'Departed' && $cur['status'] === 'Queued' && on_queue_id($cur['cluster_id']) == $mid) set_status($mid, 'Departed');
  }
  header('Location: queue.php');
  exit;
}
$all = db()->query("SELECT m.id, m.motorbanca_name n, m.status, m.cluster_id, " . PAX_SQL . " pax, m.capacity FROM motorbancas m ORDER BY m.motorbanca_name")->fetchAll();
page_head('Queue / Trips', 'admin');
?>
<h1>Queue / Trips</h1>
<div class="clusters" data-live="<?= BASE ?>/api/live.php?v=passenger"><?php render_live(); ?></div>
<h2 class="sub">Departures</h2>
<p class="lead" style="margin-top:-6px">Operators start the trips. You can only depart the boat that is ON QUEUE.</p>
<?php foreach (db()->query("SELECT * FROM clusters ORDER BY id")->fetchAll() as $c): ?>
  <?php [$dep, $q] = split_cluster(live_rows(), $c['id']);
  $onId = (!$dep && $q) ? $q[0]['id'] : null;
  $tags = [];
  foreach ($q as $i => $r) $tags[$r['id']] = tag_for((bool)$dep, $i); ?>
  <div class="card">
    <h3><?= e($c['name']) ?></h3>
    <?php foreach ($all as $m) if ($m['cluster_id'] == $c['id']): ?>
      <form method="post" class="qrow"><input type="hidden" name="action" value="status"><input type="hidden" name="mid" value="<?= $m['id'] ?>">
        <b><?= e($m['n']) ?></b><small><?= $m['pax'] ?>/<?= $m['capacity'] ?></small>
        <span><?= badge($m['status']) ?></span>
        <?php if ($m['id'] == $onId): ?>
          <input type="hidden" name="status" value="Departed"><button class="btn sm red" onclick="return confirm('Mark this motorbanca as departed?')">Depart</button>
        <?php else: ?><small><?= e($tags[$m['id']] ?? '') ?></small><?php endif; ?>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach;
page_foot('admin');
