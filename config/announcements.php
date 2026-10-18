<?php
require_once __DIR__ . '/database.php';

// ---------- Announcements (posted by the PCG admin, shown to operators and passengers) ----------
if (!defined('ANN_KINDS')) define('ANN_KINDS', ['danger' => 'Warning', 'warn' => 'Advisory', 'ok' => 'Good news', 'info' => 'Notice']);
if (!function_exists('ann_ensure')) {
    function ann_ensure()
    {
        static $done;
        if ($done) return;
        $done = true;
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS announcements (
        id INT AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(10) NOT NULL DEFAULT 'info',
        title VARCHAR(120) NOT NULL, body TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB");
        } catch (PDOException $x) {
        }
    }
    function ann_all()
    {
        ann_ensure();
        try {
            return db()->query("SELECT * FROM announcements ORDER BY id DESC LIMIT 100")->fetchAll();
        } catch (PDOException $x) {
            return [];
        }
    }
    // Strip shown on other pages while a recent announcement (last 24 hours) is out.
    function ann_banner($url)
    {
        ann_ensure();
        try {
            $a = db()->query("SELECT * FROM announcements WHERE created_at >= (NOW() - INTERVAL 24 HOUR) ORDER BY id DESC LIMIT 1")->fetch();
        } catch (PDOException $x) {
            return '';
        }
        if (!$a) return '';
        return "<a class='ann-strip k-" . e($a['kind']) . "' href='" . e($url) . "'><b>" . e($a['title']) . "</b><span>View " . (strpos($_SERVER['PHP_SELF'] ?? '', '/passenger/') !== false ? 'notification' : 'announcement') . " &rsaquo;</span></a>";
    }
    function ann_list($admin = false)
    {
        $rows = ann_all();
        if (!$rows) {
            echo "<div class='ann-empty'><p class='muted' style='margin:0'>No " . (strpos($_SERVER['PHP_SELF'] ?? '', '/passenger/') !== false ? 'notifications' : 'announcements') . " yet.</p></div>";
            return;
        }
        // Passenger / operator / admin: latest 10, split into Today and Earlier, fixed-height rows, unread vs read look, tap to read the whole notice in a modal.
        echo "<link rel='stylesheet' href='" . (function_exists('asset') ? asset('css/notif.css') : BASE . '/css/notif.css') . "'>";
        $all = isset($_GET['all']);
        $more = count($rows) > 10;
        $shown = ($more && !$all) ? array_slice($rows, 0, 10) : $rows;
        try {
            $today = (string)db()->query('SELECT CURDATE()')->fetchColumn();
        } catch (PDOException $x) {
            $today = date('Y-m-d');
        }
        $grp = ['Today' => [], 'Earlier' => []];
        foreach ($shown as $a) $grp[substr($a['created_at'], 0, 10) === $today ? 'Today' : 'Earlier'][] = $a;
        foreach ($grp as $label => $list) {
            if (!$list) continue;
            echo "<h2 class='ann-sec'>$label</h2>";
            $last = count($list) - 1;
            foreach ($list as $n => $a) {
                $k = isset(ANN_KINDS[$a['kind']]) ? $a['kind'] : 'info';
                $when = date('M j, Y \a\t g:i A', strtotime($a['created_at']));
                echo "<article class='ann ann-m unread k-$k" . ($n === $last ? ' ann-last' : '') . ($admin ? ' ann-adm' : '') . "' data-id='" . (int)$a['id'] . "' tabindex='0' role='button' aria-label='Open notification: " . e($a['title']) . "'><div class='ann-h'><span class='ann-tag'>" . ANN_KINDS[$k] . "</span><time>$when</time></div><h3>" . e($a['title']) . "</h3><p>" . nl2br(e($a['body'])) . "</p>" . ($admin ? "<form method='post' class='ann-del'><input type='hidden' name='del' value='" . (int)$a['id'] . "'><button type='submit' class='ann-x' aria-label='Delete announcement'><svg class='ico' viewBox='0 0 24 24' fill='none' stroke='currentColor' stroke-width='1.8' stroke-linecap='round' stroke-linejoin='round'><path d='M4 7h16M10 11v6M14 11v6M6 7l1 13h10l1-13M9 7V4h6v3'/></svg></button></form>" : '') . "</article>";
            }
        }
        if ($more) {
            $self = e(basename($_SERVER['PHP_SELF'] ?? ''));
            echo "<div class='ann-more'>" . ($all ? "<a href='$self'>Show latest 10 only</a>" : "<a href='$self?all=1'>See all previous notifications</a>") . "</div>";
        }
        echo "<div class='mdl' id='ann-mdl' role='dialog' aria-modal='true' aria-labelledby='ann-mt'><div class='mdl-box wide ann-box'><div class='mdl-head'><div class='ann-mk' id='ann-mk'><span class='ann-tag' id='ann-mtag'></span><h3 id='ann-mt'></h3><p class='sub' id='ann-mtime'></p></div><button type='button' class='x' aria-label='Close'>&times;</button></div><div class='ann-mb' id='ann-mb'></div></div></div>";
        echo <<<'JS'
<script>(function(){
var K='ann_read_v1',R=[];
try{R=JSON.parse(localStorage.getItem(K))||[];}catch(e){}
if(!Array.isArray(R))R=[];
var L=document.querySelectorAll('.ann-m'),m=document.getElementById('ann-mdl'),last=null;
if(!m)return;
document.body.appendChild(m);
L.forEach(function(a){if(R.indexOf(a.getAttribute('data-id'))>-1)a.classList.remove('unread');});
function openN(a){
  last=a;
  var kc=(a.className.match(/k-\w+/)||['k-info'])[0];
  document.getElementById('ann-mk').className='ann-mk '+kc;
  document.getElementById('ann-mtag').textContent=a.querySelector('.ann-tag').textContent;
  document.getElementById('ann-mt').textContent=a.querySelector('h3').textContent;
  document.getElementById('ann-mtime').textContent=a.querySelector('time').textContent;
  document.getElementById('ann-mb').innerHTML=a.querySelector('p').innerHTML;   // already escaped by the server
  a.classList.remove('unread');
  var id=a.getAttribute('data-id');
  if(R.indexOf(id)<0){R.push(id);if(R.length>300)R=R.slice(-300);try{localStorage.setItem(K,JSON.stringify(R));}catch(e){}}
  void m.offsetWidth;m.classList.add('open');
  m.querySelector('.x').focus();
}
function closeN(){m.classList.remove('open');if(last)last.focus();}
L.forEach(function(a){
  a.addEventListener('click',function(e){if(e.target.closest('.ann-del'))return;openN(a);});
  a.addEventListener('keydown',function(e){if(e.target.closest('.ann-del'))return;if(e.key==='Enter'||e.key===' '){e.preventDefault();openN(a);}});
});
m.addEventListener('click',function(e){if(e.target===m||e.target.closest('.x'))closeN();});
document.addEventListener('keydown',function(e){if(e.key==='Escape'&&m.classList.contains('open'))closeN();});
})();</script>
JS;
    }
}

