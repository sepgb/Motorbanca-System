<?php
require_once '../config/layout.php';
require_once '../config/announcements.php';
ann_seen_mark();
page_head('Notifications', 'public');
?>
<h1>Notifications</h1>
<?php ann_list(false); ?>
<?php page_foot('public'); ?>