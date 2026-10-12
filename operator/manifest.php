<?php
require_once '../config/layout.php';
require_once '../config/qr.php';
need('operator');
$m = my_mb();
if (!$m) {
  header('Location: ../logout.php');
  exit;
}
$tq = db()->prepare("SELECT id FROM trips WHERE motorbanca_id=? AND status IN ('Inspection','Queued') ORDER BY id DESC LIMIT 1");   // a departed trip lives in Trip History
$tq->execute([$m['id']]);
$trip = $tq->fetchColumn();
$pos = null;
$tag = '';
if ($m['status'] === 'Queued') {   // 0 = ON QUEUE, 1 = NEXT TRIP, 2+ = WAITING
  [$dep, $q] = split_cluster(live_rows(), $m['cluster_id']);
  foreach ($q as $i => $r) if ($r['id'] == $m['id']) {
    $pos = $i + ($dep ? 1 : 0);
    $tag = tag_for((bool)$dep, $i);
  }
}
$open = $trip && $m['status'] === 'Queued' && $pos === 0;
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $open) {
  $name = trim($_POST['name'] ?? '');
  $age = $_POST['age'] ?? '';
  $brgy = trim($_POST['barangay'] ?? '');
  if (strlen($name) < 2 || strlen($name) > 80 || !ctype_digit((string)$age) || $age > 120 || strlen($brgy) < 2 || strlen($brgy) > 60) {
    $err = 'Enter a name, an age (0 to 120) and a barangay.';
  } else {
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare("SELECT id FROM motorbancas WHERE id=? FOR UPDATE")->execute([$m['id']]);   // blocks double-adds
    $c = $pdo->prepare("SELECT COUNT(*) FROM passengers WHERE trip_id=?");
    $c->execute([$trip]);
    if ($c->fetchColumn() >= $m['capacity']) {
      $pdo->rollBack();
      $err = 'Boat is FULL. No more passengers can be added.';
    } else {
      $pdo->prepare("INSERT INTO passengers(trip_id,name,age,barangay) VALUES(?,?,?,?)")->execute([$trip, $name, (int)$age, $brgy]);
      $pdo->commit();
      header('Location: manifest.php');
      exit;
    }
  }
  $m = my_mb();
}
$pax = $trip ? db()->query("SELECT * FROM passengers WHERE trip_id=" . (int)$trip . " ORDER BY id")->fetchAll() : [];
$full = $m['pax'] >= $m['capacity'];
page_head('Passengers', 'operator');
[$tg, $tcls] = op_tag($m);
?>
<h1><?= e($m['motorbanca_name']) ?></h1>
<div class="optag <?= $tcls ?>" style="margin:2px 0 14px"><?= $tg ?></div>
<?php if (!$trip && !$err && ($m['status'] === 'Departed' || next_trip_at($m['id']))): $wait = next_trip_at($m['id']); ?>
  <div class="card dep-card">
    <div class="dep-ic"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
        <path d="M3 17l2 3h14l2-3H3z" />
        <path d="M12 3v10M12 5l6 8H6" />
      </svg></div>
    <h2>Trip departed</h2>
    <p>Your trip has departed. The manifest are saved in Trip History.</p>
    <?php if (travel_suspended()): ?><div class="dep-wait susp">Travel is suspended. You cannot start a new trip yet.</div><?php elseif ($wait): ?><div class="dep-wait">New trip available from <b><?= e($wait) ?></b></div><?php endif; ?>
    <a class="btn" href="trips.php">Open Trip History</a>
  </div>
<?php page_foot('operator');
  exit;