// ---- Travel suspension: the newest "Suspension of travel" / "Resumption of travel" notice decides the state ----
if (!function_exists('susp_ensure')) {
    function susp_ensure()
    {
        static $d;
        if ($d) return;
        $d = true;
        try {
            db()->exec("CREATE TABLE IF NOT EXISTS announcements (id INT AUTO_INCREMENT PRIMARY KEY, kind VARCHAR(10) NOT NULL DEFAULT 'info', title VARCHAR(120) NOT NULL, body TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB");
            if (!db()->query("SHOW COLUMNS FROM announcements LIKE 'act'")->fetch()) db()->exec("ALTER TABLE announcements ADD COLUMN act VARCHAR(10) NULL");
        } catch (PDOException $x) {
        }
    }
    function travel_suspended()
    {
        static $v;
        if ($v === null) {
            susp_ensure();
            try {
                $v = db()->query("SELECT act FROM announcements WHERE act IS NOT NULL ORDER BY id DESC LIMIT 1")->fetchColumn() === 'suspend';
            } catch (PDOException $x) {
                $v = false;
            }
        }
        return $v;
    }
    // A boat leaves the queue and its waiting trip is cancelled (it never departs).
    function cancel_queued_trip($mid)
    {
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE motorbancas SET status='Not Queued' WHERE id=?")->execute([$mid]);
        $pdo->prepare("UPDATE trips SET status='Cancelled' WHERE motorbanca_id=? AND status IN ('Queued','Inspection')")->execute([$mid]);
        $pdo->commit();
    }
    // Admin posted a suspension. The boat that is ON QUEUE in each cluster is NOT departed here: the PCG must depart and
    // check it (MDSD and PDIC) right away. Boats in NEXT TRIP / WAITING and boats waiting for inspection are cancelled.
    function suspend_all_boats()
    {
        $rows = live_rows();
        foreach (db()->query("SELECT id FROM clusters")->fetchAll() as $c) {
            [$dep, $q] = split_cluster($rows, $c['id']);
            foreach ($q as $i => $b) if ($dep || $i > 0) cancel_queued_trip((int)$b['id']);   // keep only the boat that is ON QUEUE
        }
        foreach (db()->query("SELECT id FROM motorbancas WHERE status='Inspection'")->fetchAll() as $b) set_status((int)$b['id'], 'Not Queued');
    }
    // Red notice for the admin while travel is suspended and a boat is still on queue (it has to be departed and checked).
    function susp_notice_html()
    {
        if (!travel_suspended()) return;
        $r = db()->query("SELECT m.motorbanca_name n, c.name cn FROM motorbancas m JOIN clusters c ON c.id=m.cluster_id WHERE m.status='Queued' ORDER BY m.queued_at, m.id")->fetchAll();
        if (!$r) return;
        $names = implode(', ', array_map(fn($x) => '<b>' . e($x['n']) . '</b> (' . e($x['cn']) . ')', $r));
        echo "<div class='alert bad susp-note' style='margin:0 0 18px'><b>Travel is suspended.</b> Depart and check the MDSD and PDIC of the boat still on queue right away: $names. <a href='" . BASE . "/admin/queue.php' style='font-weight:700;color:inherit;text-decoration:underline'>Go to the queue &rsaquo;</a></div>";
    }
}

