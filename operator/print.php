<?php
// Printable "Manipesto ng Pasahero" for the boat's current trip. Boat name, date, crew and captain fill in automatically.
require_once '../config/live.php';
if (($_SESSION['role'] ?? '') === 'admin') {   // PCG: any trip, from Trip history (?trip=ID)
    $tq = db()->prepare("SELECT t.id, t.trip_date, m.motorbanca_name FROM trips t JOIN motorbancas m ON m.id=t.motorbanca_id WHERE t.id=?");
    $tq->execute([(int)($_GET['trip'] ?? 0)]);
    $trip = $tq->fetch();
    if (!$trip) {
        http_response_code(404);
        exit('Trip not found.');
    }
    $m = ['motorbanca_name' => $trip['motorbanca_name']];
} else {                                       // operator: the boat's current trip
    need('operator');
    $m = my_mb();
    if (!$m) {
        header('Location: ../logout.php');
        exit;
    }
    if (isset($_GET['trip'])) {                // a past trip from Trip History (own boat only)
        $tq = db()->prepare("SELECT id, trip_date FROM trips WHERE id=? AND motorbanca_id=? AND status NOT IN ('Cancelled','Inspection')");
        $tq->execute([(int)$_GET['trip'], $m['id']]);
    } else {
        $tq = db()->prepare("SELECT id, trip_date FROM trips WHERE motorbanca_id=? AND status<>'Cancelled' ORDER BY id DESC LIMIT 1");
        $tq->execute([$m['id']]);
    }
    $trip = $tq->fetch();
    if (!$trip) {
        header('Location: dashboard.php');
        exit;
    }
}
$tc = trip_crew_get($trip['id']);
$crew = $tc['crew'] ?? [];
$captain = '';
foreach ($crew as $c) if ($c['position'] === 'BOAT CAPTAIN') $captain = $c['name'];
$tauhan = implode(', ', array_map(fn($c) => $c['name'], $crew));
$ps = db()->prepare("SELECT * FROM passengers WHERE trip_id=? ORDER BY id LIMIT 45");
$ps->execute([$trip['id']]);
$pax = $ps->fetchAll();
$b = BASE;
function cells($p, $n)
{   // No. / Pangalan / Tirahan / Edad
    return "<td class='no'>" . ($n ?: '') . "</td><td>" . ($p ? e($p['name']) : '') . "</td><td>" . ($p ? e($p['barangay']) : '') . "</td><td class='age'>" . ($p ? (int)$p['age'] : '') . "</td>";
}
$fn = 'Manifest.pdf';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Manipesto ng Pasahero - <?= e($m['motorbanca_name']) ?></title>
    <style>
        @page {
            size: letter;
            margin: 9mm
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            background: #fff;
            font: 11px/1.3 Arial, Helvetica, sans-serif;
            color: #000
        }

        .page {
            width: 215.9mm;
            margin: 0 auto;
            background: #fff;
            padding: 9mm
        }

        .hd {
            text-align: center
        }

        .hd img {
            width: 18mm;
            height: 18mm;
            object-fit: contain
        }

        .hd h1 {
            margin: 2mm 0 0;
            font-size: 16px;
            letter-spacing: .2px
        }

        .hd p {
            margin: 0;
            font-size: 10px;
            line-height: 1.35;
            font-weight: 700
        }

        .hd h2 {
            margin: 5mm 0 6mm;
            font-size: 14px
        }

        .f {
            display: flex;
            gap: 6mm;
            align-items: flex-end;
            font-weight: 700;
            margin: 0 0 3mm;
            line-height: 1.2
        }

        .f>div {
            display: flex;
            align-items: flex-end;
            gap: 2mm;
            white-space: nowrap
        }

        .f span {
            display: flex;
            align-items: flex-end;
            flex: 1;
            border-bottom: 1px solid #000;
            font-weight: 400;
            padding: 0 2px 1px;
            min-height: 5mm;
            line-height: 1.2;
            white-space: normal
        }

        .f .g {
            flex: 1
        }

        .f .d {
            width: 62mm
        }

        .warn {
            font-weight: 700;
            font-size: 10px;
            line-height: 1.3;
            margin: 4mm 0 4mm
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed
        }

        th,
        td {
            border: 1px solid #000;
            padding: 0 1.5mm;
            height: 6.1mm;
            font-size: 10.5px;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis
        }

        th {
            font-size: 10.5px;
            text-align: center;
            padding: 0;
            text-overflow: clip
        }

        td.no {
            text-align: center;
            font-weight: 700;
            padding: 0
        }

        td.age {
            text-align: center;
            padding: 0
        }

        .sig {
            margin: 10mm 0 0 auto;
            width: 78mm;
            text-align: center
        }

        .sig .n {
            border-bottom: 1px solid #000;
            min-height: 7mm;
            font-weight: 700;
            font-size: 12px;
            padding-top: 1mm
        }

        .sig .l {
            font-weight: 700;
            font-size: 10.5px;
            margin-top: 2mm
        }

        @media print {
            .page {
                width: auto;
                padding: 0
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="hd">
            <img src="<?= $b ?>/admin/logo.webp" alt="">
            <h1>PHILIPPINE COAST GUARD</h1>
            <p>Coast Guard District National Capital Region - Central Luzon</p>
            <p>Coast Guard Station Rizal</p>
            <p>J.P Rizal St. Extension Brgy Libis, Binangonan Rizal</p>
            <h2>MANIPESTO NG PASAHERO</h2>
        </div>
        <div class="f">
            <div class="g">PANGALAN NG BANGKA: <span><?= e($m['motorbanca_name']) ?></span></div>
            <div class="d">Date: <span><?= date('F j, Y', strtotime($trip['trip_date'])) ?></span></div>
        </div>
        <div class="f">
            <div class="g">PANGALAN NG TAUHAN/PAHINANTE: <span><?= e($tauhan) ?></span></div>
        </div>
        <div class="warn">BABALA SA AMING PASAHERO: Anumang maling pahayag o entry sa manipestong ito ay sasailalim sa paguusing sa ilalim ng Batas.</div>
        <table>
            <colgroup>
                <col style="width:5%">
                <col style="width:23%">
                <col style="width:16%">
                <col style="width:6%">
                <col style="width:5%">
                <col style="width:23%">
                <col style="width:16%">
                <col style="width:6%">
            </colgroup>
            <tr>
                <th>No.</th>
                <th>PANGALAN</th>
                <th>TIRAHAN</th>
                <th>EDAD</th>
                <th>No.</th>
                <th>PANGALAN</th>
                <th>TIRAHAN</th>
                <th>EDAD</th>
            </tr>
            <?php for ($i = 1; $i <= 25; $i++) {
                $l = $pax[$i - 1] ?? null;
                $ri = $i + 25;
                $r = $i <= 20 ? ($pax[$ri - 1] ?? null) : null;
                echo "<tr>" . cells($l, $i) . ($i <= 20 ? cells($r, $ri) : "<td class='no'></td><td></td><td></td><td></td>") . "</tr>";
            } ?>
        </table>
        <div class="sig">
            <div class="n"><?= e($captain) ?></div>
            <div class="l">PANGALAN AT PIRMA NG KAPITAN</div>
        </div>
    </div>
    <?php if (!isset($_GET['print'])): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <script>
            // Opened inside the hidden frame on the Passengers page: build the PDF and download it.
            if (window.self !== window.top) window.addEventListener('load', function() {
                var done = function(s) {
                    parent.postMessage({
                        pdf: s
                    }, '*');
                };
                try {
                    html2pdf().set({
                            margin: 0,
                            filename: '<?= $fn ?>',
                            image: {
                                type: 'jpeg',
                                quality: 0.98
                            },
                            html2canvas: {
                                scale: 2,
                                useCORS: true,
                                backgroundColor: '#ffffff'
                            },
                            jsPDF: {
                                unit: 'mm',
                                format: 'letter',
                                orientation: 'portrait'
                            }
                        })
                        .from(document.querySelector('.page')).outputPdf('blob').then(function(b) {
                            parent.postMessage({
                                pdf: 'done',
                                blob: b
                            }, '*');
                        }).catch(function() {
                            done('fail');
                        });
                } catch (e) {
                    done('fail');
                }
            });
        </script>
    <?php endif; ?>
</body>

</html>