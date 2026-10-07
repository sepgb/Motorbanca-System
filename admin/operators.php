<?php
require_once '../config/layout.php'; need('admin');
$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {   // reset an operator password
    $id = (int)($_POST['uid'] ?? 0);
    $s = db()->prepare("SELECT u.username, m.motorbanca_name n FROM users u JOIN motorbancas m ON m.id=u.motorbanca_id WHERE u.id=?");
    $s->execute([$id]);
    if ($r = $s->fetch()) {
        $pw = (preg_replace('/[^A-Z0-9]/', '', strtoupper($r['n'])) ?: 'MB') . '@' . random_int(1000, 9999);
        db()->prepare("UPDATE users SET password=?, temp_password=? WHERE id=?")->execute([password_hash($pw, PASSWORD_DEFAULT), $pw, $id]);
        $msg = "New password for <code>" . e($r['username']) . "</code>: <code>" . e($pw) . "</code>";
    }
}
$list = db()->query("SELECT u.id, u.username, u.temp_password, m.motorbanca_name, m.owner_name FROM users u
                     JOIN motorbancas m ON m.id=u.motorbanca_id WHERE u.role='operator' ORDER BY m.motorbanca_name")->fetchAll();
page_head('Operators', 'admin');
?>
<h1>Operators</h1>
<?php if ($msg) echo "<div class='alert ok'>$msg</div>"; ?>
<div class="tablewrap"><table>
  <tr><th>Motorbanca</th><th>Owner</th><th>Username</th><th>Password</th><th></th></tr>
  <?php foreach ($list as $o) echo "<tr><td>" . e($o['motorbanca_name']) . "</td><td>" . e($o['owner_name']) . "</td><td><code>" . e($o['username']) .
      "</code></td><td><code>" . e($o['temp_password']) . "</code></td><td><form method='post'><input type='hidden' name='uid' value='{$o['id']}'>
      <button class='btn sm'>New password</button></form></td></tr>"; ?>
</table></div>
<?php if (!$list) echo "<p class='muted'>No operators yet. Register a motorbanca first.</p>"; page_foot('admin');
