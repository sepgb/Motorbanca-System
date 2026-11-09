<?php

require_once '../config/layout.php';
$fares = [
    'Cluster 1A' => [57.50, 46, 20],
    'Cluster 1B' => [69, 55.20, 20],
    'Cluster 2A' => [57.50, 46, 30],
    'Cluster 2B' => [57.50, 46, 30],
    'Cluster 3' => [74.75, 59.80, 40],
    'Cluster 4' => [103.50, 82.80, 50],
    'Cluster 5' => [115, 92, 60],
    'Cluster 6' => [115, 92, 70],
    'Cluster 7' => [115, 92, 80],
];
function peso($v)
{
    return '&#8369;' . (floor($v) == $v ? number_format($v, 0) : number_format($v, 2));
}
page_head('Fares', 'public');
?>
<h1>Boat fares</h1>
<div class="fx">
    <div class="fx-head">
        <span>Cluster</span>
        <span>Regular</span>
        <span>PWD / Senior / Student</span>
        <span>Children<small>4 to 11 years old</small></span>
    </div>
    <?php foreach (db()->query("SELECT * FROM clusters ORDER BY id")->fetchAll() as $c):
        $n = trim(preg_replace('/^Cluster\s*/', '', $c['name']));
        $f = $fares[$c['name']] ?? null;
        if (!$f) continue;
    ?>
        <div class="fx-row">
            <span class="fx-c"><i><?= e($n) ?></i><em><?= e($c['name']) ?></em></span>
            <b class="fx-reg" data-label="Regular"><?= peso($f[0]) ?></b>
            <b data-label="PWD / Senior / Student"><?= peso($f[1]) ?></b>
            <b data-label="Children" data-sub="4 to 11 years old"><?= peso($f[2]) ?></b>
        </div>
    <?php endforeach; ?>
</div>
<?php page_foot();
