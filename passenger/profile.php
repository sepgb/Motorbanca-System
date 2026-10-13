<?php
// Passenger profile: full name, age and barangay. It is the account of this device (no log in).
require_once '../config/layout.php';
require_once '../config/profile.php';
pax_device();   // makes sure the device cookie is set before any output
$brgys = [];
try {
    $brgys = db()->query("SELECT DISTINCT name FROM barangays ORDER BY name")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $x) {
}
$next = preg_match('/^join\.php\?t=[a-f0-9]{16}$/', $_REQUEST['next'] ?? '') ? $_REQUEST['next'] : '';   // back to the QR join after setting up
$p = pax_profile();
$err = '';
$editing = !$p || isset($_GET['edit']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(preg_replace('/\s+/', ' ', $_POST['full_name'] ?? ''));
    $age = (int)($_POST['age'] ?? 0);
    $b = trim($_POST['barangay'] ?? '');
    if (mb_strlen($name) < 2 || mb_strlen($name) > 80) $err = 'Please enter your full name.';
    elseif ($age < 1 || $age > 120) $err = 'Please enter a valid age (1 to 120).';
    elseif (!in_array($b, $brgys, true)) $err = 'Please choose your barangay.';
    else {
        pax_save($name, $age, $b);
        $_SESSION['pax_saved'] = 1;
        header('Location: ' . ($next ?: 'profile.php'));
        exit;
    }
    $editing = true;
    $p = ['full_name' => $name, 'age' => $_POST['age'] ?? '', 'barangay' => $b] + ($p ?: []);
}
$saved = !empty($_SESSION['pax_saved']);
unset($_SESSION['pax_saved']);
page_head('Profile', 'public');
$ini = $p ? strtoupper(mb_substr(trim($p['full_name']), 0, 1)) : '';
?>
<h1>Profile</h1>
<?php if ($err): ?><div class="alert bad"><?= e($err) ?></div><?php endif; ?>
<?php if (!$editing): ?>
    <section class="card pf">
        <div class="pf-head">
            <div class="pf-av"><?= e($ini) ?></div>
            <div>
                <h2><?= e($p['full_name']) ?></h2><span class="pf-sub">Passenger &middot; this device</span>
            </div>
            <a class="pf-edit" href="profile.php?edit=1"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M4 20h4L19 9l-4-4L4 16v4zM13.5 6.5l4 4" />
                </svg><span>Edit</span></a>
        </div>
        <dl class="pf-list">
            <div>
                <dt>Age</dt>
                <dd><?= (int)$p['age'] ?></dd>
            </div>
            <div>
                <dt>Barangay</dt>
                <dd><?= e($p['barangay']) ?></dd>
            </div>
        </dl>
    </section>
<?php else: ?>
    <section class="card pf">
        <h3 style="margin:0 0 4px"><?= pax_profile() ? 'Edit profile' : 'Set up your profile' ?></h3>
        <p class="muted" style="margin:0 0 14px"><?= pax_profile() ? 'Change your details, then save.' : 'Enter your details once. This device will remember you.' ?></p>
        <form method="post" class="pf-form" autocomplete="off">
            <input type="hidden" name="next" value="<?= e($next) ?>">
            <label>Full name</label>
            <input name="full_name" maxlength="80" required value="<?= e($p['full_name'] ?? '') ?>" placeholder="Juan Dela Cruz">
            <div class="pf-row">
                <div><label>Age</label><input name="age" type="number" inputmode="numeric" min="1" max="120" required value="<?= e($p['age'] ?? '') ?>"></div>
                <div><label>Barangay</label><select name="barangay" required>
                        <option value="">Choose barangay</option>
                        <?php foreach ($brgys as $b): ?><option<?= ($p['barangay'] ?? '') === $b ? ' selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
                    </select></div>
            </div>
            <div class="pf-act"><?php if (pax_profile()): ?><a class="btn ghost" href="profile.php">Cancel</a><?php endif; ?><button class="btn">Save</button></div>
        </form>
    </section>
<?php endif; ?>
<script>
    (function() {
        try {
            var m = document.cookie.match(/(?:^|; )pax_device=([a-f0-9]{32})/),
                k = 'pax_device';
            if (m) {
                localStorage.setItem(k, m[1])
            } else {
                var s = localStorage.getItem(k);
                if (s && /^[a-f0-9]{32}$/.test(s) && !sessionStorage.getItem('pxr')) {
                    sessionStorage.setItem('pxr', '1');
                    document.cookie = k + '=' + s + '; max-age=63072000; path=/; samesite=lax';
                    location.reload()
                }
            }
        } catch (e) {}
    })();
</script>
<?php page_foot('public'); ?>