// ---- Home-page strip: shows once for the newest announcement; opening the Announcements page dismisses it ----
if (!function_exists('ann_seen_mark')) {
    function ann_seen_mark()
    {
        susp_ensure();
        try {
            $id = (int)db()->query("SELECT MAX(id) FROM announcements")->fetchColumn();
        } catch (PDOException $x) {
            return;
        }
        if ($id && !headers_sent()) setcookie('ann_seen', (string)$id, time() + 86400 * 60, '/');
        $_COOKIE['ann_seen'] = (string)$id;
    }
    function ann_strip($url)
    {
        susp_ensure();
        try {
            $a = db()->query("SELECT * FROM announcements WHERE created_at >= (NOW() - INTERVAL 24 HOUR) ORDER BY id DESC LIMIT 1")->fetch();
        } catch (PDOException $x) {
            return '';
        }
        if (!$a || (int)($_COOKIE['ann_seen'] ?? 0) >= (int)$a['id']) return '';
        return "<a class='ann-strip k-" . e($a['kind']) . "' href='" . e($url) . "'><b>" . e($a['title']) . "</b><span>View " . (strpos($_SERVER['PHP_SELF'] ?? '', '/passenger/') !== false ? 'notification' : 'announcement') . " &rsaquo;</span></a>";
    }
}
