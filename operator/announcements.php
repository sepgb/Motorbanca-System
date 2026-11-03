<?php
require_once '../config/layout.php';
require_once '../config/announcements.php';
need('operator');
ann_seen_mark();
page_head('Announcements', 'operator');
?>
<h1>Announcements</h1>
<?php ann_list(false); ?>
<?php page_foot('operator'); ?>