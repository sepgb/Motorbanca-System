<?php
// Crew list: must be filled in before the boat can join the queue. Saving it creates the trip.
require_once '../config/layout.php';
need('operator');
$m = my_mb();
if (!$m) {
    header('Location: ../logout.php');
    exit;
}
if (!in_array($m['status'], ['Not Queued', 'Departed'], true)) {
    header('Location: manifest.php');
    exit;
}
$roles = ['BOAT CAPTAIN', 'MOTORMAN', 'CREW', 'CREW'];
$last = crew_last($m['id']);
$addr = $last['address'] ?? '';
$con = $last['contact'] ?? '';
$crew = $last['crew'] ?? [];
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $addr = trim($_POST['address'] ?? '');
    $con = trim($_POST['contact'] ?? '');
    $names = (array)($_POST['cname'] ?? []);
    $ages = (array)($_POST['cage'] ?? []);
    $crew = [];
    $bad = '';
    foreach ($roles as $i => $role) {
        $n = trim((string)($names[$i] ?? ''));
        $a = trim((string)($ages[$i] ?? ''));
        $crew[] = ['name' => $n, 'age' => $a, 'position' => $role];
        if ($i === 0 && ($n === '' || $a === '')) $bad = 'The boat captain needs a name and an age.';
        elseif ($n !== '' && (strlen($n) < 2 || strlen($n) > 80 || !ctype_digit($a) || (int)$a > 120)) $bad = 'Check the crew names and ages (age 0 to 120).';
        elseif ($n === '' && $a !== '') $bad = 'Enter the name for every age you filled in.';
    }
    if (strlen($addr) < 2 || strlen($addr) > 160) $err = 'Enter the address.';
    elseif (!preg_match('/^[0-9+\-\s()]{7,25}$/', $con)) $err = 'Enter a valid contact number.';
    elseif ($bad) $err = $bad;
    else {
        crew_table();
        set_status($m['id'], 'Queued');   // creates the trip (or reuses the one already waiting)
        $t = db()->prepare("SELECT id FROM trips WHERE motorbanca_id=? AND status='Queued' ORDER BY id DESC LIMIT 1");
        $t->execute([$m['id']]);
        $tid = $t->fetchColumn();
        if ($tid) {
            $keep = array_values(array_filter($crew, fn($c) => $c['name'] !== ''));
            db()->prepare("REPLACE INTO trip_crew(trip_id,address,contact,crew) VALUES(?,?,?,?)")
                ->execute([$tid, $addr, $con, json_encode($keep, JSON_UNESCAPED_UNICODE)]);
        }
        header('Location: manifest.php');
        exit;
    }
}
page_head('Crew list', 'operator');
?>
<h1>Crew list</h1>
<p class="lead">Fill this in before your boat joins the queue. It is added to the passenger manifest.</p>
<?php if ($err) echo "<div class='alert bad' role='alert'>" . e($err) . "</div>"; ?>
<form method="post" class="card crewform" autocomplete="off">
    <div class="crew-top">
        <label>Motorbanca<input value="<?= e($m['motorbanca_name']) ?>" readonly></label>
        <label>Date<input value="<?= date('F j, Y') ?>" readonly></label>
        <label>Address<input name="address" maxlength="160" value="<?= e($addr) ?>" required></label>
        <label>Contact No.<input name="contact" type="tel" inputmode="tel" maxlength="25" value="<?= e($con) ?>" required></label>
    </div>
    <h3 class="crew-h">Crew</h3>
    <?php foreach ($roles as $i => $role): ?>
        <div class="crewrow">
            <span class="crew-pos"><?= $i + 1 ?> &middot; <?= ucwords(strtolower($role)) ?></span>
            <input name="cname[]" maxlength="80" placeholder="Full name" value="<?= e($crew[$i]['name'] ?? '') ?>" <?= $i === 0 ? ' required' : '' ?>>
            <input name="cage[]" type="number" inputmode="numeric" min="0" max="120" placeholder="Age" value="<?= e($crew[$i]['age'] ?? '') ?>" <?= $i === 0 ? ' required' : '' ?>>
        </div>
    <?php endforeach; ?>
    <div class="crew-act">
        <a class="btn ghost" href="dashboard.php">Cancel</a>
        <button class="btn">Save &amp; join queue</button>
    </div>
</form>
<?php page_foot('operator');
