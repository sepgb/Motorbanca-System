<?php
require_once '../config/layout.php';
need('admin');
mb_schema();
$msg = $err = '';
$keep = [];
$clusters = db()->query("SELECT * FROM clusters ORDER BY id")->fetchAll();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rest = trim(preg_replace('#^\s*M\s*/\s*B\s*[-.:]?\s*#i', '', trim($_POST['name'] ?? '')));
    $name = 'M/B ' . $rest;
    $owner = trim($_POST['owner'] ?? '');
    $addr = trim($_POST['address'] ?? '');
    $con = trim($_POST['contact'] ?? '');
    $cap = (int)($_POST['capacity'] ?? 0);
    $cid = (int)($_POST['cluster'] ?? 0);
    $ok = db()->prepare("SELECT 1 FROM clusters WHERE id=?");
    $ok->execute([$cid]);
    if ($rest === '' || strlen($rest) > 45 || $owner === '' || strlen($owner) > 100 || $cap < 1 || $cap > 500 || !$ok->fetch()) {
        $err = 'Please fill in every field. Capacity must be 1 to 500.';
    } elseif (strlen($addr) < 2 || strlen($addr) > 160) {
        $err = 'Enter the address.';
    } elseif (!preg_match('/^[0-9+\-\s()]{7,25}$/', $con)) {
        $err = 'Enter a valid contact number.';
    } else {
        try {
            $base = preg_replace('/[^A-Z0-9]/', '', strtoupper($rest)) ?: 'MB';
            $user = strtolower($base);
            $pass = $base . '@' . random_int(1000, 9999);
            db()->beginTransaction();
            db()->prepare("INSERT INTO motorbancas(motorbanca_name,owner_name,address,contact,capacity,cluster_id) VALUES(?,?,?,?,?,?)")
                ->execute([$name, $owner, $addr, $con, $cap, $cid]);
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
if ($err) $keep = $_POST;
?>
<h1 style="margin-bottom:18px">Registered motorbanca</h1>
<?php if ($msg) echo "<div class='alert ok'>$msg<br><small>Give these to the operator. You can see them anytime in the list.</small></div>";
if ($err) echo "<div class='alert bad'>" . e($err) . "</div>"; ?>
<div class="rg">
    <div class="rg-top">
        <div>
            <h2>Motorbanca list</h2>
            <p id="reg-n"><?= count($list) ?> registered motorbancas</p>
        </div>
        <div class="rg-tools">
            <input id="reg-q" class="rg-q" type="search" placeholder="Search M/B" autocomplete="off">
            <button type="button" class="btn rg-new">New registration +</button>
        </div>
    </div>
    <div class="rg-cols"><span>Motorbanca</span><span>Owner</span><span>Address and contact</span><span>Capacity</span><span>Cluster</span><span>Operator login</span></div>
    <?php foreach ($list as $m):
        $nm = preg_replace('#^M/B\s*#', '', $m['motorbanca_name']);
        $ad = $m['address'] ?? '';
        $ct = $m['contact'] ?? ''; ?>
        <div class="mb-row rg-row" data-hint="Double-click to edit" data-id="<?= (int)$m['id'] ?>" data-name="<?= e($nm) ?>" data-owner="<?= e($m['owner_name'] === 'Not set' ? '' : $m['owner_name']) ?>" data-address="<?= e($ad) ?>" data-contact="<?= e($ct) ?>" data-cap="<?= (int)$m['capacity'] ?>" data-cluster="<?= (int)$m['cluster_id'] ?>">
            <span><b><?= e($m['motorbanca_name']) ?></b><i>Motorbanca #<?= (int)$m['id'] ?></i></span>
            <span><?= e($m['owner_name']) ?></span>
            <span><b><?= e($ad ?: 'Not set') ?></b><i><?= e($ct ?: 'Not set') ?></i></span>
            <span class="rg-cap"><b><?= (int)$m['capacity'] ?></b><i>Seats</i></span>
            <span><?= e($m['cluster']) ?></span>
            <span><b><?= e($m['username']) ?></b><i>Password: <?= e($m['temp_password']) ?></i></span>
        </div>
    <?php endforeach; ?>
    <p class="rg-none" id="reg-none" hidden>No motorbanca found.</p>
</div>

<div class="mdl" id="reg-mdl" role="dialog" aria-modal="true" aria-labelledby="reg-t">
    <div class="mdl-box wide">
        <div class="mdl-head">
            <div>
                <h3 id="reg-t">New registration</h3>
                <p class="sub">A login for the operator is created automatically.</p>
            </div><button type="button" class="x" aria-label="Close">&times;</button>
        </div>
        <form method="post" class="mdl-form grid">
            <label>Motorbanca name<div class="pfx"><span>M/B</span><input name="name" placeholder="Ruiz" maxlength="45" value="<?= e($keep['name'] ?? '') ?>" required></div></label>
            <label>Owner name<input name="owner" placeholder="Juan Dela Cruz" maxlength="100" value="<?= e($keep['owner'] ?? '') ?>" required></label>
            <label>Address<input name="address" placeholder="Brgy. Buhangin, Binangonan, Rizal" maxlength="160" value="<?= e($keep['address'] ?? '') ?>" required></label>
            <label>Contact No.<input name="contact" type="tel" inputmode="tel" placeholder="09XX XXX XXXX" maxlength="25" value="<?= e($keep['contact'] ?? '') ?>" required></label>
            <label>Capacity<input name="capacity" type="number" min="1" max="500" placeholder="45" value="<?= e($keep['capacity'] ?? '') ?>" required></label>
            <label>Cluster<select name="cluster"><?php foreach ($clusters as $c) echo "<option value='{$c['id']}'" . (($keep['cluster'] ?? '') == $c['id'] ? ' selected' : '') . ">" . e($c['name']) . "</option>"; ?></select></label>
            <div class="mdl-act"><button type="button" class="no">Cancel</button><button class="go">Register and create login</button></div>
        </form>
    </div>
</div>

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
            <label>Address<input name="address" maxlength="160" placeholder="Brgy. Buhangin, Binangonan, Rizal" required></label>
            <label>Contact No.<input name="contact" type="tel" inputmode="tel" maxlength="25" placeholder="09XX XXX XXXX" required></label>
            <label>Capacity<input name="capacity" type="number" min="1" max="500" required></label>
            <label>Cluster<select name="cluster"><?php foreach ($clusters as $c) echo "<option value='{$c['id']}'>" . e($c['name']) . "</option>"; ?></select></label>
            <div class="alert bad" id="edit-err" hidden></div>
            <div class="mdl-act"><button type="button" class="no">Cancel</button><button class="go">Save changes</button></div>
        </form>
    </div>
</div>
<script>
    (function() {
        var q = document.getElementById('reg-q'),
            n = document.getElementById('reg-n'),
            none = document.getElementById('reg-none'),
            m = document.getElementById('reg-mdl'),
            rows = [].slice.call(document.querySelectorAll('.mb-row'));

        function run() {
            var t = q.value.trim().toLowerCase(),
                k = 0;
            rows.forEach(function(r) {
                var ok = !t || r.textContent.toLowerCase().indexOf(t) > -1;
                r.hidden = !ok;
                if (ok) k++;
            });
            n.textContent = t ? k + ' of ' + rows.length + ' motorbancas' : rows.length + ' registered motorbancas';
            none.hidden = k > 0;
        }

        function open() {
            void m.offsetWidth;
            m.classList.add('open');
            var f = m.querySelector('input');
            if (f) f.focus();
        }

        function close() {
            m.classList.remove('open');
        }
        q.addEventListener('input', run);
        document.querySelector('.rg-new').addEventListener('click', open);
        m.addEventListener('click', function(e) {
            if (e.target === m || e.target.classList.contains('x') || e.target.classList.contains('no')) close();
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && m.classList.contains('open')) close();
        });
        run();
        <?php if ($err) echo 'open();'; ?>
    })();
</script>
<?php page_foot('admin');
