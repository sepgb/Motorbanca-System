<?php
require_once '../config/layout.php';
need('admin');
$S = ['Queued', 'Departed'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $a = $_POST['action'] ?? '';
  if ($a === 'status') {   // only the PCG can depart a boat
    $mid = (int)($_POST['mid'] ?? 0);
    $to = $_POST['status'] ?? '';
    $c = db()->prepare("SELECT status FROM motorbancas WHERE id=?");
    $c->execute([$mid]);
    $cur = $c->fetchColumn();
    if (($to === 'Departed' && $cur === 'Queued') || ($to === 'Queued' && in_array($cur, ['Not Queued', 'Departed'], true))) set_status($mid, $to);
  }
  header('Location: queue.php');
  exit;
}
$all = db()->query("SELECT m.id, m.motorbanca_name n, m.status, m.cluster_id, " . PAX_SQL . " pax, m.capacity FROM motorbancas m ORDER BY m.motorbanca_name")->fetchAll();
page_head('Queue / Trips', 'admin');
?>
<h1>Queue / Trips</h1>
<div class="clusters" data-live="<?= BASE ?>/api/live.php?v=passenger"><?php render_live(); ?></div>
<h2 class="sub">Change status</h2>
<?php foreach (db()->query("SELECT * FROM clusters ORDER BY id")->fetchAll() as $c): ?>
  <div class="card">
    <h3><?= e($c['name']) ?></h3>
    <?php foreach ($all as $m) if ($m['cluster_id'] == $c['id']): ?>
      <form method="post" class="qrow"><input type="hidden" name="action" value="status"><input type="hidden" name="mid" value="<?= $m['id'] ?>">
        <b><?= e($m['n']) ?></b><small><?= $m['pax'] ?>/<?= $m['capacity'] ?></small>
        <span><?= badge($m['status']) ?></span>
        <?php if ($m['status'] === 'Queued'): ?>
          <input type="hidden" name="status" value="Departed"><button class="btn sm red" onclick="return confirm('Mark this motorbanca as departed?')">Depart</button>
        <?php else: ?>
          <input type="hidden" name="status" value="Queued"><button class="btn sm ghost">Add to queue</button>
        <?php endif; ?>
      </form>
    <?php endif; ?>
  </div>
<?php endforeach;
page_foot('admin');
