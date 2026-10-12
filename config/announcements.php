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
        return "<a class='ann-strip k-" . e($a['kind']) . "' href='" . e($url) . "'><b>" . e($a['title']) . "</b><span>View announcement &rsaquo;</span></a>";
    }
    function ann_list($admin = false)
    {
        $rows = ann_all();
        if (!$rows) {
            echo "<div class='card'><p class='muted' style='margin:0'>No announcements yet.</p></div>";
            return;
        }
        foreach ($rows as $a) {
            $k = isset(ANN_KINDS[$a['kind']]) ? $a['kind'] : 'info';
            echo "<article class='ann k-$k'><div class='ann-h'><span class='ann-tag'>" . ANN_KINDS[$k] . "</span><time>" . date('M j, Y \a\t g:i A', strtotime($a['created_at'])) . "</time></div><h3>" . e($a['title']) . "</h3><p>" . nl2br(e($a['body'])) . "</p>";
            if ($admin) echo "<form method='post' class='ann-del'><input type='hidden' name='del' value='" . (int)$a['id'] . "'><button class='btn sm ghost'>Delete</button></form>";
            echo "</article>";
        }
    }
}
