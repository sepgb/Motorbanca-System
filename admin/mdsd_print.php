<?php
// Printable MDSD (Master's Declaration of Safe Departure, Coast Guard Form C) for one trip. PCG (any trip) or the operator of that boat (own trips only).
// The blanks fill in by themselves from the trip; the YES / NO / N/A boxes show the answers the PCG gave when the boat departed.
require_once '../config/mdsd_helpers.php';
$isOp = ($_SESSION['role'] ?? '') === 'operator';
if ($isOp) {   // an operator may open the forms of this boat's own trips (for the PDF download in Trip History)
    need('operator');
    $mb = my_mb();
    $own = db()->prepare("SELECT 1 FROM trips WHERE id=? AND motorbanca_id=? AND status NOT IN ('Cancelled','Inspection')");
    $own->execute([(int)($_GET['trip'] ?? 0), $mb['id'] ?? 0]);
    if (!$mb || !$own->fetchColumn()) {
        http_response_code(404);
        exit('Trip not found.');
    }
} else need('admin');
$tid = (int)($_GET['trip'] ?? 0);
$v = mdsd_data($tid);
if (!$v) {
    http_response_code(404);
    exit('Trip not found.');
}
$ans = mdsd_get($tid);
$ref = (string)($_GET['back'] ?? '');
$back = strpos($ref, BASE . '/admin/') === 0 ? $ref : 'dashboard.php';
if ($isOp) $back = BASE . '/operator/trips.php';
function bx($ans, $k, $val)
{
    return "<span class='bx" . (($ans[$k] ?? '') === $val ? " on" : "") . "'></span>";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>MDSD - <?= e($v['vessel']) ?></title>
    <style>
        /* no browser header / footer (date, title, address): only the page count "1/1" at the bottom right */
        @page {
            size: letter;
            margin: 6mm 10mm;

            @top-center {
                content: ""
            }

            @bottom-right {
                content: counter(page) "/" counter(pages);
                font: 600 9px Arial, sans-serif;
                color: #000
            }
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            background: #f3eee9;
            font: 9.3px/1.24 Arial, Helvetica, sans-serif;
            color: #000
        }

        .bar {
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            gap: 10px;
            justify-content: center;
            padding: 10px;
            background: #fff;
            border-bottom: 1px solid #eadfd5
        }

        .bar a,
        .bar button {
            font: 600 13px Arial, sans-serif;
            padding: 9px 20px;
            border-radius: 999px;
            border: 1px solid #efe7e0;
            background: #fff;
            color: #1f1a17;
            text-decoration: none;
            cursor: pointer
        }

        .bar button {
            background: #f26b1d;
            border-color: #f26b1d;
            color: #fff
        }

        .page {
            position: relative;
            width: 215.9mm;
            min-height: 279.4mm;
            margin: 10px auto;
            padding: 8mm 10mm;
            background: #fff
        }

        .formc {
            position: absolute;
            top: 8mm;
            right: 24mm;
            font-weight: 700;
            font-size: 12px
        }

        .stamp {
            position: absolute;
            top: 20mm;
            right: 10mm;
            width: 34mm;
            height: 34mm;
            border: .3mm dashed #000;
            font-size: 6.5px;
            font-weight: 700;
            text-align: center;
            padding-top: 25mm;
            line-height: 1.25
        }

        .hd {
            text-align: center;
            padding: 0 38mm
        }

        .hd img {
            width: 12mm;
            height: 12mm;
            object-fit: contain;
            display: block;
            margin: 0 auto .3mm
        }

        .hd b {
            font-size: 11px
        }

        .hd p {
            margin: 0;
            font-size: 10px;
            line-height: 1.2
        }

        h1 {
            margin: 2mm 0 0;
            font-size: 11px;
            text-align: center;
            padding-right: 36mm
        }

        .meta {
            display: flex;
            justify-content: space-between;
            margin-top: 1.5mm;
            padding-right: 38mm
        }

        .meta .r {
            width: 62mm
        }

        .meta p {
            margin: 0;
            display: flex;
            align-items: flex-end;
            gap: 1mm;
            line-height: 1.4
        }

        .meta .r p span:first-child {
            width: 21mm;
            font-weight: 700
        }

        .f {
            display: inline-block;
            border-bottom: .25mm solid #000;
            min-width: 14mm;
            padding: 0 1mm;
            text-align: center;
            font-weight: 700;
            vertical-align: bottom;
            line-height: 1.3
        }

        .meta .f {
            flex: 1
        }

        p.l {
            margin: 1.2mm 0 0;
            line-height: 1.5
        }

        p.l.n {
            padding-left: 8mm;
            text-indent: -8mm
        }

        .num {
            display: inline-block;
            width: 8mm;
            text-indent: 0
        }

        .h2 {
            margin: 1.2mm 0 0;
            padding-left: 8mm;
            text-indent: -8mm
        }

        .yn {
            margin: 1mm 0 0 8mm;
            display: flex;
            gap: 2.4mm;
            font-weight: 700;
            font-size: 9.5px;
            width: 25mm
        }

        .yn span,
        .sub-h span {
            width: 4.8mm;
            text-align: center;
            font-size: 8.5px
        }

        .q {
            display: flex;
            align-items: flex-start;
            margin: .3mm 0 0 8mm
        }

        .q .bxs {
            flex: none;
            display: flex;
            gap: 2.6mm;
            width: 25mm;
            padding-top: .2mm
        }

        .bx {
            position: relative;
            flex: none;
            width: 4mm;
            height: 4mm;
            border: .35mm solid #000
        }

        .bx.on::after {
            content: "";
            position: absolute;
            left: 1.1mm;
            top: -.1mm;
            width: 1.4mm;
            height: 2.8mm;
            border: solid #000;
            border-width: 0 .5mm .5mm 0;
            transform: rotate(45deg)
        }

        .q .lt {
            flex: none;
            width: 6mm;
            font-weight: 700;
            padding-top: .5mm
        }

        .q.sub .lt {
            width: 9mm
        }

        .q .tx {
            flex: 1;
            font-weight: 700;
            line-height: 1.25;
            padding-top: .5mm
        }

        .q.sub {
            margin-left: 40mm
        }

        .sub-h {
            display: flex;
            gap: 2.6mm;
            margin: 1mm 0 0 40mm;
            font-weight: 700;
            font-size: 9.5px;
            width: 25mm
        }

        .sub-h span {
            width: 4mm
        }

        .c2 {
            margin-left: 14mm
        }

        .sig {
            display: flex;
            justify-content: space-between;
            align-items: flex-end;
            margin-top: 2.5mm;
            margin-bottom: 1mm
        }

        .sig .lbl {
            font-weight: 700
        }

        .row {
            display: flex;
            align-items: flex-end;
            gap: 1.5mm;
            margin-top: 1.2mm
        }

        .row .f {
            flex: 1
        }

        .center {
            text-align: center;
            font-weight: 700
        }

        @media print {
            body {
                background: #fff
            }

            .bar {
                display: none
            }

            .page {
                display: flex;
                flex-direction: column;
                margin: 0;
                padding: 0;
                width: auto;
                min-height: 264mm
            }

            /* the "Distribution" line sits at the bottom of the sheet, so no empty strip is left under the form */
            .page>p:last-child {
                margin-top: auto !important
            }

            .formc {
                top: 0;
                right: 14mm
            }

            .stamp {
                top: 11mm;
                right: 0
            }
        }
    </style>
</head>

<body>
    <div class="bar">
        <button type="button" onclick="window.print()">Print / Save as PDF</button>
        <a href="<?= e($back) ?>">Done</a>
    </div>
    <div class="page">
        <div class="formc">FORM C</div>
        <div class="stamp">For PCG Stamp<br>Cleared to depart/<br>Not Cleared to depart</div>
        <div class="hd">
            <img src="<?= BASE ?>/admin/logo.webp" alt="">
            <b>PUNONGHIMPILAN TANOD BAYBAYIN NG PILIPINAS</b>
            <p><b>(Headquarters Philippine Coast Guard)</b></p>
            <p>139 25<sup>th</sup> Street, Port Area</p>
            <p>1018 Manila</p>
        </div>
        <h1>MASTER'S DECLARATION OF SAFE DEPARTURE (MDSD)</h1>
        <div class="meta">
            <div class="lft">
                <p>REPUBLIC OF THE PHILIPPINES</p>
                <p>Philippine Coast Guard</p>
                <p>Station/Detachment <span class="f" style="min-width:62mm"><?= e($v['station']) ?></span></p>
            </div>
            <div class="r">
                <p><span>Vessel Name</span>: <span class="f"><?= e($v['vessel']) ?></span></p>
                <p><span>Voyage No.</span>: <span class="f"><?= e($v['voyage']) ?></span></p>
                <p><span>Date</span>: <span class="f"><?= e($v['date']) ?></span></p>
            </div>
        </div>

        <p class="l" style="margin-top:3.5mm">I, <span class="f" style="min-width:52mm"><?= e($v['master']) ?></span> Master of <span class="f" style="min-width:52mm"><?= e($v['vessel']) ?></span> presently berthed / anchored at <span class="f" style="min-width:62mm"><?= e($v['berthed']) ?></span> after having been duly sworn to in accordance with law, do hereby depose and state:</p>

        <p class="l n"><span class="num">1.</span><b>THAT</b> my vessel is seaworthy in all respect to sail for <span class="f" style="min-width:68mm"><?= e($v['sail_for']) ?></span> at <span class="f" style="min-width:26mm"><?= e($v['hrs']) ?></span> hrs;</p>

        <p class="h2"><span class="num">2.</span>That I have caused the verification and inspection on my ship and I hereby attest to the following findings:</p>
        <div class="yn"><span>YES</span><span>NO</span><span>N/A</span></div>
        <div class="q "><span class="bxs"><?= bx($ans, 'a', 'yes') ?><?= bx($ans, 'a', 'no') ?><?= bx($ans, 'a', 'na') ?></span><span class="lt">a)</span><span class="tx">Completeness and validity of ships safety certificates and documents;</span></div>
        <div class="q "><span class="bxs"><?= bx($ans, 'b', 'yes') ?><?= bx($ans, 'b', 'no') ?><?= bx($ans, 'b', 'na') ?></span><span class="lt">b)</span><span class="tx">Compliance with safe manning requirement and crew certification and qualification document validity;</span></div>
        <div class="q "><span class="bxs"><?= bx($ans, 'c', 'yes') ?><?= bx($ans, 'c', 'no') ?><?= bx($ans, 'c', 'na') ?></span><span class="lt">c)</span><span class="tx">Officers and crew are in healthy and sound condition, not under the influence of alcohol or prohibited drugs and fit to perform duties and responsibilities for the intended voyage;</span></div>
        <div class="q "><span class="bxs"><?= bx($ans, 'd', 'yes') ?><?= bx($ans, 'd', 'no') ?><?= bx($ans, 'd', 'na') ?></span><span class="lt">d)</span><span class="tx">Carriage of cargo and passengers are with authorized limits, and properly documented;</span></div>
        <div class="q "><span class="bxs"><?= bx($ans, 'e', 'yes') ?><?= bx($ans, 'e', 'no') ?><?= bx($ans, 'e', 'na') ?></span><span class="lt">e)</span><span class="tx">Required communications and navigational equipment are complete and in good operating condition;</span></div>
        <div class="q "><span class="bxs"><?= bx($ans, 'f', 'yes') ?><?= bx($ans, 'f', 'no') ?><?= bx($ans, 'f', 'na') ?></span><span class="lt">f)</span><span class="tx">Main and auxiliary engines are in good and operating condition;</span></div>
        <div class="q "><span class="bxs"><?= bx($ans, 'g', 'yes') ?><?= bx($ans, 'g', 'no') ?><?= bx($ans, 'g', 'na') ?></span><span class="lt">g)</span><span class="tx">Fire-fighting and life-saving appliances are complete, in good operating condition, available and accessible to passengers;</span></div>
        <div class="q "><span class="bxs"><?= bx($ans, 'h', 'yes') ?><?= bx($ans, 'h', 'no') ?><?= bx($ans, 'h', 'na') ?></span><span class="lt">h)</span><span class="tx">Proper ship stability and trim, cargo stowage securing and lashings are strictly observed;</span></div>
        <div class="q "><span class="bxs"><?= bx($ans, 'i', 'yes') ?><?= bx($ans, 'i', 'no') ?><?= bx($ans, 'i', 'na') ?></span><span class="lt">i)</span><span class="tx">Prevailing weather conditions for intended voyage is safe and latest weather bulletin and advisory are available;</span></div>
        <div class="q "><span class="bxs"><?= bx($ans, 'j', 'yes') ?><?= bx($ans, 'j', 'no') ?><?= bx($ans, 'j', 'na') ?></span><span class="lt">j)</span><span class="tx">The crew, passengers and cargoes have undergone the necessary security screening;</span></div>
        <div class="q "><span class="bxs"><?= bx($ans, 'k', 'yes') ?><?= bx($ans, 'k', 'no') ?><?= bx($ans, 'k', 'na') ?></span><span class="lt">k)</span><span class="tx">Hull in excellent condition; and</span></div>
        <div class="q "><span class="bxs"><?= bx($ans, 'l', 'yes') ?><?= bx($ans, 'l', 'no') ?><?= bx($ans, 'l', 'na') ?></span><span class="lt">l)</span><span class="tx">Attached documents are properly accomplished:</span></div>
        <div class="sub-h"><span>YES</span><span>NO</span><span>N/A</span></div>
        <div class="q sub"><span class="bxs"><?= bx($ans, 'd1', 'yes') ?><?= bx($ans, 'd1', 'no') ?><?= bx($ans, 'd1', 'na') ?></span><span class="lt">i.</span><span class="tx">Voyage Plan</span></div>
        <div class="q sub"><span class="bxs"><?= bx($ans, 'd2', 'yes') ?><?= bx($ans, 'd2', 'no') ?><?= bx($ans, 'd2', 'na') ?></span><span class="lt">ii.</span><span class="tx">Crew List</span></div>
        <div class="q sub"><span class="bxs"><?= bx($ans, 'd3', 'yes') ?><?= bx($ans, 'd3', 'no') ?><?= bx($ans, 'd3', 'na') ?></span><span class="lt">iii.</span><span class="tx">Passenger Main Manifest</span></div>
        <div class="q sub"><span class="bxs"><?= bx($ans, 'd4', 'yes') ?><?= bx($ans, 'd4', 'no') ?><?= bx($ans, 'd4', 'na') ?></span><span class="lt">iv.</span><span class="tx">Passenger Supplemental Manifest</span></div>
        <div class="q sub"><span class="bxs"><?= bx($ans, 'd5', 'yes') ?><?= bx($ans, 'd5', 'no') ?><?= bx($ans, 'd5', 'na') ?></span><span class="lt">v.</span><span class="tx">Coasting Manifest</span></div>
        <div class="q sub"><span class="bxs"><?= bx($ans, 'd6', 'yes') ?><?= bx($ans, 'd6', 'no') ?><?= bx($ans, 'd6', 'na') ?></span><span class="lt">vi.</span><span class="tx">Dangerous Cargo Manifest and Permit to Carry Dangerous Cargo</span></div>
        <div class="q sub"><span class="bxs"><?= bx($ans, 'd7', 'yes') ?><?= bx($ans, 'd7', 'no') ?><?= bx($ans, 'd7', 'na') ?></span><span class="lt">vii.</span><span class="tx">Cargo Manifest</span></div>

        <p class="l n" style="margin-top:2.4mm"><span class="num">3.</span>That the ship has an authorized carrying capacity of <span class="f" style="min-width:14mm"><?= e($v['capacity']) ?></span> passengers and the total actual passengers on board are <span class="f" style="min-width:14mm"><?= e($v['total']) ?></span> consisting of <span class="f" style="min-width:14mm"><?= e($v['adults']) ?></span> adults, <span class="f" style="min-width:14mm"><?= e($v['children']) ?></span> Children and <span class="f" style="min-width:14mm"><?= e($v['infants']) ?></span> infants; as per attached Passenger Main Manifest.</p>

        <p class="l n"><span class="num">4.</span><b>THAT</b> I am permitted to carry the following items which are properly stowed and lashed throughout the voyage:</p>
        <div class="row c2" style="margin-left:14mm"><span><b>a)</b>&nbsp; Declaration of dangerous cargo</span><span class="f"><?= e($v['cargo']) ?></span></div>
        <div class="row c2" style="margin-left:14mm"><span><b>b)</b>&nbsp; Permit to carry dangerous cargo is likewise attached.</span></div>

        <p class="l" style="margin-top:2.4mm;padding-left:8mm"><b>AFFIANT FURTHER SAYETH NONE,</b></p>
        <p class="l" style="padding-left:8mm"><b>IN WITNESS WHEREOF</b>, I have hereunto affixed my signature this <span class="f" style="min-width:52mm"><?= e($v['sign_day']) ?></span> at the City/ Municipality of <span class="f" style="min-width:52mm"><?= e($v['place']) ?></span>, Philippines.</p>

        <div class="sig">
            <div style="width:96mm">
                <div class="row" style="position:relative"><span>Contact Numbers for intended voyage: 1)</span><span class="f"><?= e($v['contact']) ?></span><span style="position:absolute;left:3mm;top:100%;font-size:6.5px;white-space:nowrap">(Mobile Phones of Master/ C/E)</span></div>
                <div class="row" style="padding-left:55mm"><span>2)</span><span class="f">&nbsp;</span></div>
                <div class="row" style="padding-left:55mm"><span>3)</span><span class="f">&nbsp;</span></div>
            </div>
            <div style="width:78mm;text-align:center">
                <div style="border-top:.25mm solid #000;margin-top:6mm;padding-top:.5mm;font-weight:700">(Master)</div>
                <div class="row"><span style="width:22mm;text-align:left">License No.</span><span class="f">&nbsp;</span></div>
                <div class="row"><span style="width:22mm;text-align:left">Issued by</span><span class="f">&nbsp;</span></div>
                <div class="row"><span style="width:22mm;text-align:left">Issued on</span><span class="f">&nbsp;</span></div>
            </div>
        </div>

        <p class="l" style="margin-top:3mm"><b>SUBCRIBED AND SWORN</b> to before me this <span class="f" style="min-width:52mm"><?= e($v['sign_day']) ?></span> at the place as indicated above.</p>

        <div style="margin:6mm 0 0 66mm;border-top:.25mm solid #000;text-align:center;font-weight:700;padding-top:.5mm">(Officer Authorized to Administer Oath under Sec 41 of the Administrative Code)</div>

        <div style="display:flex;justify-content:space-between;margin-top:2mm">
            <div style="width:84mm">
                <p style="margin:0"><b>Received:</b></p>
                <div style="border-top:.25mm solid #000;margin-top:6mm;text-align:center;padding-top:.5mm;font-weight:700">(Signature over printed name)</div>
            </div>
            <div style="width:82mm">
                <div class="row"><span><b>Date:</b></span><span class="f"><?= e($v['recv_date']) ?></span></div>
                <div class="row" style="margin-top:3mm"><span><b>Time:</b></span><span class="f"><?= e($v['recv_time']) ?></span></div>
            </div>
        </div>

        <p style="margin:2.5mm 0 0;display:flex;gap:16mm;font-weight:700;font-style:italic;padding-left:30mm"><span>Distribution:</span><span>Original-PCG Copy</span><span>Duplicate- Master's File (Ship's file)</span></p>
    </div>
    <?php if (!isset($_GET['print'])): ?>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
        <script>
            // Opened inside the hidden frame of the Trip History page: build the PDF and download it.
            if (window.self !== window.top) window.addEventListener('load', function() {
                var done = function(s) {
                    parent.postMessage({
                        pdf: s
                    }, '*');
                };
                try {
                    html2pdf().set({
                            margin: 0,
                            filename: 'MDSD.pdf',
                            image: {
                                type: 'jpeg',
                                quality: 0.98
                            },
                            html2canvas: {
                                scale: 2,
                                useCORS: true,
                                backgroundColor: '#ffffff',
                                windowWidth: 1200,
                                scrollX: 0,
                                scrollY: 0
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