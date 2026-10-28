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
$editTitle = $editing && pax_profile();   // changing an existing profile
page_head($editTitle ? 'Edit Profile' : 'Profile', 'public');
?>
<h1><?= $editTitle ? 'Edit Profile' : 'Profile' ?></h1>
<?php if ($err): ?><div class="alert bad"><?= e($err) ?></div><?php endif; ?>
<?php $view = !$editing;   // saved profile shown in the same layout as the form: fields locked, button says Edit 
?>
<section class="card pf">
    <form method="post" class="pf-form<?= $view ? ' ro' : '' ?>" autocomplete="off">
        <input type="hidden" name="next" value="<?= e($next) ?>">
        <label>Full name</label>
        <input name="full_name" maxlength="80" required<?= $view ? ' disabled' : '' ?> value="<?= e($p['full_name'] ?? '') ?>" placeholder="Juan Dela Cruz">
        <div class="pf-row">
            <div><label>Age</label><input name="age" type="number" inputmode="numeric" min="1" max="120" required<?= $view ? ' disabled' : '' ?> value="<?= e($p['age'] ?? '') ?>"></div>
            <div><label>Barangay</label><select name="barangay" required<?= $view ? ' disabled' : '' ?>>
                    <option value="">Choose barangay</option>
                    <?php foreach ($brgys as $b): ?><option<?= ($p['barangay'] ?? '') === $b ? ' selected' : '' ?>><?= e($b) ?></option><?php endforeach; ?>
                </select></div>
        </div>
        <div class="pf-act"><?php if ($view): ?><a class="btn" href="profile.php?edit=1"><span>Edit</span><span class="btn-ic"><?= ico('<path d="M12 20h9"/><path d="M16.5 3.5a2.12 2.12 0 013 3L7 19l-4 1 1-4z"/>') ?></span></a><?php else: ?><?php if (pax_profile()): ?><a class="btn ghost" href="profile.php">Cancel</a><?php endif; ?><button class="btn"><span>Save</span><span class="btn-ic"><?= ico('<path d="M5 12.5l4.5 4.5L19 7.5"/>') ?></span></button><?php endif; ?></div>
    </form>
</section>
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