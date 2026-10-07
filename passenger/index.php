<?php require_once '../config/layout.php';
page_head('Live Boat Board'); ?>
<p class="muted">Updates by itself every few seconds. 🟢 Queued · 🔵 Departed · 🔴 Full</p>
<div data-live="<?= BASE ?>/api/live.php?v=passenger"><?php render_live(); ?></div>
<?php page_foot();
