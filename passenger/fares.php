<?php
// Public: boat fare per ride for each cluster.
require_once '../config/layout.php';
$fares = [   // cluster number => [regular, PWD/senior/student (20% off), children 4-11]
    1 => [42, 34, 20],
    2 => [53, 42, 20],
    3 => [70, 56, 40],
    4 => [90, 72, 50],
    5 => [90, 72, 70],
];
page_head('Fares', 'public');
?>
<h1>Boat fares</h1>
<p class="lead">Fare per ride for each cluster.</p>
<div class="fx">
    <div class="fx-head">
        <span>Cluster</span>
        <span>Regular</span>
        <span>PWD / Senior / Student<small>20% off</small></span>
        <span>Children<small>4 to 11 years old</small></span>
    </div>
    <?php foreach (db()->query("SELECT * FROM clusters ORDER BY id")->fetchAll() as $c):
        $n = (int)preg_replace('/\D+/', '', $c['name']);   // "Cluster 3" -> 3
        $f = $fares[$n] ?? null;
        if (!$f) continue;
    ?>
        <div class="fx-row">
            <span class="fx-c"><i><?= $n ?></i><em><?= e($c['name']) ?></em></span>
            <b class="fx-reg" data-label="Regular">&#8369;<?= $f[0] ?></b>
            <b data-label="PWD / Senior / Student" data-sub="20% off">&#8369;<?= $f[1] ?></b>
            <b data-label="Children" data-sub="4 to 11 years old">&#8369;<?= $f[2] ?></b>
        </div>
    <?php endforeach; ?>
</div>
<?php page_foot();
