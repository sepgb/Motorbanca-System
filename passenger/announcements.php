<?php
require_once '../config/layout.php';
require_once '../config/announcements.php';
ann_seen_mark();
page_head('Announcements', 'public');
?>
<h1>Announcements</h1>
<p class="lead">Official notices from the Philippine Coast Guard.</p>
<?php ann_list(false); ?>
<?php page_foot('public'); ?>