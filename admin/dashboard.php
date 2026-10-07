<?php
require_once '../config/layout.php';
need('admin');
$h = (int)date('G');
$greet = $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');
page_head('Dashboard', 'admin'); ?>
<h1><?= $greet ?>, PCG!</h1>
<p class="lead">Here is what is happening at the port right now.</p>
<div class="stats" data-live="<?= BASE ?>/api/live.php?v=stats"><?php stats_html(); ?></div>
<div class="dash" style="margin-top:26px">
    <div>
        <h2 class="sub" style="margin-top:0"><i class="dot"></i>Live queue</h2>
        <div class="clusters" data-live="<?= BASE ?>/api/live.php?v=passenger"><?php render_live(); ?></div>
    </div>
    <aside>
        <div class="card" data-live="<?= BASE ?>/api/live.php?v=fleet"><?php fleet_html(); ?></div>
        <div class="card">
            <h3>Quick actions</h3>
            <div class="grid">
                <a class="btn" href="queue.php">Manage queue</a>
                <a class="btn ghost" href="motorbancas.php">Register motorbanca</a>
                <a class="btn ghost" href="trips.php">View trip history</a>
            </div>
        </div>
    </aside>
</div>
<?php page_foot('admin');
