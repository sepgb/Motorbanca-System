<?php
require_once '../config/layout.php';
need('operator');
$m = my_mb();
if (!$m) {
  header('Location: ../logout.php');
  exit;
}
$tq = db()->prepare("SELECT id FROM trips WHERE motorbanca_id=? AND status<>'Cancelled' ORDER BY id DESC LIMIT 1");
$tq->execute([$m['id']]);
$trip = $tq->fetchColumn();
$pos = null;
$tag = '';
if ($m['status'] === 'Queued') {   // 0 = ON QUEUE, 1 = NEXT TRIP, 2+ = WAITING
  [$dep, $q] = split_cluster(live_rows(), $m['cluster_id']);
  foreach ($q as $i => $r) if ($r['id'] == $m['id']) {
    $pos = $i + ($dep ? 1 : 0);
    $tag = tag_for((bool)$dep, $i);
  }
}
$open = $trip && $m['status'] === 'Queued' && $pos === 0;
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $open) {
  $name = trim($_POST['name'] ?? '');
  $age = $_POST['age'] ?? '';
  $brgy = trim($_POST['barangay'] ?? '');
  if (strlen($name) < 2 || strlen($name) > 80 || !ctype_digit((string)$age) || $age > 120 || strlen($brgy) < 2 || strlen($brgy) > 60) {
    $err = 'Enter a name, an age (0 to 120) and a barangay.';
  } else {
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare("SELECT id FROM motorbancas WHERE id=? FOR UPDATE")->execute([$m['id']]);   // blocks double-adds
    $c = $pdo->prepare("SELECT COUNT(*) FROM passengers WHERE trip_id=?");
    $c->execute([$trip]);
    if ($c->fetchColumn() >= $m['capacity']) {
      $pdo->rollBack();
      $err = 'Boat is FULL. No more passengers can be added.';
    } else {
      $pdo->prepare("INSERT INTO passengers(trip_id,name,age,barangay) VALUES(?,?,?,?)")->execute([$trip, $name, (int)$age, $brgy]);
      $pdo->commit();
      header('Location: manifest.php');
      exit;
    }
  }
  $m = my_mb();
}
$pax = $trip ? db()->query("SELECT * FROM passengers WHERE trip_id=" . (int)$trip . " ORDER BY id")->fetchAll() : [];
$full = $m['pax'] >= $m['capacity'];
page_head('Passengers', 'operator');
?>
<div class="card hero">
  <h1><?= e($m['motorbanca_name']) ?> <?= badge($m['status']) ?></h1><?php echo count_html($m['pax'], $m['capacity']); ?>
</div>
<?php if ($err) echo "<div class='alert bad'>" . e($err) . "</div>"; ?>
<?php if (!$trip): ?>
  <div class="alert bad">No active trip. Go Home and tap NEW TRIP.</div>
<?php elseif (!$open): ?>
  <div class="alert"><?= $m['status'] === 'Queued'
                        ? '⏳ Your boat is <b>' . e($tag) . '</b>. You can add passengers once your boat is ON QUEUE.'
                        : 'This trip has departed. Go Home and tap NEW TRIP for the next one.' ?></div>
<?php elseif ($full): ?>
  <div class="alert bad"><b>FULL</b> — no more passengers can be added.</div>
<?php else: ?>
  <form method="post" class="card grid">
    <label>Passenger name<input name="name" maxlength="80" autocomplete="off" required></label>
    <label>Age<input name="age" type="number" inputmode="numeric" min="0" max="120" required></label>
    <label>Barangay<input name="barangay" maxlength="60" autocomplete="off" required></label>
    <button class="btn">+ Add passenger</button>
  </form>
<?php endif; ?>
<div class="tablewrap">
  <table>
    <tr>
      <th>No.</th>
      <th>Name</th>
      <th>Age</th>
      <th>Barangay</th>
    </tr>
    <?php foreach ($pax as $i => $p) echo "<tr><td>" . ($i + 1) . "</td><td>" . e($p['name']) . "</td><td>{$p['age']}</td><td>" . e($p['barangay']) . "</td></tr>"; ?>
  </table>
</div>
<?php if (!$pax) echo "<p class='muted'>No passengers yet.</p>";
page_foot('operator');
