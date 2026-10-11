<?php require_once '../config/layout.php';
page_head('Live Boat Board'); ?>
<div class="clusters" data-live="<?= BASE ?>/api/live.php?v=passenger"><?php render_live(); ?></div>
<?php page_foot();
