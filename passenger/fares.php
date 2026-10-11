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
<div class="clusters fares">
    <?php foreach (db()->query("SELECT * FROM clusters ORDER BY id")->fetchAll() as $c):
        $f = $fares[(int)preg_replace('/\D+/', '', $c['name'])] ?? null;
        if (!$f) continue;   // "Cluster 3" -> 3 
    ?>
        <section class="cluster">
            <h2><?= e($c['name']) ?></h2>
            <div class="fare-list">
                <div class="fare reg"><span>Regular</span><b>&#8369;<?= $f[0] ?></b></div>
                <div class="fare"><span>PWD / Senior / Student<small>20% off</small></span><b>&#8369;<?= $f[1] ?></b></div>
                <div class="fare"><span>Children<small>4 to 11 years old</small></span><b>&#8369;<?= $f[2] ?></b></div>
            </div>
        </section>
    <?php endforeach; ?>
</div>
<?php page_foot();
