<?php
// PCG: the MDSD checklist and the PDIC (Pre-Departure Inspection Checklist). When Depart is pressed they open as two modals, one after the other
// (js/script.js loads both from here with ?ajax=1). This page also works on its own. The boat departs when both are submitted.
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

// The Yes / No / N/A choice for one question
function md_opts($k, $cur)
{
    $o = '';
    foreach (['yes' => 'Yes', 'no' => 'No', 'na' => 'N/A'] as $val => $lab)
        $o .= "<label class='md-o md-$val'><input type='radio' name='q[$k]' value='$val'" . (($cur[$k] ?? '') === $val ? ' checked' : '') . "><span>$lab</span></label>";
    return "<div class='md-opts'>$o</div>";
}

// One question row
function md_row($k, $label, $cur, $sub = false)
{
    return "<div class='md-q" . ($sub ? ' md-sub' : '') . "' role='radiogroup' aria-labelledby='md-$k'><div class='md-t' id='md-$k'>$label</div>" . md_opts($k, $cur) . "</div>";
}

// Every question of the MDSD checklist
function md_rows($cur)
{
    $h = "<h2 class='md-h'>Inspection findings</h2>";
    foreach (mdsd_items() as $it) $h .= md_row($it[0], "<b>" . $it[0] . ")</b> " . e($it[1]), $cur);
    $h .= "<h2 class='md-h'>Attached documents</h2>";
    foreach (mdsd_docs() as $d) $h .= md_row($d[0], "<b>" . $d[1] . "</b> " . e($d[2]), $cur, true);
    return $h;
}

$c = db()->prepare("SELECT id, motorbanca_name, capacity, status, cluster_id FROM motorbancas WHERE id=?");
$c->execute([$mid]);
$m = $c->fetch();
// Only the boat that is ON QUEUE can depart (the same rule as before).
if (!$m || $m['status'] !== 'Queued' || on_queue_id($m['cluster_id']) != $mid) {
    if ($ajax) md_json(['ok' => false, 'error' => 'This boat is no longer on the queue. Reload the page.']);
    header('Location: ' . $back);
    exit;
}
$tq = db()->prepare("SELECT id FROM trips WHERE motorbanca_id=? AND status='Queued' ORDER BY id DESC LIMIT 1");
$tq->execute([$mid]);
$qt = (int)$tq->fetchColumn();
$np = 0;
$pre = [];   // MDSD answers that are already known from the system
if ($qt) {
    $pc = db()->prepare("SELECT COUNT(*) FROM passengers WHERE trip_id=?");
    $pc->execute([$qt]);
    $np = (int)$pc->fetchColumn();
    if (trip_crew_get($qt)) $pre['d2'] = 'yes';   // Crew List
    if ($np > 0) $pre['d3'] = 'yes';              // Passenger Main Manifest
}
$err = '';
$q = (array)($_POST['q'] ?? []);
$p = (array)($_POST['p'] ?? []);
$r = (array)($_POST['r'] ?? []);   // PDIC remarks
$ins = trim((string)($_POST['inspector'] ?? ''));
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ans = [];
    foreach (mdsd_keys() as $k) {
        $a = $q[$k] ?? '';
        if (in_array($a, ['yes', 'no', 'na'], true)) $ans[$k] = $a;
    }
    $pa = [];
    foreach (pdic_numbers() as $n) {
        $a = $p[$n] ?? '';
        if (in_array($a, ['yes', 'no'], true)) $pa[$n] = $a;
    }
    $pr = [];   // the remarks that were typed (kept short)
    foreach (pdic_numbers() as $n) {
        $t = trim(preg_replace('/\s+/', ' ', (string)($r[$n] ?? '')));
        if ($t !== '') $pr[$n] = mb_substr($t, 0, 120);
    }
    if (count($ans) < count(mdsd_keys()) || count($pa) < count(pdic_numbers())) {
        $err = 'Please answer every item.';
    } elseif ($ins === '' || mb_strlen($ins) > 100) {
        $err = 'Please enter the name of the inspector.';
    } else {
        // When the inspection started: the moment the checklist was opened (sent back by the page), never in the future
        $st = strtotime((string)($_POST['started'] ?? ''));
        $started = ($st && $st <= time()) ? date('Y-m-d H:i:s', $st) : date('Y-m-d H:i:s');
        set_status($mid, 'Departed');
        $t = db()->prepare("SELECT id FROM trips WHERE motorbanca_id=? AND status='Departed' ORDER BY id DESC LIMIT 1");
        $t->execute([$mid]);
        $tid = (int)$t->fetchColumn();
        if ($tid) {   // the MDSD and PDIC are printed later from the "Departed" window or from Trip History
            mdsd_save($tid, $ans);
            pdic_save($tid, $pa, $ins, $started, $pr);
        }
        if ($ajax) md_json(['ok' => true, 'trip' => $tid]);
        header('Location: ' . $back);
        exit;
    }
    if ($ajax) md_json(['ok' => false, 'error' => $err]);
}
$cur = $_SERVER['REQUEST_METHOD'] === 'POST' ? $q : $pre;   // keep what was ticked if something was missed
$pcur = $_SERVER['REQUEST_METHOD'] === 'POST' ? $p : [];
$sub = $m['motorbanca_name'] . ' · ' . $np . ' of ' . (int)$m['capacity'] . ' passengers';
if ($ajax) md_json([
    'title' => "Master's Declaration of Safe Departure",
    'sub' => $sub,
    'trip' => (int)$qt,
    'html' => md_rows($cur),
    'pdic' => pdic_rows($pcur),
    'started' => date('Y-m-d H:i:s'),
]);

page_head('MDSD and PDIC', 'admin'); ?>
<h1>Safe departure</h1>
<p class="md-sum"><?= e($sub) ?></p>
<?php if ($err): ?><div class="alert bad"><?= e($err) ?></div><?php endif; ?>
<form method="post" class="md-form md-page">
    <input type="hidden" name="mid" value="<?= $mid ?>">
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <input type="hidden" name="started" value="<?= e(date('Y-m-d H:i:s')) ?>">
    <?= md_rows($cur) ?>
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
