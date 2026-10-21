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
if (travel_suspended()) {
    header('Location: dashboard.php');
    exit;
}   // travel suspended
if ($wait = next_trip_at($m['id'])) {
    header('Location: dashboard.php');
    exit;
}   // 1 hour after departing
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
    elseif (count(array_filter(array_slice($crew, 2), fn($c) => $c['name'] !== '')) < 1) $err = 'Add at least 1 crew member (Crew 3 or 4) besides the boat captain and the motorman.';
    else {
        crew_table();
        set_status($m['id'], 'Inspection');   // creates the trip; the PCG inspects the boat, then puts it on queue
        $t = db()->prepare("SELECT id FROM trips WHERE motorbanca_id=? AND status IN ('Inspection','Queued') ORDER BY id DESC LIMIT 1");
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
<p class="lead">The PCG will inspect your boat before putting it on queue</p>
<?php if ($err) echo "<div class='alert bad' role='alert'>" . e($err) . "</div>"; ?>
<form method="post" class="card crewform crew2" autocomplete="off">
    <div class="crew-cols">
        <section class="crew-col">
            <h3 class="crew-h">Details</h3>
            <div class="crew-top">
                <label>Motorbanca<input value="<?= e($m['motorbanca_name']) ?>" readonly></label>
                <label>Date<input value="<?= date('F j, Y') ?>" readonly></label>
                <label>Address<input name="address" maxlength="160" value="<?= e($addr) ?>" required></label>
                <label>Contact No.<input name="contact" type="tel" inputmode="tel" maxlength="25" value="<?= e($con) ?>" required></label>
            </div>
        </section>
        <section class="crew-col">
            <h3 class="crew-h">Crew</h3>
            <p class="muted crew-note">The boat captain is required, and so is at least 1 crew member besides the captain and the motorman.</p>
            <?php foreach ($roles as $i => $role): ?>
                <div class="crewrow">
                    <span class="crew-pos"><?= $i + 1 ?> &middot; <?= ucwords(strtolower($role)) ?></span>
                    <input name="cname[]" maxlength="80" placeholder="Full name" value="<?= e($crew[$i]['name'] ?? '') ?>" <?= $i === 0 ? ' required' : '' ?>>
                    <input name="cage[]" type="number" inputmode="numeric" min="0" max="120" placeholder="Age" value="<?= e($crew[$i]['age'] ?? '') ?>" <?= $i === 0 ? ' required' : '' ?>>
                </div>
            <?php endforeach; ?>
        </section>
    </div>
    <div class="crew-act">
        <a class="btn sm ghost" href="dashboard.php">Cancel</a>
        <button class="btn sm">Submit for inspection</button>
    </div>
</form>
<script>
    // at least 1 crew member (Crew 3 or 4) besides the boat captain and the motorman (the server checks it too)
    (function() {
        var f = document.querySelector('.crewform');
        if (!f) return;
        f.addEventListener('submit', function(e) {
            var n = f.querySelectorAll('input[name="cname[]"]'),
                ok = false;
            for (var i = 2; i < n.length; i++)
                if (n[i].value.trim()) ok = true;
            if (!ok) {
                e.preventDefault();
                n[2].setCustomValidity('Add at least 1 crew member besides the boat captain and the motorman.');
                n[2].reportValidity();
            }
        });
        f.addEventListener('input', function() {
            f.querySelectorAll('input[name="cname[]"]').forEach(function(i) {
                i.setCustomValidity('');
            });
        });
    })();
</script>
<?php page_foot('operator');
