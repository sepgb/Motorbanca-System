<?php
// Opened by scanning a trip's QR code (in-app scanner or the phone camera). Adds this device's Profile to that trip's manifest.
require_once '../config/layout.php';
require_once '../config/profile.php';
require_once '../config/qr.php';
pax_device();
$tok = preg_match('/^[a-f0-9]{16}$/i', $_GET['t'] ?? '') ? strtolower($_GET['t']) : '';
$p = pax_profile();
$t = null;
$state = 'invalid';
if ($tok && !$p) $state = 'noprofile';
elseif ($tok) {
    $t = qr_trip_by_token($tok);
    if (!$t) $state = 'invalid';
    elseif (!qr_is_open($t)) $state = 'closed';
    else {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare("SELECT id FROM motorbancas WHERE id=? FOR UPDATE")->execute([$t['motorbanca_id']]);   // blocks double-adds
        $d = $pdo->prepare("SELECT 1 FROM passengers WHERE trip_id=? AND (device_token=? OR (LOWER(name)=LOWER(?) AND age=? AND LOWER(barangay)=LOWER(?)))");
        $d->execute([$t['id'], pax_device(), $p['full_name'], $p['age'], $p['barangay']]);
        $c = $pdo->prepare("SELECT COUNT(*) FROM passengers WHERE trip_id=?");
        $c->execute([$t['id']]);
        if ($d->fetch()) {
            $pdo->rollBack();
            $state = 'already';
        } elseif ($c->fetchColumn() >= $t['capacity']) {
            $pdo->rollBack();
            $state = 'full';
        } else {
            $pdo->prepare("INSERT INTO passengers(trip_id,name,age,barangay,device_token) VALUES(?,?,?,?,?)")->execute([$t['id'], $p['full_name'], (int)$p['age'], $p['barangay'], pax_device()]);
            $pdo->commit();
            $state = 'ok';
        }
    }
}
$boat = $t['motorbanca_name'] ?? '';
$M = [
    'ok'        => ['ok',  'You are on the manifest', 'You have been added to <b>' . e($boat) . '</b>. Have a safe trip!'],
    'already'   => ['ok',  'Already on the manifest', 'You are already on the manifest of <b>' . e($boat) . '</b>.'],
    'closed'    => ['bad', 'This boat is not boarding', '<b>' . e($boat) . '</b> is not ON QUEUE right now, so passengers cannot be added yet. Ask the boat operator.'],
    'full'      => ['bad', 'This boat is full', '<b>' . e($boat) . '</b> has reached its capacity. Please ask the operator or wait for the next boat.'],
    'noprofile' => ['bad', 'Set up your profile first', 'Your name, age and barangay are added to the manifest from your profile. Set it up once, then you will be added to this boat right away.'],
    'invalid'   => ['bad', 'QR code not valid', 'We could not find this trip. Scan the QR code shown by the boat operator again.'],
][$state];
page_head('Join trip', 'public');
?>
<section class="card jn">
    <div class="jn-ic <?= $M[0] ?>"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><?= $M[0] === 'ok' ? '<path d="M5 12.5l4.5 4.5L19 7.5"/>' : '<path d="M12 8v5M12 16.5v.01"/><circle cx="12" cy="12" r="9"/>' ?></svg></div>
    <h2><?= e($M[1]) ?></h2>
    <p><?= $M[2] ?></p>
    <?php if ($M[0] === 'ok' && $p): ?>
        <dl class="pf-list jn-det">
            <div>
                <dt>Name</dt>
                <dd><?= e($p['full_name']) ?></dd>
            </div>
            <div>
                <dt>Age</dt>
                <dd><?= (int)$p['age'] ?></dd>
            </div>
            <div>
                <dt>Barangay</dt>
                <dd><?= e($p['barangay']) ?></dd>
            </div>
        </dl>
    <?php endif; ?>
    <div class="jn-act">
        <?php if ($state === 'noprofile'): ?><a class="btn" href="profile.php?next=<?= urlencode('join.php?t=' . $tok) ?>">Set up profile</a>
        <?php elseif ($M[0] === 'ok'): ?><a class="btn" href="track.php">Track my ride</a><a class="btn ghost" href="index.php">Live Queue</a>
        <?php else: ?><a class="btn" href="index.php?scan=1">Scan again</a><a class="btn ghost" href="index.php">Live Queue</a><?php endif; ?>
    </div>
</section>
<?php page_foot('public'); ?>