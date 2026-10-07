<?php
require_once 'config/layout.php';
// First run: create the PCG admin account (pcg_admin / admin123). Change it afterwards.
if (!db()->query("SELECT 1 FROM users WHERE role='admin'")->fetch()) {
    db()->prepare("INSERT INTO users(username,password,role) VALUES('pcg_admin',?,'admin')")
        ->execute([password_hash('admin123', PASSWORD_DEFAULT)]);
}
if (isset($_SESSION['role'])) {
    header('Location: ' . BASE . ($_SESSION['role'] === 'admin' ? '/admin/dashboard.php' : '/operator/dashboard.php')); exit;
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s = db()->prepare("SELECT * FROM users WHERE username=?");
    $s->execute([trim($_POST['username'] ?? '')]);
    $u = $s->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
        session_regenerate_id(true);
        $_SESSION = ['uid' => $u['id'], 'role' => $u['role'], 'mid' => $u['motorbanca_id']];
        header('Location: ' . BASE . ($u['role'] === 'admin' ? '/admin/dashboard.php' : '/operator/dashboard.php')); exit;
    }
    $err = 'Wrong username or password. Check it and try again.';
}
page_head('Login');
?>
<div class="card narrow">
  <h1>Staff login</h1>
  <p class="muted">PCG and motorbanca operators only. Passengers do not need to log in.</p>
  <?php if ($err) echo "<div class='alert bad'>" . e($err) . "</div>"; ?>
  <form method="post" class="grid">
    <label>Username<input name="username" autocomplete="username" required></label>
    <label>Password<input name="password" type="password" autocomplete="current-password" required></label>
    <button class="btn">Log in</button>
  </form>
  <p><a href="passenger/index.php">← Back to live boat board</a></p>
</div>
<?php page_foot();
