<?php
require_once '../config/layout.php';
require_once '../config/announcements.php';
need('admin');
ann_ensure();
susp_ensure();
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
        $k = $_POST['kind'] ?? 'info';
        $act = in_array($_POST['act'] ?? '', ['suspend', 'resume'], true) ? $_POST['act'] : null;
        if (!isset(ANN_KINDS[$k])) $k = 'info';
        if ($t === '' || $b === '' || mb_strlen($t) > 120 || mb_strlen($b) > 2000) $err = 'Please enter a title and a message (message up to 2000 characters).';
        else {
            db()->prepare("INSERT INTO announcements(kind,title,body,act) VALUES(?,?,?,?)")->execute([$k, $t, $b, $act]);
            if ($act === 'suspend') suspend_all_boats();
            $_SESSION['ann_msg'] = $act === 'suspend' ? 'Travel suspended. All boats on queue have departed and new trips are blocked.' : ($act === 'resume' ? 'Travel resumed. Operators can start new trips again.' : 'Announcement posted to operators and passengers.');
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
?>
<h1>Announcements</h1>
<?php if ($err): ?><div class="alert err"><?= e($err) ?></div><?php endif; ?>
<div class="ann-wrap">
    <div class="card ann-compose">
        <div class="ann-tpl">
            <?php foreach ($tpl as $i => $t): ?><button type="button" class="ann-chip k-<?= $t[1] ?>" data-i="<?= $i ?>"><b><?= e($t[0]) ?></b><span class="ann-d"><?= e($t[3]) ?></span></button><?php endforeach; ?>
        </div>
        <form method="post" id="annf">
            <input type="hidden" name="act" id="a-act" value="">
            <div class="alert bad" id="a-actn" hidden style="margin:0 0 6px"></div>
            <label>Type</label>
            <select name="kind" id="a-kind"><?php foreach (ANN_KINDS as $k => $l): ?><option value="<?= $k ?>" <?= ($_POST['kind'] ?? 'info') === $k ? ' selected' : '' ?>><?= $l ?></option><?php endforeach; ?></select>
            <div class="ann-seg" id="a-seg"><?php foreach (ANN_KINDS as $k => $l): ?><button type="button" class="k-<?= $k ?>" data-k="<?= $k ?>"><i></i><?= $l ?></button><?php endforeach; ?></div>
            <label>Title</label>
            <input name="title" id="a-title" maxlength="120" required value="<?= e($_POST['title'] ?? '') ?>">
            <label>Message <em class="ann-cnt" id="a-cnt">0 / 2000</em></label>
            <textarea name="body" id="a-body" rows="5" maxlength="2000" required><?= e($_POST['body'] ?? '') ?></textarea>
            <button class="btn" style="margin-top:12px">Post announcement</button>
        </form>
    </div>

</div>
<h2 class="pub-h2" style="margin-top:22px"><i class="dot"></i>Posted announcements</h2>
<div class="ann-grid"><?php ann_list(true); ?></div>
<script>
    (function() {
        var T = <?= json_encode($tpl, JSON_UNESCAPED_UNICODE) ?>;
        document.querySelectorAll('.ann-chip').forEach(function(b) {
            b.addEventListener('click', function() {
                var t = T[b.dataset.i];
                document.getElementById('a-kind').value = t[1];
                var a = t[4] || '';
                document.getElementById('a-act').value = a;
                var n = document.getElementById('a-actn');
                n.hidden = !a;
                n.classList.toggle('good', a === 'resume');
                n.textContent = a === 'suspend' ? 'Posting this will make every boat on queue depart and block all new trips until a Resumption of travel notice is posted.' : (a === 'resume' ? 'Posting this lets operators start new trips again.' : '');
                document.getElementById('a-title').value = t[2];
                document.getElementById('a-body').value = t[3];
                document.getElementById('a-title').focus()
            })
        });
    })();
    (function() {
        var k = document.getElementById('a-kind'),
            seg = document.getElementById('a-seg'),
            b = document.getElementById('a-body'),
            c = document.getElementById('a-cnt');
        if (!seg) return;

        function u() {
            seg.querySelectorAll('button').forEach(function(x) {
                x.classList.toggle('on', x.dataset.k === k.value)
            });
            c.textContent = b.value.length + ' / 2000'
        }
        seg.addEventListener('click', function(e) {
            var x = e.target.closest('button');
            if (x) {
                k.value = x.dataset.k;
                u()
            }
        });
        b.addEventListener('input', u);
        k.addEventListener('change', u);
        document.querySelectorAll('.ann-chip').forEach(function(x) {
            x.addEventListener('click', function() {
                setTimeout(u, 0)
            })
        });
        u();
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