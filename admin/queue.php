<?php
require_once '../config/layout.php';
need('admin');
page_head('Queue / Trips', 'admin');
?>
<h1>Queue / Trips</h1>
<p class="lead">Tap <b>Depart</b> on the boat that is ON QUEUE when it leaves.</p>
<div class="clusters" data-live="<?= BASE ?>/api/live.php?v=admin"><?php render_live(true); ?></div>
<?php page_foot('admin');
