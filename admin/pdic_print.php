<?php
// Printable PDIC (Pre-Departure Inspection Checklist, Coast Guard Form D-3) for one trip. PCG (any trip) or the operator of that boat (own trips only).
// The header fills in by itself from the trip; the YES / NO columns show the answers the PCG gave when the boat departed.
require_once '../config/pdic_helpers.php';
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
$d = pdic_data($tid);
if (!$d) {
    http_response_code(404);
    exit('Trip not found.');
}
$g = pdic_get($tid);
$ans = $g['answers'] ?? [];
$ref = (string)($_GET['back'] ?? '');
$back = strpos($ref, BASE . '/admin/') === 0 ? $ref : 'trips.php';
if ($isOp) $back = BASE . '/operator/trips.php';
function ck($ans, $n, $v)
{
    return (($ans[$n] ?? '') === $v) ? "<span class='ck'></span>" : '';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>PDIC - <?= e($d['vessel']) ?></title>
    <style>
        @page {
            size: letter landscape;
            margin: 8mm
        }

        * {
            box-sizing: border-box
        }

        body {
            margin: 0;
            background: #f3eee9;
            font: 10px/1.28 Arial, Helvetica, sans-serif;
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

        .pg {
            position: relative;
            display: flex;
            flex-direction: column;
            width: 279.4mm;
            height: 215.9mm;
            margin: 10px auto;
            padding: 8mm;
            background: #fff;
            overflow: hidden
        }

        /* the table takes all the height that is left on the sheet, so the rows spread out and there is no empty space at the bottom */
        .tw {
            flex: 1 1 auto;
            min-height: 0
        }

        .tw .t {
            height: 100%
        }

        .formno {
            position: absolute;
            top: 8mm;
            right: 8mm;
            font-weight: 700;
            font-size: 11px
        }

        .hd {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 4mm;
            padding: 0 30mm;
            min-height: 19mm
        }

        .hd img {
            position: absolute;
            left: 0;
            top: 0;
            width: 17mm;
            height: 17mm;
            object-fit: contain
        }

        .hd .t1 {
            display: block;
            font-size: 17px;
            text-align: center
        }

        .hd .t2 {
            font-size: 13px;
            text-align: center
        }

        .hd .t3 {
            font-size: 7px;
            font-weight: 700;
            text-align: center
        }

        .fields {
            display: flex;
            gap: 8mm;
            margin: 3mm 0 3mm;
            border: .3mm solid #000;
            padding: 2mm 3mm
        }

        .fields>div {
            flex: 1
        }

        .row {
            display: flex;
            align-items: flex-end;
            gap: 1.5mm;
            margin-top: 1.4mm
        }

        .row .l {
            white-space: nowrap
        }

        .f {
            display: inline-block;
            flex: 1;
            min-width: 8mm;
            border-bottom: .25mm solid #000;
            padding: 0 1mm;
            font-weight: 700;
            text-align: center;
            line-height: 1.3
        }

        .t {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed
        }

        .t th,
        .t td {
            border: .3mm solid #000;
            padding: 1.6mm 1.6mm;
            vertical-align: top
        }

        .t th {
            font-size: 10.5px;
            text-align: center
        }

        .t td.ref {
            font-size: 8.6px
        }

        .t td.cd {
            text-align: center;
            font-size: 10px
        }

        .t td.yn {
            position: relative;
            text-align: center
        }

        .t tr.sec td {
            text-align: center;
            font-weight: 700;
            font-size: 10.5px;
            padding: 1.6mm
        }

        .t td.it ul {
            margin: .8mm 0 0;
            padding-left: 5mm
        }

        .t td.it .nt {
            font-style: italic;
            text-align: center
        }

        .t td.it {
            text-align: justify
        }

        .ck {
            display: block;
            width: 2.2mm;
            height: 4mm;
            margin: 0 auto;
            border: solid #000;
            border-width: 0 .6mm .6mm 0;
            transform: rotate(45deg) translate(-.3mm, -.6mm)
        }

        .ins {
            margin: 3mm 0 0;
            padding-left: 5mm;
            font-size: 8px;
            line-height: 1.22
        }

        .ins li {
            margin-bottom: .6mm
        }

        .foot {
            display: flex;
            justify-content: space-between;
            gap: 20mm;
            margin-top: 6mm
        }

        .foot>div {
            flex: 1;
            text-align: center
        }

        .foot .v {
            min-height: 5mm;
            font-weight: 700;
            font-size: 10px
        }

        .foot .ln {
            border-top: .3mm solid #000;
            padding-top: 1mm;
            font-size: 10px
        }

        @media print {
            body {
                background: #fff
            }

            .bar {
                display: none
            }

            .pg {
                margin: 0;
                padding: 0;
                width: auto;
                height: 199.8mm;
                break-after: page;
                page-break-after: always
            }

            .pg:last-of-type {
                break-after: auto;
                page-break-after: auto
            }

            .formno {
                top: 0;
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

    <div class="pg">
        <div class="formno">FORM D-3</div>
        <div class="hd">
            <img src="<?= BASE ?>/admin/logo.webp" alt="">
            <div>
                <b class="t1">PRE-DEPARTURE INSPECTION CHECKLIST</b>
                <div class="t2">For <b><i>MOTOR BANCA / BOAT</i></b> <i>carrying Passengers and / or Cargoes</i></div>
                <div class="t3">FOR OFFICIAL USE OF PHILIPPINE COAST GUARD VSEI TEAM</div>
            </div>
        </div>
        <div class="fields">
            <div>
                <div class="row"><span class="l">Name of Motor Banca:</span><span class="f"><?= e($d['vessel']) ?></span></div>
                <div class="row"><span class="l">Owner/ Operator:</span><span class="f"><?= e($d['owner']) ?></span></div>
                <div class="row"><span class="l">Type of Trading:</span><span class="f"><?= e($d['trading']) ?></span></div>
                <div class="row"><span class="l">Hull Material:</span><span class="f"><?= e($d['hull']) ?></span></div>
                <div class="row"><span class="l">GT:</span><span class="f"><?= e($d['gt']) ?></span><span class="l">NT:</span><span class="f"><?= e($d['nt']) ?></span></div>
            </div>
            <div>
                <div class="row"><span class="l">Date of Inspection:</span><span class="f"><?= e($d['date']) ?></span></div>
                <div class="row"><span class="l">Port of Inspection:</span><span class="f"><?= e($d['port']) ?></span></div>
                <div class="row"><span class="l">Port of Destination:</span><span class="f"><?= e($d['destination']) ?></span></div>
                <div class="row"><span class="l">Time Inspection Started:</span><span class="f"><?= e($d['started']) ?></span></div>
                <div class="row"><span class="l">Time Inspection Completed:</span><span class="f"><?= e($d['completed']) ?></span></div>
            </div>
        </div>
        <div class="tw">
            <table class="t">
                <colgroup>
                    <col style="width:16%">
                    <col style="width:52%">
                    <col style="width:8%">
                    <col style="width:5%">
                    <col style="width:5%">
                    <col style="width:14%">
                </colgroup>
                <thead>
                    <tr>
                        <th class="ref">REF TO REG</th>
                        <th>ITEMS FOR INSPECTION</th>
                        <th>CODE</th>
                        <th>YES</th>
                        <th>NO</th>
                        <th>REMARKS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="sec">
                        <td colspan="6">COMPLETE AND VALID SHIP SAFETY CERTIFICATES AND DOCUMENTS</td>
                    </tr>
                    <tr>
                        <td class="ref">MC 16/MC 2006-06</td>
                        <td class="it"><b>1.</b> Certificate of Public Convenience (CPC) is valid. Authorized route (Ports of Call) &amp; area of operation is observed<div class="nt">(If Not Valid, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS8113</td>
                        <td class="yn"><?= ck($ans, 1, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 1, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">MC 177</td>
                        <td class="it"><b>2.</b> Certificate of Vessel Registry is valid<div class="nt">(If Not Valid, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS8115</td>
                        <td class="yn"><?= ck($ans, 2, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 2, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">MC 177</td>
                        <td class="it"><b>3.</b> Certificate of Ownership is valid<div class="nt">(If Not Valid, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS8112</td>
                        <td class="yn"><?= ck($ans, 3, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 3, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">MC 110</td>
                        <td class="it"><b>4.</b> Coastwise/ Bay &amp; River License is valid<div class="nt">(If Not Valid, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS8118</td>
                        <td class="yn"><?= ck($ans, 4, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 4, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">MC 134</td>
                        <td class="it"><b>5.</b> Certificate of Compliance is valid<div class="nt">(If Not Valid, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS8120</td>
                        <td class="yn"><?= ck($ans, 5, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 5, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">MC 203</td>
                        <td class="it"><b>6.</b> Passenger / Cargo Ship Safety Certificate is valid<div class="nt">(If Not Valid, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS8148<br>VS8104</td>
                        <td class="yn"><?= ck($ans, 6, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 6, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">MC 148; MC 179</td>
                        <td class="it"><b>7.</b> Minimum Safe Manning Certificate is valid<div class="nt">(If Not Valid, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS8142</td>
                        <td class="yn"><?= ck($ans, 7, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 7, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">MC 2007-04</td>
                        <td class="it"><b>8.</b> Tonnage Measurement Certificate is valid<div class="nt">(If Not Valid, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS8165</td>
                        <td class="yn"><?= ck($ans, 8, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 8, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">MC 90, MC ____ Sec III A.36</td>
                        <td class="it"><b>9.</b> Certificate of Number (OPTIONAL FOR 15 G.T. AND BELOW)<div class="nt">(If Not Valid, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS8111</td>
                        <td class="yn"><?= ck($ans, 9, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 9, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">RA 9295, MC 2009-18</td>
                        <td class="it"><b>10.</b> Passenger Insurance Cover<div class="nt">(If Not Valid, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS8147</td>
                        <td class="yn"><?= ck($ans, 10, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 10, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">HPCG MC 08-12</td>
                        <td class="it"><b>11.</b> Master's Declaration of Safe Departure (MDSD) properly accomplished, submitted with the necessary attachments:<div class="nt">(If incomplete, to be RECTIFIED)</div>
                        </td>
                        <td class="cd">VS8144</td>
                        <td class="yn"><?= ck($ans, 11, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 11, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="pg">
        <div class="tw">
            <table class="t">
                <colgroup>
                    <col style="width:16%">
                    <col style="width:52%">
                    <col style="width:8%">
                    <col style="width:5%">
                    <col style="width:5%">
                    <col style="width:14%">
                </colgroup>
                <thead>
                    <tr>
                        <th class="ref">REF TO REG</th>
                        <th>ITEMS FOR INSPECTION</th>
                        <th>CODE</th>
                        <th>YES</th>
                        <th>NO</th>
                        <th>REMARKS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="sec">
                        <td colspan="6">OVERLOADING OF CARGO AND EXCESS PASSENGERS STRICTLY NOT ALLOWED</td>
                    </tr>
                    <tr>
                        <td class="ref">MC 180, MC 2009-13, MC 2009-18</td>
                        <td class="it"><b>12.</b> Passenger Manifest properly accomplished and submitted prior departure.<ul>
                                <li>Number of passengers in the manifest within the authorized passenger capacity;</li>
                                <li>Number of passengers in the manifest corresponds to the actual passengers physically onboard;</li>
                                <li>Random check of names in the manifest and actual passengers onboard matches</li>
                            </ul>
                            <div class="nt">(If No, to be RECTIFIED)</div>
                        </td>
                        <td class="cd">VS82404<br>VS82606<br>VS82603</td>
                        <td class="yn"><?= ck($ans, 12, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 12, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">HPCG MC 04-98, MC 180, MC 2009-13, MC 2009-18</td>
                        <td class="it"><b>13.</b> Head counting of passengers conducted and finds the vessel carrying only the authorized number of passengers per MARINA issued PSSC<ul>
                                <li>Number of Passengers onboard: <span class="f"><?= e($d['pax']) ?></span></li>
                            </ul>
                            <div class="nt">(If No, to be RECTIFIED)</div>
                        </td>
                        <td class="cd">VS82606</td>
                        <td class="yn"><?= ck($ans, 13, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 13, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr class="sec">
                        <td colspan="6">ADEQUATE/ ACCESSIBLE LIFEJACKETS</td>
                    </tr>
                    <tr>
                        <td class="ref">MC 2008-08</td>
                        <td class="it"><b>14.</b> All passengers and crews in open deck boats are wearing lifejackets prior departure.<div class="nt">(If No, to be RECTIFIED)</div>
                        </td>
                        <td class="cd">VS82602</td>
                        <td class="yn"><?= ck($ans, 14, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 14, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr class="sec">
                        <td colspan="6">SHIP STABILITY AND TRIM, CARGO SECURING / LASHING ASCERTAINED</td>
                    </tr>
                    <tr>
                        <td class="ref">HPCG MC 04-98</td>
                        <td class="it"><b>15.</b> Per visual assessment, motorbanca is in her upright normal condition.<div class="nt">(If No, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS8926</td>
                        <td class="yn"><?= ck($ans, 15, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 15, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">MC 114</td>
                        <td class="it"><b>16.</b> Cargo / personal effects of passengers stowed in a manner that do not obstruct passenger escape in case of emergency.<div class="nt">(If No, to be RECTIFIED)</div>
                        </td>
                        <td class="cd">VS81221</td>
                        <td class="yn"><?= ck($ans, 16, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 16, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr class="sec">
                        <td colspan="6">TOTAL NUMBER OF OFFICERS AND CREW MEETS MINIMUM SAFE MANNING REQUIREMENT</td>
                    </tr>
                    <tr>
                        <td class="ref">MC 89, MC 148, MC 179</td>
                        <td class="it"><b>17.</b> The actual number, composition and qualifications of crew conform with the Minimum Safe Manning Certificate.<div class="nt">(If No, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS8142</td>
                        <td class="yn"><?= ck($ans, 17, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 17, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">MC 170</td>
                        <td class="it"><b>18.</b> Master / Patron / Boat Capt and Chief Engr./ Marine Diesel Mechanic possess valid licenses.<div class="nt">(If Not Valid, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS8216</td>
                        <td class="yn"><?= ck($ans, 18, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 18, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="pg">
        <div class="tw">
            <table class="t">
                <colgroup>
                    <col style="width:16%">
                    <col style="width:52%">
                    <col style="width:8%">
                    <col style="width:5%">
                    <col style="width:5%">
                    <col style="width:14%">
                </colgroup>
                <thead>
                    <tr>
                        <th class="ref">REF TO REG</th>
                        <th>ITEMS FOR INSPECTION</th>
                        <th>CODE</th>
                        <th>YES</th>
                        <th>NO</th>
                        <th>REMARKS</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="sec">
                        <td colspan="6">GOOD WEATHER/ SEA CONDITION PREVAILING</td>
                    </tr>
                    <tr>
                        <td class="ref">PCG MC 01-09</td>
                        <td class="it"><b>19.</b> Per latest PAGASA weather bulletin, the port of origin or route or port of destination of the motorbanca is not under Storm Signal Nr. 1.</td>
                        <td class="cd">VS82615</td>
                        <td class="yn"><?= ck($ans, 19, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 19, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">PCG MC 03-01</td>
                        <td class="it"><b>20.</b> In the absence of a Storm Signal in the area, assessment of the prevailing sea condition in the origin, motorbanca is allowed to sail.</td>
                        <td class="cd">VS82614</td>
                        <td class="yn"><?= ck($ans, 20, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 20, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr class="sec">
                        <td colspan="6">UPKEEP OF EQUIPMENT AND RUNNING LIGHTS VERIFIED</td>
                    </tr>
                    <tr>
                        <td class="ref">PMMR 1997 Chapter X and XI</td>
                        <td class="it"><b>21.</b> Required communications/ navigational equipment (if any) are in operating condition.<div class="nt">(If No, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS8745</td>
                        <td class="yn"><?= ck($ans, 21, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 21, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">PMMR 1997 Chapter X and XI</td>
                        <td class="it"><b>22.</b> Running lights or other lighting devices are operating.<div class="nt">(If No, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS8746</td>
                        <td class="yn"><?= ck($ans, 22, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 22, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr class="sec">
                        <td colspan="6">ALL ENGINES IN OPERATING CONDITION</td>
                    </tr>
                    <tr>
                        <td class="ref">PMMR 1997 Chapter V</td>
                        <td class="it"><b>23.</b> Engine(s) in operating condition.<div class="nt">(If No, DETAINABLE)</div>
                        </td>
                        <td class="cd">VS81516</td>
                        <td class="yn"><?= ck($ans, 23, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 23, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr class="sec">
                        <td colspan="6">RESPONSIBLE SHIPOWNER/CREW/TERMINAL CONTROL</td>
                    </tr>
                    <tr>
                        <td class="ref">MC 180, MC 2009-13, MC 2009-18</td>
                        <td class="it"><b>24.</b> Complete Passenger Manifest submitted prior departure.<div class="nt">(If No, to be RECTIFIED)</div>
                        </td>
                        <td class="cd">VS82404</td>
                        <td class="yn"><?= ck($ans, 24, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 24, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">MC 191</td>
                        <td class="it"><b>25.</b> No indication observed that any Officer is under the influence of alcohol or prohibited drugs.<div class="nt">(If No, to be RECTIFIED)</div>
                        </td>
                        <td class="cd">VS82412</td>
                        <td class="yn"><?= ck($ans, 25, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 25, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">MC 134, MC ____ Sec III.A.24</td>
                        <td class="it"><b>26.</b> Embarkation / Disembarkation ladder is provided with non-skid steps and guard rails.<div class="nt">(If No, to be RECTIFIED)</div>
                        </td>
                        <td class="cd">VS82408</td>
                        <td class="yn"><?= ck($ans, 26, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 26, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                    <tr>
                        <td class="ref">MC 134, MC ____ Sec III.A.24</td>
                        <td class="it"><b>27.</b> The Official Number is painted on both sides of the motorized banca<div class="nt">(If No, to be RECTIFIED)</div>
                        </td>
                        <td class="cd">VS82613</td>
                        <td class="yn"><?= ck($ans, 27, 'yes') ?></td>
                        <td class="yn"><?= ck($ans, 27, 'no') ?></td>
                        <td class="rm"></td>
                    </tr>
                </tbody>
            </table>
        </div>
        <div style="font-weight:700;font-size:8px;margin-top:3mm">INSTRUCTIONS:</div>
        <ol class="ins" style="margin-top:.5mm">
            <li>No vessel shall be cleared to depart for voyage unless it complies with all the applicable conditions (items of inspection) of this checklist.</li>
            <li>PDI shall be conducted “prior to a vessel’s departure”. The phrase “prior to a vessel’s departure” shall be reckoned from the time the Master has declared his intention to depart a port thru the submission of the Master’s Declaration of Safe Departure (MDSD) to a PCG Station or Detachment.</li>
            <li>For the use of this checklist, the term “passenger” shall mean any person carried onboard the vessel, whether paying or non-paying, except official members of the crew.</li>
            <li>When PDI is completed, ensure that no more passenger(s) shall be allowed to board the vessel for the voyage.</li>
            <li>PCG inspector(s) shall personally witness the departure of the vessel from port/ point of origin to ensure compliance to instruction 2.</li>
            <li>In areas where the same vessel makes multiple departures on same port on the same day, inspection may be conducted on the first departure except for checking the passenger manifest, carriage of excess passengers or reported deficiency or malfunctioning of ship’s equipment/machinery, verification shall be on a by trip basis.</li>
            <li>Appropriate MVR or EIAR shall be issued for violations of regulations discovered or observed during the inspection.</li>
            <li>Accomplished copies of this vessel departure checklist shall be maintained by each Coast Guard Station and Detachment for ready reference.</li>
            <li>VSEI team shall video record the specified points of inspection in reference to HPCG Memorandum dated 26 June 2012 re “Guidelines for Video Recording During MPDI”.</li>
        </ol>
        <div style="font-weight:700;font-size:10px;margin-top:3mm">Inspection conducted by:</div>
        <div class="foot">
            <div>
                <div class="v"><?= e($d['station']) ?></div>
                <div class="ln">Coast Guard Station/ Detachment</div>
            </div>
            <div>
                <div class="v"><?= e($d['inspector']) ?></div>
                <div class="ln">Name and Signature of Inspector</div>
            </div>
        </div>
        <div style="font-size:7px;margin-top:3mm">NOTE: PCG INSPECTORS SHOULD ALWAYS ENSURE THE ATTACHMENT OF FORM &ldquo;B&rdquo; AFTER THE INSPECTION</div>
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
                            filename: 'PDIC.pdf',
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
                                orientation: 'landscape'
                            }
                        })
                        .from(document.querySelector('.pg')).outputPdf('blob').then(function(b) {
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