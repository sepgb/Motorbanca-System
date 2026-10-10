<?php
require_once 'config/layout.php';
// First run: create the PCG admin account (pcg_admin / admin123). Change it afterwards.
if (!db()->query("SELECT 1 FROM users WHERE role='admin'")->fetch()) {
    db()->prepare("INSERT INTO users(username,password,role) VALUES('pcg_admin',?,'admin')")
        ->execute([password_hash('admin123', PASSWORD_DEFAULT)]);
}
if (isset($_SESSION['role'])) {
    header('Location: ' . BASE . ($_SESSION['role'] === 'admin' ? '/admin/dashboard.php' : '/operator/dashboard.php'));
    exit;
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $s = db()->prepare("SELECT * FROM users WHERE username=?");
    $s->execute([trim($_POST['username'] ?? '')]);
    $u = $s->fetch();
    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {
        session_regenerate_id(true);
        $_SESSION = ['uid' => $u['id'], 'role' => $u['role'], 'mid' => $u['motorbanca_id']];
        header('Location: ' . BASE . ($u['role'] === 'admin' ? '/admin/dashboard.php' : '/operator/dashboard.php'));
        exit;
    }
    $err = 'Wrong username or password. Check it and try again.';
}
$b = BASE;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Log in | Motorbanca Monitor</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="<?= $b ?>/css/style.css">
    <link rel="stylesheet" href="<?= $b ?>/css/admin.css">
    <link rel="stylesheet" href="<?= $b ?>/css/login.css">
</head>

<body class="r-admin login">
    <main class="lg">
        <section class="lg-brand">
            <h2>Port Control</h2>
            <p>Live boat queue and trip monitoring for the port.</p>
            <img src="<?= $b ?>/admin/logo.webp" alt="Philippine Coast Guard" width="96" height="96">
            <img class="lg-photo" src="<?= $b ?>/admin/pcg.jpg" alt="Coast Guard personnel helping a passenger board a motorbanca">
        </section>
        <section class="lg-form">
            <div class="lg-mark"><img src="<?= $b ?>/admin/logo.webp" alt="" width="44" height="44"><span>Port Control</span></div>
            <h1>Welcome back</h1>
            <p class="muted lg-sub">Log in to continue</p>
            <?php if ($err) echo "<div class='alert bad' role='alert'>" . e($err) . "</div>"; ?>
            <form method="post" class="grid" autocomplete="on">
                <label><span class="lbl">Username</span>
                    <span class="pw"><input name="username" placeholder="Username" autocomplete="username" autocapitalize="none" spellcheck="false" value="<?= e(trim($_POST['username'] ?? '')) ?>" required>
                        <i class="pw-i" aria-hidden="true"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <circle cx="12" cy="8" r="4" />
                                <path d="M4 21c0-4 3.6-7 8-7s8 3 8 7" />
                            </svg></i></span>
                </label>
                <label><span class="lbl">Password</span>
                    <span class="pw"><input name="password" id="pw" type="password" placeholder="Password" autocomplete="current-password" required>
                        <button type="button" class="pw-t" aria-label="Show password" aria-pressed="false">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" />
                                <circle cx="12" cy="12" r="3" />
                            </svg>
                        </button></span>
                </label>
                <button class="btn">Log in</button>
            </form>
            <p class="lg-note">Log in with the username and password given to you.</p>
        </section>
    </main>
    <script>
        if (window.innerWidth >= 760) document.querySelector('input[name=username]').focus(); // on phones the keyboard would cover the header
        document.querySelector('.pw-t').addEventListener('click', function() {
            var i = document.getElementById('pw'),
                show = i.type === 'password';
            i.type = show ? 'text' : 'password';
            this.setAttribute('aria-pressed', show);
            this.setAttribute('aria-label', show ? 'Hide password' : 'Show password');
            this.classList.toggle('on', show);
            i.focus();
        });
    </script>
</body>

</html>