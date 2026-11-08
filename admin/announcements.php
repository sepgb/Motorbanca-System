<?php
require_once '../config/layout.php';
require_once '../config/announcements.php';
need('admin');
ann_ensure();
susp_ensure();
$auto = [
    'ok' => ['resume', 'resumption', 'lifted', 'cleared', 'now allowed', 'good news', 'normal operation'],
    'danger' => ['suspend', 'suspension', 'cancel', 'gale', 'storm', 'typhoon', 'signal no', 'prohibited', 'danger', 'warning', 'not allowed', 'no travel'],
    'warn' => ['advisory', 'limited', 'delay', 'caution', 'rough', 'strong wind', 'heavy rain', 'reminder', 'longer waiting'],
];
function ann_auto_kind($t, $b, $auto)
{
    foreach ([[$t, ['ok', 'danger', 'warn']], [$b, ['danger', 'warn', 'ok']]] as [$text, $order]) {
        $text = mb_strtolower($text);
        foreach ($order as $k) foreach ($auto[$k] as $w) if (strpos($text, $w) !== false) return $k;
    }
    return 'info';
}
$msg = $_SESSION['ann_msg'] ?? '';
unset($_SESSION['ann_msg']);
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['del'])) {
        db()->prepare("DELETE FROM announcements WHERE id=?")->execute([(int)$_POST['del']]);
        $_SESSION['ann_msg'] = 'Announcement deleted.';
        header('Location: announcements.php');
        exit;
    } else {
        $t = trim($_POST['title'] ?? '');
        $b = trim($_POST['body'] ?? '');
        $act = in_array($_POST['act'] ?? '', ['suspend', 'resume'], true) ? $_POST['act'] : null;
        $k = ann_auto_kind($t, $b, $auto);
        if ($t === '' || $b === '' || mb_strlen($t) > 120 || mb_strlen($b) > 2000) $err = 'Please enter a title and a message (message up to 2000 characters).';
        else {
            db()->prepare("INSERT INTO announcements(kind,title,body,act) VALUES(?,?,?,?)")->execute([$k, $t, $b, $act]);
            if ($act === 'suspend') suspend_all_boats();
            $_SESSION['ann_msg'] = $act === 'suspend' ? 'Travel suspended. Boats waiting for their next trip were cancelled and new trips are blocked. Depart and check the boat still on queue right away.' : ($act === 'resume' ? 'Travel resumed. Operators can start new trips again.' : 'Announcement posted to operators and passengers.');
            header('Location: announcements.php');
            exit;
        }
    }
}
$tpl = [
    ['Suspension of travel', 'danger', 'Suspension of Travel', "Public Storm Warning Signal No. 1 is now raised over Binangonan. Under Coast Guard rules, Wind Signal No. 1 means travel is automatically suspended. All motorbanca trips from Binangonan port are suspended until the signal is lifted. Operators must not depart. Passengers are advised to stay safe and wait for the next announcement.", 'suspend'],
    ['Gale warning', 'danger', 'Gale Warning', "A gale warning is in effect over Laguna de Bay. Small sea craft are advised not to venture out. Trips may be suspended at the discretion of the Coast Guard."],
    ['Resumption of travel', 'ok', 'Travel Resumes', "Weather conditions have improved and the wind signal has been lifted. Motorbanca trips from Binangonan port are now allowed to resume. Operators may start the inspection and queue process again.", 'resume'],
    ['Limited trips', 'warn', 'Limited Trips', "Due to bad weather, only a few motorbancas are queuing at the port. Expect fewer trips and longer waiting times. Passengers are advised to wait for the Coast Guard's go signal and wear life jackets at all times."],
    ['Fare update', 'info', 'Fare Update', "The fare rates have been updated. Please check the Fares page for the new amounts."],
];
page_head('Announcements', 'admin');
$rows = ann_all();
$all = isset($_GET['all']);
$more = count($rows) > 4;
$shown = $all ? $rows : array_slice($rows, 0, 4);
$ic = [
    'danger' => '<path d="M12 3l10 18H2z"/><path d="M12 10v5M12 18h.01"/>',
    'warn' => '<circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/>',
    'ok' => '<circle cx="12" cy="12" r="9"/><path d="M8 12.5l3 3 5-6"/>',
    'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
];
$svg = fn($k) => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $ic[$k] . '</svg>';
?>
<h1>Announcements</h1>
<?php if ($err): ?><div class="alert bad"><?= e($err) ?></div><?php endif; ?>
<div class="ax ax-grid">
    <form method="post" id="annf" class="ax-comp">
        <input type="hidden" name="act" id="a-act" value="">
        <div class="ax-cb">
            <div class="ax-qs">
                <span>Quick start</span>
                <div class="ax-chips">
                    <?php foreach ($tpl as $i => $t): ?><button type="button" class="ax-k-<?= $t[1] ?>" data-i="<?= $i ?>"><i><?= $svg($t[1]) ?></i><span><?= e($t[0]) ?></span></button><?php endforeach; ?>
                </div>
            </div>
            <div class="ax-note" id="a-actn" hidden><i>!</i><span></span></div>
            <label for="a-title">Title</label>
            <input class="ax-title" name="title" id="a-title" maxlength="120" required placeholder="What is this announcement about?" autocomplete="off" value="<?= e($_POST['title'] ?? '') ?>">
            <label for="a-body" class="ax-l2">Context</label>
            <textarea class="ax-body" name="body" id="a-body" rows="6" maxlength="2000" required placeholder="Add the details operators and passengers need to know"><?= e($_POST['body'] ?? '') ?></textarea>
        </div>
        <footer class="ax-foot">
            <span class="ax-cnt" id="a-cnt">0 / 2000</span>
            <button class="ax-post">Post announcement</button>
        </footer>
    </form>
    <section class="ax-side">
        <h2 class="ax-h">Recents</h2>
        <?php if (!$rows): ?>
            <p class="ax-none">Nothing has been announced yet.</p>
        <?php endif; ?>
        <div class="ax-tl">
            <?php foreach ($shown as $a):
                $k = isset(ANN_KINDS[$a['kind']]) ? $a['kind'] : 'info'; ?>
                <article class="ax-e ax-k-<?= $k ?>">
                    <span class="ax-mk"><?= $svg($k) ?></span>
                    <div class="ax-eb">
                        <div class="ax-em"><b><?= e(ANN_KINDS[$k]) ?></b><time><?= date('M j, Y \a\t g:i A', strtotime($a['created_at'])) ?></time></div>
                        <h4><?= e($a['title']) ?></h4>
                        <p><?= e($a['body']) ?></p>
                    </div>
                    <form method="post" class="ann-del"><input type="hidden" name="del" value="<?= (int)$a['id'] ?>"><button class="ax-del" aria-label="Delete announcement"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />
                            </svg></button></form>
                </article>
            <?php endforeach; ?>
        </div>
        <?php if ($more): ?>
            <p class="ax-more"><?= $all ? '<a href="announcements.php">Show latest 4 only</a>' : '<a href="announcements.php?all=1">See all announcements</a>' ?></p>
        <?php endif; ?>
    </section>
