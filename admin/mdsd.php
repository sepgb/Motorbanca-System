<?php
// PCG: answer the MDSD checklist. The boat departs when this is submitted, then the MDSD is generated.
require_once '../config/layout.php';
require_once '../config/mdsd_helpers.php';
need('admin');
$mid = (int)($_REQUEST['mid'] ?? 0);
$ref = (string)($_REQUEST['back'] ?? '');
$back = strpos($ref, BASE . '/admin/') === 0 ? $ref : 'dashboard.php';
$c = db()->prepare("SELECT id, motorbanca_name, capacity, status, cluster_id FROM motorbancas WHERE id=?");
$c->execute([$mid]);
$m = $c->fetch();
// Only the boat that is ON QUEUE can depart (the same rule as before).
if (!$m || $m['status'] !== 'Queued' || on_queue_id($m['cluster_id']) != $mid) {
    header('Location: ' . $back);
    exit;
}
$tq = db()->prepare("SELECT id FROM trips WHERE motorbanca_id=? AND status='Queued' ORDER BY id DESC LIMIT 1");
$tq->execute([$mid]);
$qt = (int)$tq->fetchColumn();
$np = 0;
$pre = [];   // answers that are already known from the system
if ($qt) {
    $pc = db()->prepare("SELECT COUNT(*) FROM passengers WHERE trip_id=?");
    $pc->execute([$qt]);
    $np = (int)$pc->fetchColumn();
    if (trip_crew_get($qt)) $pre['d2'] = 'yes';   // Crew List
    if ($np > 0) $pre['d3'] = 'yes';              // Passenger Main Manifest
}
$err = '';
$q = (array)($_POST['q'] ?? []);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ans = [];
    foreach (mdsd_keys() as $k) {
        $a = $q[$k] ?? '';
        if (in_array($a, ['yes', 'no', 'na'], true)) $ans[$k] = $a;
    }
    if (count($ans) < count(mdsd_keys())) {
        $err = 'Please answer every item with Yes, No or N/A.';
    } else {
        set_status($mid, 'Departed');
        $t = db()->prepare("SELECT id FROM trips WHERE motorbanca_id=? AND status='Departed' ORDER BY id DESC LIMIT 1");
        $t->execute([$mid]);
        $tid = (int)$t->fetchColumn();
        if ($tid) {
            mdsd_save($tid, $ans);
            header('Location: mdsd_print.php?trip=' . $tid . '&back=' . urlencode($back));
        } else {
            header('Location: ' . $back);
        }
        exit;
    }
}
$cur = $_SERVER['REQUEST_METHOD'] === 'POST' ? $q : $pre;   // keep what was ticked if something was missed

// The Yes / No / N/A choice for one question
function md_opts($k, $cur)
{
    $o = '';
    foreach (['yes' => 'Yes', 'no' => 'No', 'na' => 'N/A'] as $val => $lab)
        $o .= "<label class='md-o md-$val'><input type='radio' name='q[$k]' value='$val' required" . (($cur[$k] ?? '') === $val ? ' checked' : '') . "><span>$lab</span></label>";
    return "<div class='md-opts'>$o</div>";
}

page_head('MDSD', 'admin'); ?>
<h1>Master's Declaration of Safe Departure</h1>
<p class="md-sum"><b><?= e($m['motorbanca_name']) ?></b> &middot; <?= $np ?> of <?= (int)$m['capacity'] ?> passengers</p>
<?php if ($err): ?><div class="alert bad"><?= e($err) ?></div><?php endif; ?>
<form method="post" class="md-form">
    <input type="hidden" name="mid" value="<?= $mid ?>">
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <div class="md-top">
        <p class="md-note">Answer every item. The boat departs when you submit, and the MDSD is created.</p>
        <button type="button" class="btn ghost" id="md-yes">Mark all Yes</button>
    </div>
    <h2 class="md-h">Findings of the verification and inspection</h2>
    <?php foreach (mdsd_items() as $it): ?>
        <div class="md-q" role="radiogroup" aria-labelledby="md-<?= $it[0] ?>">
            <div class="md-t" id="md-<?= $it[0] ?>"><b><?= $it[0] ?>)</b> <?= e($it[1]) ?></div>
            <?= md_opts($it[0], $cur) ?>
        </div>
    <?php endforeach; ?>
    <?php foreach (mdsd_docs() as $d): ?>
        <div class="md-q sub" role="radiogroup" aria-labelledby="md-<?= $d[0] ?>">
            <div class="md-t" id="md-<?= $d[0] ?>"><b><?= $d[1] ?></b> <?= e($d[2]) ?></div>
            <?= md_opts($d[0], $cur) ?>
        </div>
    <?php endforeach; ?>
    <div class="md-act">
        <a class="btn ghost" href="<?= e($back) ?>">Cancel</a>
        <button class="btn" type="submit">Depart and create MDSD</button>
    </div>
</form>
<script>
    document.getElementById('md-yes').addEventListener('click', function() {
        document.querySelectorAll('.md-yes input').forEach(function(i) {
            i.checked = true
        })
    })
</script>
<?php page_foot('admin');
