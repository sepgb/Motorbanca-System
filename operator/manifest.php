<?php
require_once '../config/layout.php';
require_once '../config/qr.php';
require_once '../config/mdsd_helpers.php';
need('operator');
$m = my_mb();
if (!$m) {
  header('Location: ../logout.php');
  exit;
}
$tq = db()->prepare("SELECT id FROM trips WHERE motorbanca_id=? AND status IN ('Inspection','Queued') ORDER BY id DESC LIMIT 1");
$tq->execute([$m['id']]);
$trip = $tq->fetchColumn();
$pos = null;
$tag = '';
if ($m['status'] === 'Queued') {
  [$dep, $q] = split_cluster(live_rows(), $m['cluster_id']);
  foreach ($q as $i => $r) if ($r['id'] == $m['id']) {
    $pos = $i + ($dep ? 1 : 0);
    $tag = tag_for((bool)$dep, $i);
  }
}
$ready = $trip && $m['status'] === 'Queued' && !empty($m['depart_req']);
$open = $trip && $m['status'] === 'Queued' && $pos === 0 && !$ready;
$canRm = $trip && $m['status'] === 'Queued' && !$ready;
$canDepart = $open;
$roles = ['BOAT CAPTAIN', 'MOTORMAN', 'CREW', 'CREW'];
$last = $canDepart ? crew_last($m['id']) : null;
$crew = $last['crew'] ?? [];
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_id']) && $canRm) {
  db()->prepare("DELETE FROM passengers WHERE id=? AND trip_id=?")->execute([(int)$_POST['remove_id'], $trip]);
  header('Location: manifest.php');
  exit;
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $open && !isset($_POST['remove_id'])) {
  $name = trim($_POST['name'] ?? '');
  $age = $_POST['age'] ?? '';
  $brgy = trim($_POST['barangay'] ?? '');
  if (strlen($name) < 2 || strlen($name) > 80 || !ctype_digit((string)$age) || $age > 120 || strlen($brgy) < 2 || strlen($brgy) > 60) {
    $err = 'Enter a name, an age (0 to 120) and a barangay.';
  } else {
    $pdo = db();
    $pdo->beginTransaction();
    $pdo->prepare("SELECT id FROM motorbancas WHERE id=? FOR UPDATE")->execute([$m['id']]);
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
<?php if ($trip): ?>
  <h1><?= e($m['motorbanca_name']) ?></h1>
  <?php if ($m['status'] !== 'Inspection'): ?><div class="optag <?= $tcls ?>" style="margin:2px 0 14px"><?= $tg ?></div><?php endif; ?>
<?php endif; ?>
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
<div class="mpage" data-ready="<?= $ready ? 1 : 0 ?>">
  <?php if ($trip): ?><div class="card mp-count"><?php echo count_html($m['pax'], $m['capacity'], 'passengers on board'); ?>
      <?php if ($canDepart): ?><button type="button" class="btn dep-open" style="margin-top:16px;width:100%">Depart</button><?php endif; ?>
    </div><?php endif; ?>
  <?php if ($err) echo "<div class='alert bad mp-alert'>" . e($err) . "</div>"; ?>
  <?php $showForm = false;
  if (!$trip): ?>
    <div class="mp-empty"><?= travel_suspended() ? 'Travel is suspended by the PCG. You cannot start a new trip until travel resumes.' : 'No active trip. Go Home and tap New trip.' ?></div>
  <?php elseif (!$open): ?>
    <?php
    if ($m['status'] === 'Inspection') {
      $noteT = 'For inspection';
      $noteM = 'You can add passengers once the PCG approves your boat and it is on the queue.';
      $noteC = 'Waiting for the PCG';
      $noteI = '<path d="M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z"/><path d="M9 12l2 2 4-4"/>';
    } elseif ($ready) {
      $noteT = 'Ready to depart';
      $noteM = 'The PCG has been notified. They will check your crew list, manifest and MDSD, then depart your boat.';
      $noteC = 'Waiting for the PCG';
      $noteI = '<path d="M7 17L17 7M8 7h9v9"/>';
    } elseif ($m['status'] === 'Queued') {
      $noteT = strtoupper($tag) === 'NEXT TRIP' ? 'You are next in line' : 'You are waiting in line';
      $noteM = 'You can add passengers once your boat is on the queue.';
      $noteC = 'Waiting for your turn';
      $noteI = '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>';
    } else {
      $noteT = 'Trip departed';
      $noteM = 'Go Home and tap New trip for the next one.';
      $noteC = '';
      $noteI = '<path d="M5 21V4"/><path d="M5 4h11l-2 4 2 4H5"/>';
    } ?>
    <div class="mp-note mp-alert" role="status"><span class="mp-note-ic"><?= ico($noteI) ?></span>
      <div class="mp-note-tx"><b><?= e($noteT) ?></b><span><?= e($noteM) ?></span></div>
      <?php if ($noteC !== ''): ?><span class="mp-note-chip"><i></i><i></i><i></i><?= e($noteC) ?></span><?php endif; ?>
    </div>
  <?php elseif ($full): ?>
    <div class="alert bad mp-alert"><b>FULL</b> &mdash; no more passengers can be added.</div>
  <?php else: $showForm = true;
  endif; ?>
  <div class="reg">
    <div class="reg-side">
      <?php if ($showForm): ?>
        <form method="post" class="card grid">
          <label>Name<input name="name" maxlength="80" autocomplete="off" required></label>
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
            <dd><?php if ($tc && $tc['crew']) foreach ($tc['crew'] as $c) echo "<div class='mp-cm'><span class='mp-nm'>" . e($c['name']) . "</span><small>" . ucwords(strtolower(e($c['position']))) . "</small></div>";
                else echo '&mdash;'; ?></dd>
          </dl>
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
            <?php if ($canRm): ?><th class="rm-col"><span>Action</span></th><?php endif; ?>
          </tr>
          <?php foreach ($pax as $i => $p) echo "<tr><td>" . ($i + 1) . "</td><td>" . e($p['name']) . "</td><td>{$p['age']}</td><td>" . e($p['barangay']) . "</td>"
            . ($canRm ? "<td class='rm-cell'><button type='button' class='rm-btn' data-id='" . (int)$p['id'] . "' data-name='" . e($p['name']) . "'>Remove</button></td>" : '') . "</tr>"; ?>
          <?php if (!$pax) echo "<tr><td colspan='" . ($canRm ? 5 : 4) . "' class='muted' style='text-align:center;padding:22px'>No passengers yet.</td></tr>"; ?>
        </table>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php if ($canRm): ?>
  <div class="mdl" id="rm-mdl" role="dialog" aria-modal="true" aria-labelledby="rm-t">
    <div class="mdl-box">
      <div class="mdl-ic red"><?= ico('<circle cx="9" cy="8" r="3.5"/><path d="M2.5 20c0-3.6 2.9-6 6.5-6s6.5 2.4 6.5 6M17 8h5"/>') ?></div>
      <h3 id="rm-t">Remove passenger?</h3>
      <p id="rm-p"></p>
      <form method="post">
        <input type="hidden" name="remove_id" id="rm-id">
        <div class="mdl-act"><button type="button" class="no">Keep</button><button class="go red">Remove</button></div>
      </form>
    </div>
  </div>
  <script>
    (function() {
      var m = document.getElementById('rm-mdl'),
        from = null;
      if (!m) return;

      function close() {
        m.classList.remove('open');
        if (from) from.focus()
      }

      document.addEventListener('click', function(e) {
        var b = e.target.closest('.rm-btn');
        if (!b) return;
        from = b;
        document.getElementById('rm-id').value = b.dataset.id;
        document.getElementById('rm-p').textContent = b.dataset.name + ' will be taken off the manifest and the boat will have one more seat free.';
        m.classList.add('open');
        m.querySelector('.no').focus()
      });
      m.querySelector('.no').addEventListener('click', close);
      m.addEventListener('click', function(e) {
        if (e.target === m) close()
      });
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && m.classList.contains('open')) close()
      });
    })();
  </script>