endif; ?>
<?php $tc = $trip ? trip_crew_get($trip) : null;
$td = $trip ? db()->query("SELECT trip_date FROM trips WHERE id=" . (int)$trip)->fetchColumn() : null; ?>
<div class="mpage">
  <?php if ($trip): ?><div class="card mp-count"><?php echo count_html($m['pax'], $m['capacity']); ?><p class="muted" style="margin:0">passengers on board</p>
    </div><?php endif; ?>
  <?php if ($err) echo "<div class='alert bad mp-alert'>" . e($err) . "</div>"; ?>
  <?php $showForm = false;
  if (!$trip): ?>
    <div class="alert mp-alert"><?= travel_suspended() ? 'Travel is suspended by the PCG. You cannot start a new trip until travel resumes.' : 'No active trip. Go Home and tap New trip.' ?></div>
  <?php elseif (!$open): ?>
    <div class="alert mp-alert"><?= $m['status'] === 'Inspection'
                                  ? 'Your boat is <b>FOR INSPECTION</b>. You can add passengers once the PCG approves it and it is ON QUEUE.'
                                  : ($m['status'] === 'Queued'
                                    ? '&#9203; Your boat is <b>' . e($tag) . '</b>. You can add passengers once your boat is ON QUEUE.'
                                    : 'This trip has departed. Go Home and tap New trip for the next one.') ?></div>
  <?php elseif ($full): ?>
    <div class="alert bad mp-alert"><b>FULL</b> &mdash; no more passengers can be added.</div>
  <?php else: $showForm = true;
  endif; ?>
  <div class="reg">
    <div class="reg-side">
      <?php if ($showForm): ?>
        <form method="post" class="card grid">
          <h3>Add passenger</h3>
          <label>Passenger name<input name="name" maxlength="80" autocomplete="off" required></label>
          <label>Age<input name="age" type="number" inputmode="numeric" min="0" max="120" required></label>
          <label>Barangay<input name="barangay" maxlength="60" autocomplete="off" required></label>
          <button class="btn">+ Add passenger</button>
        </form>
      <?php endif; ?>
      <?php if ($open): $qt = trip_qr_token($trip); ?>
        <div class="card qrc">
          <h3 style="margin:0">Trip QR code</h3>
          <div class="qrc-box">
            <div id="qr" data-url="<?= e(trip_join_url($qt)) ?>"></div>
          </div>
        </div>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <script>
          (function() {
            var e = document.getElementById('qr');
            if (e && window.QRCode) new QRCode(e, {
              text: e.dataset.url,
              width: 200,
              height: 200,
              correctLevel: QRCode.CorrectLevel.M
            })
          })();
          // passengers who scan appear here without refreshing
          setInterval(function() {
            if (document.hidden) return;
            fetch(location.href, {
              credentials: 'same-origin'
            }).then(function(r) {
              return r.text()
            }).then(function(h) {
              var d = new DOMParser().parseFromString(h, 'text/html');
              ['.tablewrap', '.mp-count'].forEach(function(q) {
                var a = document.querySelector(q),
                  b = d.querySelector(q);
                if (a && b && a.innerHTML !== b.innerHTML) a.innerHTML = b.innerHTML
              })
            }).catch(function() {})
          }, 5000);
        </script>
      <?php endif; ?>
      <?php if ($trip): ?>
        <div class="card mp-details">
          <h3 style="margin:0 0 8px">Manifest details</h3>
          <dl class="kv">
            <dt>Pangalan ng bangka</dt>
            <dd><?= e($m['motorbanca_name']) ?></dd>
            <dt>Date</dt>
            <dd><?= $td ? date('F j, Y', strtotime($td)) : '&mdash;' ?></dd>
            <dt>Pangalan ng tauhan</dt>
            <dd><?php if ($tc && $tc['crew']) foreach ($tc['crew'] as $c) echo "<div>" . e($c['name']) . " <span class='muted'>&middot; " . ucwords(strtolower(e($c['position']))) . "</span></div>";
                else echo '&mdash;'; ?></dd>
          </dl>
          <div class="dl-act">
            <button type="button" class="btn sm ghost" data-pdf="print_crew.php"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 4v11M7 11l5 5 5-5M5 20h14" />
              </svg><span>Crew list</span></button>
            <button type="button" class="btn sm" data-pdf="print.php"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M12 4v11M7 11l5 5 5-5M5 20h14" />
              </svg><span>Manifest</span></button>
          </div>
        </div>
      <?php endif; ?>
    </div>
    <?php if ($trip): ?>
      <div class="tablewrap">
        <table>
          <tr>
            <th>No.</th>
            <th>Name</th>
            <th>Age</th>
            <th>Barangay</th>
          </tr>
          <?php foreach ($pax as $i => $p) echo "<tr><td>" . ($i + 1) . "</td><td>" . e($p['name']) . "</td><td>{$p['age']}</td><td>" . e($p['barangay']) . "</td></tr>"; ?>
          <?php if (!$pax) echo "<tr><td colspan='4' class='muted' style='text-align:center;padding:22px'>No passengers yet.</td></tr>"; ?>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php page_foot('operator');
