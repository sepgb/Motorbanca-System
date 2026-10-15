<?php
// PCG: the MDSD checklist. When Depart is pressed it opens as a modal (js/script.js loads it from here with ?ajax=1);
// this page also works on its own. The boat departs when the checklist is submitted.
require_once '../config/layout.php';
require_once '../config/mdsd_helpers.php';
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
    return "<div class='md-q" . ($sub ? ' sub' : '') . "' role='radiogroup' aria-labelledby='md-$k'><div class='md-t' id='md-$k'>$label</div>" . md_opts($k, $cur) . "</div>";
}

// Every question of the checklist
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
        if ($tid) mdsd_save($tid, $ans);   // the MDSD is downloaded later from Trip History
        if ($ajax) md_json(['ok' => true]);
        header('Location: ' . $back);
        exit;
    }
    if ($ajax) md_json(['ok' => false, 'error' => $err]);
}
$cur = $_SERVER['REQUEST_METHOD'] === 'POST' ? $q : $pre;   // keep what was ticked if something was missed
$sub = $m['motorbanca_name'] . ' · ' . $np . ' of ' . (int)$m['capacity'] . ' passengers';
if ($ajax) md_json(['title' => "Master's Declaration of Safe Departure", 'sub' => $sub, 'html' => md_rows($cur)]);

page_head('MDSD', 'admin'); ?>
<h1>Master's Declaration of Safe Departure</h1>
<p class="md-sum"><?= e($sub) ?></p>
<?php if ($err): ?><div class="alert bad"><?= e($err) ?></div><?php endif; ?>
<form method="post" class="md-form md-page">
    <input type="hidden" name="mid" value="<?= $mid ?>">
    <input type="hidden" name="back" value="<?= e($back) ?>">
    <?= md_rows($cur) ?>
    <div class="md-act">
        <a class="btn ghost" href="<?= e($back) ?>">Cancel</a>
        <button class="btn" type="submit">Depart</button>
    </div>
</form>
<?php page_foot('admin');
