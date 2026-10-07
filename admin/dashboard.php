<?php
require_once '../config/layout.php';
need('admin');
$h = (int)date('G');
$greet = $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');
page_head('Dashboard', 'admin'); ?>
<h1><?= $greet ?>, officer</h1>
<p class="lead">Here is what is happening at the port right now.</p>
<div class="stats" data-live="<?= BASE ?>/api/live.php?v=stats"><?php stats_html(); ?></div>
<h2 class="sub"><i class="dot"></i>Live queue</h2>
<div class="clusters" data-live="<?= BASE ?>/api/live.php?v=passenger"><?php render_live(); ?></div>
<?php page_foot('admin');