</div>
<script>
    (function() {
        var T = <?= json_encode($tpl, JSON_UNESCAPED_UNICODE) ?>,
            ti = document.getElementById('a-title'),
            bo = document.getElementById('a-body'),
            cnt = document.getElementById('a-cnt'),
            note = document.getElementById('a-actn');

        function sync() {
            cnt.textContent = bo.value.length + ' / 2000'
        }
        document.querySelectorAll('.ax-chips button').forEach(function(x) {
            x.addEventListener('click', function() {
                var t = T[x.dataset.i],
                    a = t[4] || '';
                document.getElementById('a-act').value = a;
                note.hidden = !a;
                note.className = 'ax-note' + (a === 'resume' ? ' ax-ok' : '');
                note.firstChild.textContent = a === 'resume' ? '\u2713' : '!';
                note.lastChild.textContent = a === 'suspend' ? 'Posting this will cancel the boats waiting for their next trip and block all new trips until a Resumption of travel notice is posted. The boat on queue is not departed automatically: you must depart and check it (PDIC) right away.' : (a === 'resume' ? 'Posting this lets operators start new trips again.' : '');
                ti.value = t[2];
                bo.value = t[3];
                sync();
                ti.focus()
            })
        });
        ti.addEventListener('input', sync);
        bo.addEventListener('input', sync);
        document.querySelectorAll('.ax-e').forEach(function(r) {
            r.addEventListener('click', function(e) {
                if (!e.target.closest('.ann-del')) r.classList.toggle('open')
            })
        });
        sync()
    })();
</script>
<div class="mdl" id="del-mdl" role="dialog" aria-modal="true" aria-labelledby="del-t">
    <div class="mdl-box">
        <div class="mdl-ic red"><svg class="ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
                <path d="M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3" />
            </svg></div>
        <h3 id="del-t">Delete announcement?</h3>
        <p>It will be removed for operators and passengers.</p>
        <div class="mdl-act"><button type="button" class="no">Cancel</button><button type="button" class="go red">Delete</button></div>
    </div>
</div>
<script>
    (function() {
        var m = document.getElementById('del-mdl'),
            f = null;
        if (!m) return;

        function close() {
            m.classList.remove('open')
        }
        document.querySelectorAll('input[name=del]').forEach(function(i) {
            var x = i.form;
            if (x) {
                x.removeAttribute('onsubmit');
                x.onsubmit = null;
                x.classList.add('ann-del')
            }
        });
        document.addEventListener('submit', function(e) {
            var x = e.target.closest('.ann-del');
            if (!x) return;
            e.preventDefault();
            f = x;
            m.classList.add('open');
            m.querySelector('.no').focus()
        });
        m.querySelector('.no').addEventListener('click', close);
        m.querySelector('.go').addEventListener('click', function() {
            if (f) {
                f.submit()
            }
        });
        m.addEventListener('click', function(e) {
            if (e.target === m) close()
        });
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') close()
        });
    })();
</script>
<?php page_foot('admin'); ?>