<?php endif; ?>
<?php if ($canDepart): ?>
  <div class="mdl" id="dep-mdl" role="dialog" aria-modal="true" aria-labelledby="dep-t">
    <div class="mdl-box wide md-box">
      <div class="mdl-head">
        <div class="mdl-title">
          <h3 id="dep-t">Master&rsquo;s Declaration of Safe Departure</h3>
          <p class="sub" id="dep-s">Declare the condition of your boat and its documents. Every item needs an answer.</p>
        </div>
        <div class="mdl-tools"><button type="button" class="x" aria-label="Close">&times;</button></div>
      </div>
      <form class="md-form" id="dep-form" novalidate autocomplete="off">
        <div class="dp-step">
          <div class="mdl-body md-body"><?= md_rows(['d2' => 'yes', 'd3' => 'yes'], false, true) ?></div>
        </div>
        <div class="dp-step" hidden>
          <div class="mdl-body md-body">
            <?php foreach ($roles as $i => $role): ?>
              <div class="dp-row">
                <span><?= ucwords(strtolower($role)) ?></span>
                <input name="cname[]" maxlength="80" placeholder="Full name" value="<?= e($crew[$i]['name'] ?? '') ?>">
                <input name="cage[]" type="number" inputmode="numeric" min="0" max="120" placeholder="Age" value="<?= e($crew[$i]['age'] ?? '') ?>">
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="alert bad md-err" hidden></div>
        <div class="md-foot"><span class="md-count"></span><button type="button" class="btn sm ghost md-cancel md-pair">Cancel</button><button type="button" class="btn sm md-next md-go-wide md-pair">Next</button><button type="submit" class="btn sm md-go md-go-wide md-pair" hidden>Submit and depart</button></div>
      </form>
    </div>
  </div>
  <script>
    (function() {
      var m = document.getElementById('dep-mdl'),
        f = document.getElementById('dep-form'),
        err = m.querySelector('.md-err'),
        cnt = m.querySelector('.md-count'),
        nextB = m.querySelector('.md-next'),
        goB = m.querySelector('.md-go'),
        backB = m.querySelector('.md-cancel'),
        ti = document.getElementById('dep-t'),
        sub = document.getElementById('dep-s'),
        steps = f.querySelectorAll('.dp-step'),
        step = 1,
        busy = false;

      function tally() {
        cnt.textContent = steps[0].querySelectorAll('.md-q input:checked').length + ' of ' + steps[0].querySelectorAll('.md-q').length + ' answered'
      }

      function say(t) {
        err.textContent = t || '';
        err.hidden = !t
      }

      function show(n) {
        step = n;
        steps[0].hidden = n !== 1;
        steps[1].hidden = n !== 2;
        nextB.hidden = n !== 1;
        goB.hidden = n !== 2;
        cnt.hidden = n !== 1;
        backB.textContent = n === 1 ? 'Cancel' : 'Back';
        ti.textContent = n === 1 ? 'Master\u2019s Declaration of Safe Departure' : 'Crew list';
        sub.textContent = n === 1 ? 'Declare the condition of your boat and its documents. Every item needs an answer.' : 'Fill in your crew for this trip. The boat captain is required.';
        say('')
      }

      function close() {
        m.classList.remove('open')
      }

      function toCrew() {
        var first = null;
        steps[0].querySelectorAll('.md-q').forEach(function(q) {
          var miss = !q.querySelector('input:checked');
          q.classList.toggle('miss', miss);
          if (miss && !first) first = q
        });
        if (first) {
          say('Please answer every item of the declaration.');
          first.scrollIntoView({
            block: 'center',
            behavior: 'smooth'
          });
          return
        }
        show(2);
        steps[1].querySelector('input').focus()
      }

      function crewError() {
        var n = steps[1].querySelectorAll('input[name="cname[]"]'),
          a = steps[1].querySelectorAll('input[name="cage[]"]'),
          extra = false;
        for (var i = 0; i < n.length; i++) {
          var nv = n[i].value.trim(),
            av = a[i].value.trim();
          if (i === 0 && (nv === '' || av === '')) return 'The boat captain needs a name and an age.';
          if (nv !== '' && (nv.length < 2 || !/^[0-9]+$/.test(av) || +av > 120)) return 'Check the crew names and ages (age 0 to 120).';
          if (nv === '' && av !== '') return 'Enter the name for every age you filled in.';
          if (i >= 2 && nv !== '') extra = true
        }
        return extra ? '' : 'Add at least 1 crew member (Crew 3 or 4) besides the boat captain and the motorman.'
      }
      document.addEventListener('click', function(e) {
        if (!e.target.closest('.dep-open')) return;
        show(1);
        tally();
        void m.offsetWidth;
        m.classList.add('open')
      });
      m.addEventListener('click', function(e) {
        if (e.target === m || e.target.classList.contains('x')) close();
        else if (e.target.classList.contains('md-cancel')) {
          if (step === 2) show(1);
          else close()
        } else if (e.target.classList.contains('md-next')) toCrew()
      });
      document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && m.classList.contains('open')) close()
      });
      f.addEventListener('change', function(e) {
        if (e.target.type === 'radio') {
          e.target.closest('.md-q').classList.remove('miss');
          say('');
          tally()
        }
      });
      f.addEventListener('submit', function(e) {
        e.preventDefault();
        if (busy) return;
        if (step === 1) {
          toCrew();
          return
        }
        var msg = crewError();
        if (msg) {
          say(msg);
          return
        }
        busy = true;
        goB.disabled = true;
        goB.textContent = 'Submitting\u2026';
        say('');
        fetch('depart_request.php', {
            method: 'POST',
            body: new FormData(f),
            credentials: 'same-origin'
          })
          .then(function(x) {
            return x.json()
          })
          .then(function(j) {
            if (j.ok) location.reload();
            else {
              say(j.error || 'Could not submit. Try again.');
              busy = false;
              goB.disabled = false;
              goB.textContent = 'Submit and depart'
            }
          })
          .catch(function() {
            say('Could not submit. Check your connection.');
            busy = false;
            goB.disabled = false;
            goB.textContent = 'Submit and depart'
          })
      })
    })();
  </script>
<?php endif; ?>
<?php if ($ready): ?>
  <script>
    setInterval(function() {
      if (document.hidden) return;
      fetch(location.href, {
        credentials: 'same-origin'
      }).then(function(r) {
        return r.text()
      }).then(function(h) {
        var d = new DOMParser().parseFromString(h, 'text/html');
        if (!d.querySelector('.mpage[data-ready="1"]')) location.reload()
      }).catch(function() {})
    }, 5000);
  </script>
<?php endif; ?>
<?php page_foot('operator');
