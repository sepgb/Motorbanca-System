<?php
require_once '../config/layout.php';
need('admin');
page_head('Queue', 'admin');
?>
<h1 style="margin-bottom:18px">Queue</h1>
<div class="clusters" data-live="<?= BASE ?>/api/live.php?v=admin"><?php render_live(true); ?></div>
<?php page_foot('admin');
