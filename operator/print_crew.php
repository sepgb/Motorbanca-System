<?php
// Printable "Crew List" for the boat's current trip (same layout as the paper form).
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
$stored = $tc['crew'] ?? [];
$roles = ['BOAT CAPTAIN', 'MOTORMAN', 'CREW', 'CREW'];
$rows = [];
foreach ($roles as $role) {   // first unused stored entry with that position
    $hit = null;
    foreach ($stored as $k => $c) if ($c['position'] === $role) {
        $hit = $c;
        unset($stored[$k]);
        break;
    }
    $rows[] = ['position' => $role, 'name' => $hit['name'] ?? '', 'age' => $hit['age'] ?? ''];
}
$captain = $rows[0]['name'];
$fn = 'Crew List.pdf';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Crew List - <?= e($m['motorbanca_name']) ?></title>
    <style>
        @page {
            size: letter;
            margin: 12mm
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            background: #fff;
            font: 14px/1.3 Arial, Helvetica, sans-serif;
            color: #000
        }

        .page {
            width: 215.9mm;
            margin: 0 auto;
            padding: 22mm 14mm 10mm
        }

        .top {
            width: 128mm;
            margin-left: 14mm
        }

        .ln {
            display: flex;
            align-items: flex-end;
            gap: 2mm;
            font-weight: 700;
            font-size: 15px;
            margin-bottom: 3.5mm;
            white-space: nowrap
        }

        .ln span {
            display: flex;
            align-items: flex-end;
            flex: 1;
            border-bottom: 2px solid #000;
            font-weight: 400;
            font-size: 14px;
            padding: 0 2px 1px;
            min-height: 6mm;
            white-space: normal
        }

        .dt {
            width: 62mm;
            margin: 14mm 14mm 0 auto;
            text-align: center
        }

        .dt .v {
            border-bottom: 2px solid #000;
            min-height: 7mm;
            font-size: 14px;
            padding-bottom: 1px
        }

        .dt .l {
            font-weight: 700;
            font-size: 13px;
            margin-top: 1.5mm
        }

        h1 {
            margin: 12mm 0 6mm;
            text-align: center;
            font-size: 25px;
            letter-spacing: -.3px
        }

        table {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed
        }

        th,
        td {
            border: 1.5px solid #000;
            text-align: center;
            padding: 0 2mm;
            height: 9.5mm;
            overflow: hidden;
            white-space: nowrap;
            text-overflow: ellipsis
        }

        th {
            font-size: 16px;
            height: 11mm
        }

        td.no {
            font-weight: 700;
            font-size: 16px
        }

        td.pos {
            font-weight: 700;
            font-size: 13px
        }

        td.nm {
            text-align: left;
            font-size: 14px
        }

        .sig {
            width: 78mm;
            margin: 50mm 14mm 0 auto;
            text-align: center
        }

        .sig .n {
            border-bottom: 2px solid #000;
            min-height: 9mm;
            font-weight: 700;
            font-size: 15px;
            padding-top: 2mm
        }

        .sig .l {
            font-weight: 700;
            font-size: 16px;
            margin-top: 2mm
        }

        @media print {
            .page {
                width: auto;
                padding: 10mm 0 0
            }
        }
    </style>
</head>

<body>
    <div class="page">
        <div class="top">
            <div class="ln">Motorbanca <span><?= e($m['motorbanca_name']) ?></span></div>
            <div class="ln">Address <span><?= e($tc['address'] ?? '') ?></span></div>
            <div class="ln">Contact No.: <span><?= e($tc['contact'] ?? '') ?></span></div>
        </div>
        <div class="dt">
            <div class="v"><?= date('F j, Y', strtotime($trip['trip_date'])) ?></div>
            <div class="l">DATE</div>
        </div>
        <h1>CREW LIST</h1>
        <table>
            <colgroup>
                <col style="width:8%">
                <col style="width:36%">
                <col style="width:10%">
                <col style="width:23%">
                <col style="width:23%">
            </colgroup>
            <tr>
                <th>NO</th>
                <th>CREW NAME</th>
                <th>AGE</th>
                <th>POSITION</th>
                <th>SIGNATURE</th>
            </tr>
            <?php foreach ($rows as $i => $r) echo "<tr><td class='no'>" . ($i + 1) . "</td><td class='nm'>" . e($r['name']) . "</td><td>" . e($r['age']) . "</td><td class='pos'>" . e($r['position']) . "</td><td></td></tr>"; ?>
        </table>
        <div class="sig">
            <div class="n"><?= e($captain) ?></div>
            <div class="l">BOAT CAPTAIN</div>
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