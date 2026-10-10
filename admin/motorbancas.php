<?php
require_once '../config/layout.php';
need('admin');
$msg = $err = '';
$clusters = db()->query("SELECT * FROM clusters ORDER BY id")->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rest = trim(preg_replace('#^\s*M\s*/\s*B\s*[-.:]?\s*#i', '', trim($_POST['name'] ?? '')));   // the M/B prefix is fixed
    $name = 'M/B ' . $rest;
    $owner = trim($_POST['owner'] ?? '');
    $cap = (int)($_POST['capacity'] ?? 0);
    $cid = (int)($_POST['cluster'] ?? 0);
    $ok = db()->prepare("SELECT 1 FROM clusters WHERE id=?");
    $ok->execute([$cid]);
    if ($rest === '' || strlen($rest) > 45 || $owner === '' || strlen($owner) > 100 || $cap < 1 || $cap > 500 || !$ok->fetch()) {
        $err = 'Please fill in every field. Capacity must be 1 to 500.';
    } else {
        try {
            $base = preg_replace('/[^A-Z0-9]/', '', strtoupper($rest)) ?: 'MB';
            $user = strtolower($base);   // username = the boat name in lowercase, e.g. M/B Ruiz -> ruiz
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
$list = db()->query("SELECT m.*, c.name cluster, u.username, u.temp_password FROM motorbancas m
    JOIN clusters c ON c.id=m.cluster_id LEFT JOIN users u ON u.motorbanca_id=m.id AND u.role='operator'
    ORDER BY m.id DESC")->fetchAll();
page_head('Register', 'admin');
?>
<h1 style="margin-bottom:18px">Registered motorbanca</h1>
<?php if ($msg) echo "<div class='alert ok'>$msg<br><small>Give these to the operator. You can see them anytime in the list.</small></div>";
if ($err) echo "<div class='alert bad'>" . e($err) . "</div>"; ?>
<div class="reg">
    <div class="tablewrap">
        <table class="regtbl">
            <tr>
                <th>Name</th>
                <th>Owner</th>
                <th>Capacity</th>
                <th>Cluster</th>
                <th>Username</th>
                <th>Password</th>
            </tr>
            <?php foreach ($list as $m) echo "<tr class='mb-row' data-hint='Double-click to edit' data-id='{$m['id']}' data-name='" . e(preg_replace('#^M/B\s*#', '', $m['motorbanca_name'])) .
                "' data-owner='" . e($m['owner_name'] === 'Not set' ? '' : $m['owner_name']) . "' data-cap='{$m['capacity']}' data-cluster='{$m['cluster_id']}' data-status='" . e($m['status']) . "'><td>" . e($m['motorbanca_name']) . "</td><td>" . e($m['owner_name']) .
                "</td><td>{$m['capacity']}</td><td>" . e($m['cluster']) . "</td><td><code>" . e($m['username']) .
                "</code></td><td><code>" . e($m['temp_password']) . "</code></td></tr>"; ?>
        </table>
    </div>
    <div class="card">
        <h3>New registration</h3>
        <form method="post" class="grid">
            <label>Motorbanca name<div class="pfx"><span>M/B</span><input name="name" placeholder="Ruiz" maxlength="45" required></div></label>
            <label>Owner name<input name="owner" placeholder="Juan Dela Cruz" maxlength="100" required></label>
            <label>Capacity<input name="capacity" type="number" min="1" max="500" placeholder="45" required></label>
            <label>Cluster<select name="cluster"><?php foreach ($clusters as $c) echo "<option value='{$c['id']}'>" . e($c['name']) . "</option>"; ?></select></label>
            <button class="btn">Register and create login</button>
        </form>
    </div>
</div>

<!-- edit modal: opens when a row in the list is double-clicked -->
<div class="mdl" id="edit-mdl" role="dialog" aria-modal="true">
    <div class="mdl-box wide">
        <div class="mdl-head">
            <div>
                <h3>Edit motorbanca</h3>
                <p class="sub" id="edit-sub"></p>
            </div><button type="button" class="x" aria-label="Close">&times;</button>
        </div>
        <form class="mdl-form grid" id="edit-form" data-url="<?= BASE ?>/admin/update_motorbanca.php">
            <input type="hidden" name="mid">
            <label>Motorbanca name<div class="pfx"><span>M/B</span><input name="name" maxlength="45" required></div></label>
            <label>Owner name<input name="owner" maxlength="100" placeholder="Juan Dela Cruz" required></label>
            <label>Capacity<input name="capacity" type="number" min="1" max="500" required></label>
            <label>Cluster<select name="cluster"><?php foreach ($clusters as $c) echo "<option value='{$c['id']}'>" . e($c['name']) . "</option>"; ?></select></label>
            <div class="alert bad" id="edit-err" hidden></div>
            <div class="mdl-act"><button type="button" class="no">Cancel</button><button class="go">Save changes</button></div>
        </form>
    </div>
</div>
<?php page_foot('admin');
