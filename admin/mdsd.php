<?php
require_once '../config/layout.php';
require_once '../config/pdic_helpers.php';
need('admin');
$ajax = isset($_REQUEST['ajax']);
$mid = (int)($_REQUEST['mid'] ?? 0);
$ref = (string)($_REQUEST['back'] ?? '');
$back = strpos($ref, BASE . '/admin/') === 0 ? $ref : 'dashboard.php';

function md_json($data)
{
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

mb_schema();
$c = db()->prepare("SELECT id, motorbanca_name, capacity, status, cluster_id, depart_req FROM motorbancas WHERE id=?");
$c->execute([$mid]);
$m = $c->fetch();
if (!$m || $m['status'] !== 'Queued' || on_queue_id($m['cluster_id']) != $mid) {
    if ($ajax) md_json(['ok' => false, 'error' => 'This boat is no longer on the queue. Reload the page.']);
    header('Location: ' . $back);
    exit;
}
if (empty($m['depart_req']) && !travel_suspended()) {
    if ($ajax) md_json(['ok' => false, 'error' => 'The operator has not marked this boat ready to depart yet.']);
    header('Location: ' . $back);
    exit;
}
$tq = db()->prepare("SELECT id FROM trips WHERE motorbanca_id=? AND status='Queued' ORDER BY id DESC LIMIT 1");
$tq->execute([$mid]);
$qt = (int)$tq->fetchColumn();
$np = 0;
if ($qt) {
    $pc = db()->prepare("SELECT COUNT(*) FROM passengers WHERE trip_id=?");
    $pc->execute([$qt]);
    $np = (int)$pc->fetchColumn();
}
$err = '';
$p = (array)($_POST['p'] ?? []);
$r = (array)($_POST['r'] ?? []);
$ins = trim((string)($_POST['inspector'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $pa = [];
    foreach (pdic_numbers() as $n) {
        $a = $p[$n] ?? '';
        if (in_array($a, ['yes', 'no'], true)) $pa[$n] = $a;
    }
    $pr = [];
    foreach (pdic_numbers() as $n) {
        $t = trim(preg_replace('/\s+/', ' ', (string)($r[$n] ?? '')));
        if ($t !== '') $pr[$n] = mb_substr($t, 0, 120);
    }
    if (count($pa) < count(pdic_numbers())) {
        $err = 'Please answer every item.';
    } elseif ($ins === '' || mb_strlen($ins) > 100) {
        $err = 'Please enter the name of the inspector.';
    } else {
        $st = strtotime((string)($_POST['started'] ?? ''));
        $started = ($st && $st <= time()) ? date('Y-m-d H:i:s', $st) : date('Y-m-d H:i:s');
        set_status($mid, 'Departed');
        $t = db()->prepare("SELECT id FROM trips WHERE motorbanca_id=? AND status='Departed' ORDER BY id DESC LIMIT 1");
        $t->execute([$mid]);
        $tid = (int)$t->fetchColumn();
        if ($tid) {
            pdic_save($tid, $pa, $ins, $started, $pr);
        }
        if ($ajax) md_json(['ok' => true, 'trip' => $tid]);
        header('Location: ' . $back);
        exit;
    }
    if ($ajax) md_json(['ok' => false, 'error' => $err]);
}
$pcur = $_SERVER['REQUEST_METHOD'] === 'POST' ? $p : [];
$sub = $m['motorbanca_name'] . ' · ' . $np . ' of ' . (int)$m['capacity'] . ' passengers';
if ($ajax) md_json([
    'title' => 'Pre-Departure Inspection Checklist',
    'sub' => $sub,
    'trip' => (int)$qt,
    'mdsd' => $qt ? (bool)mdsd_get($qt) : false,
    'pdic' => pdic_rows($pcur),
    'started' => date('Y-m-d H:i:s'),
]);

page_head('PDIC', 'admin'); ?>
<h1>Pre-departure inspection</h1>
<p class="md-sum"><?= e($sub) ?></p>
<?php if ($err): ?><div class="alert bad"><?= e($err) ?></div><?php endif; ?>
<form method="post" class="md-form md-page">
    <input type="hidden" name="mid" value="<?= $mid ?>">
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <input type="hidden" name="started" value="<?= e(date('Y-m-d H:i:s')) ?>">
    <?= pdic_rows($pcur) ?>
    <div class="md-ins">
        <label for="pd-ins">Name of inspector</label>
        <input id="pd-ins" name="inspector" type="text" maxlength="100" autocomplete="off" value="<?= e($ins) ?>">
    </div>
    <div class="md-act">
        <button class="btn" type="submit">Depart motorbanca</button>
    </div>
</form>
<?php page_foot('admin');
