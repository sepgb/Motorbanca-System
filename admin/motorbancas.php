<?php
require_once '../config/layout.php'; need('admin');
$msg = $err = '';
$clusters = db()->query("SELECT * FROM clusters ORDER BY id")->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? ''); $owner = trim($_POST['owner'] ?? '');
    $cap = (int)($_POST['capacity'] ?? 0); $cid = (int)($_POST['cluster'] ?? 0);
    $ok = db()->prepare("SELECT 1 FROM clusters WHERE id=?"); $ok->execute([$cid]);
    if ($name === '' || strlen($name) > 50 || $owner === '' || strlen($owner) > 100 || $cap < 1 || $cap > 500 || !$ok->fetch()) {
        $err = 'Please fill in every field. Capacity must be 1 to 500.';
    } else {
        try {
            $base = preg_replace('/[^A-Z0-9]/', '', strtoupper($name)) ?: 'MB';
            $user = $base . '_operator';
            $pass = $base . '@' . random_int(1000, 9999);
            db()->beginTransaction();
            db()->prepare("INSERT INTO motorbancas(motorbanca_name,owner_name,capacity,cluster_id) VALUES(?,?,?,?)")
                ->execute([$name, $owner, $cap, $cid]);
            $id = db()->lastInsertId();
            db()->prepare("INSERT INTO users(username,password,temp_password,role,motorbanca_id) VALUES(?,?,?,'operator',?)")
                ->execute([$user, password_hash($pass, PASSWORD_DEFAULT), $pass, $id]);
            db()->commit();
            $msg = "<b>" . e($name) . "</b> registered.<br>Username: <code>" . e($user) . "</code><br>Password: <code>" . e($pass) . "</code>";
        } catch (PDOException $x) {
            if (db()->inTransaction()) db()->rollBack();
            $err = $x->getCode() == 23000 ? 'That motorbanca name (or its username) already exists.' : 'Could not save. Try again.';
        }
    }
}
$list = db()->query("SELECT m.*, c.name cluster FROM motorbancas m JOIN clusters c ON c.id=m.cluster_id ORDER BY m.id DESC")->fetchAll();
page_head('Motorbancas', 'admin');
?>
<h1>Motorbancas</h1>
<?php if ($msg) echo "<div class='alert ok'>$msg<br><small>Give these to the operator. You can view them later under Operators.</small></div>";
      if ($err) echo "<div class='alert bad'>" . e($err) . "</div>"; ?>
<div class="card">
  <h2>Register a motorbanca</h2>
  <form method="post" class="grid cols">
    <label>Motorbanca name<input name="name" placeholder="MB-01" maxlength="50" required></label>
    <label>Owner name<input name="owner" placeholder="Juan Dela Cruz" maxlength="100" required></label>
    <label>Capacity<input name="capacity" type="number" min="1" max="500" placeholder="45" required></label>
    <label>Cluster<select name="cluster"><?php foreach ($clusters as $c) echo "<option value='{$c['id']}'>" . e($c['name']) . "</option>"; ?></select></label>
    <button class="btn">Register and create login</button>
  </form>
</div>
<div class="tablewrap"><table>
  <tr><th>Name</th><th>Owner</th><th>Capacity</th><th>Cluster</th><th>Status</th></tr>
  <?php foreach ($list as $m) echo "<tr><td>" . e($m['motorbanca_name']) . "</td><td>" . e($m['owner_name']) . "</td><td>{$m['capacity']}</td><td>" .
        e($m['cluster']) . "</td><td>" . badge($m['status']) . "</td></tr>"; ?>
</table></div>
<?php page_foot('admin');
