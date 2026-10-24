<?php
// PDIC: Pre-Departure Inspection Checklist (Coast Guard Form D-3).
// The PCG answers it right after the MDSD when a boat is departed (admin/mdsd.php); the printable form (admin/pdic_print.php) fills in the rest by itself.
require_once __DIR__ . '/mdsd_helpers.php';

// "Type of Trading" on the form. Change it here if it is not right. Hull Material, GT and NT are not kept by the system, so they are left blank on the print.
const PDIC_TRADING = 'Passenger';

// The checklist: sections, each with its numbered items (1 to 27)
function pdic_sections()
{
    return [
        ['COMPLETE AND VALID SHIP SAFETY CERTIFICATES AND DOCUMENTS', [
            ['n' => 1, 'ref' => 'MC 16/MC 2006-06', 'text' => 'Certificate of Public Convenience (CPC) is valid. Authorized route (Ports of Call) & area of operation is observed', 'note' => 'If Not Valid, DETAINABLE', 'codes' => ['VS8113']],
            ['n' => 2, 'ref' => 'MC 177', 'text' => 'Certificate of Vessel Registry is valid', 'note' => 'If Not Valid, DETAINABLE', 'codes' => ['VS8115']],
            ['n' => 3, 'ref' => 'MC 177', 'text' => 'Certificate of Ownership is valid', 'note' => 'If Not Valid, DETAINABLE', 'codes' => ['VS8112']],
            ['n' => 4, 'ref' => 'MC 110', 'text' => 'Coastwise/ Bay & River License is valid', 'note' => 'If Not Valid, DETAINABLE', 'codes' => ['VS8118']],
            ['n' => 5, 'ref' => 'MC 134', 'text' => 'Certificate of Compliance is valid', 'note' => 'If Not Valid, DETAINABLE', 'codes' => ['VS8120']],
            ['n' => 6, 'ref' => 'MC 203', 'text' => 'Passenger / Cargo Ship Safety Certificate is valid', 'note' => 'If Not Valid, DETAINABLE', 'codes' => ['VS8148', 'VS8104']],
            ['n' => 7, 'ref' => 'MC 148; MC 179', 'text' => 'Minimum Safe Manning Certificate is valid', 'note' => 'If Not Valid, DETAINABLE', 'codes' => ['VS8142']],
            ['n' => 8, 'ref' => 'MC 2007-04', 'text' => 'Tonnage Measurement Certificate is valid', 'note' => 'If Not Valid, DETAINABLE', 'codes' => ['VS8165']],
            ['n' => 9, 'ref' => 'MC 90, MC ____ Sec III A.36', 'text' => 'Certificate of Number (OPTIONAL FOR 15 G.T. AND BELOW)', 'note' => 'If Not Valid, DETAINABLE', 'codes' => ['VS8111']],
            ['n' => 10, 'ref' => 'RA 9295, MC 2009-18', 'text' => 'Passenger Insurance Cover', 'note' => 'If Not Valid, DETAINABLE', 'codes' => ['VS8147']],
            ['n' => 11, 'ref' => 'HPCG MC 08-12', 'text' => 'Master\'s Declaration of Safe Departure (MDSD) properly accomplished, submitted with the necessary attachments:', 'note' => 'If incomplete, to be RECTIFIED', 'codes' => ['VS8144']]
        ]],
        ['OVERLOADING OF CARGO AND EXCESS PASSENGERS STRICTLY NOT ALLOWED', [
            ['n' => 12, 'ref' => 'MC 180, MC 2009-13, MC 2009-18', 'text' => 'Passenger Manifest properly accomplished and submitted prior departure.', 'note' => 'If No, to be RECTIFIED', 'codes' => ['VS82404', 'VS82606', 'VS82603'], 'bullets' => ['Number of passengers in the manifest within the authorized passenger capacity;', 'Number of passengers in the manifest corresponds to the actual passengers physically onboard;', 'Random check of names in the manifest and actual passengers onboard matches']],
            ['n' => 13, 'ref' => 'HPCG MC 04-98, MC 180, MC 2009-13, MC 2009-18', 'text' => 'Head counting of passengers conducted and finds the vessel carrying only the authorized number of passengers per MARINA issued PSSC', 'note' => 'If No, to be RECTIFIED', 'codes' => ['VS82606'], 'bullets' => ['Number of Passengers onboard: {pax}']]
        ]],
        ['ADEQUATE/ ACCESSIBLE LIFEJACKETS', [
            ['n' => 14, 'ref' => 'MC 2008-08', 'text' => 'All passengers and crews in open deck boats are wearing lifejackets prior departure.', 'note' => 'If No, to be RECTIFIED', 'codes' => ['VS82602']]
        ]],
        ['SHIP STABILITY AND TRIM, CARGO SECURING / LASHING ASCERTAINED', [
            ['n' => 15, 'ref' => 'HPCG MC 04-98', 'text' => 'Per visual assessment, motorbanca is in her upright normal condition.', 'note' => 'If No, DETAINABLE', 'codes' => ['VS8926']],
            ['n' => 16, 'ref' => 'MC 114', 'text' => 'Cargo / personal effects of passengers stowed in a manner that do not obstruct passenger escape in case of emergency.', 'note' => 'If No, to be RECTIFIED', 'codes' => ['VS81221']]
        ]],
        ['TOTAL NUMBER OF OFFICERS AND CREW MEETS MINIMUM SAFE MANNING REQUIREMENT', [
            ['n' => 17, 'ref' => 'MC 89, MC 148, MC 179', 'text' => 'The actual number, composition and qualifications of crew conform with the Minimum Safe Manning Certificate.', 'note' => 'If No, DETAINABLE', 'codes' => ['VS8142']],
            ['n' => 18, 'ref' => 'MC 170', 'text' => 'Master / Patron / Boat Capt and Chief Engr./ Marine Diesel Mechanic possess valid licenses.', 'note' => 'If Not Valid, DETAINABLE', 'codes' => ['VS8216']]
        ]],
        ['GOOD WEATHER/ SEA CONDITION PREVAILING', [
            ['n' => 19, 'ref' => 'PCG MC 01-09', 'text' => 'Per latest PAGASA weather bulletin, the port of origin or route or port of destination of the motorbanca is not under Storm Signal Nr. 1.', 'note' => '', 'codes' => ['VS82615']],
            ['n' => 20, 'ref' => 'PCG MC 03-01', 'text' => 'In the absence of a Storm Signal in the area, assessment of the prevailing sea condition in the origin, motorbanca is allowed to sail.', 'note' => '', 'codes' => ['VS82614']]
        ]],
        ['UPKEEP OF EQUIPMENT AND RUNNING LIGHTS VERIFIED', [
            ['n' => 21, 'ref' => 'PMMR 1997 Chapter X and XI', 'text' => 'Required communications/ navigational equipment (if any) are in operating condition.', 'note' => 'If No, DETAINABLE', 'codes' => ['VS8745']],
            ['n' => 22, 'ref' => 'PMMR 1997 Chapter X and XI', 'text' => 'Running lights or other lighting devices are operating.', 'note' => 'If No, DETAINABLE', 'codes' => ['VS8746']]
        ]],
        ['ALL ENGINES IN OPERATING CONDITION', [
            ['n' => 23, 'ref' => 'PMMR 1997 Chapter V', 'text' => 'Engine(s) in operating condition.', 'note' => 'If No, DETAINABLE', 'codes' => ['VS81516']]
        ]],
        ['RESPONSIBLE SHIPOWNER/CREW/TERMINAL CONTROL', [
            ['n' => 24, 'ref' => 'MC 180, MC 2009-13, MC 2009-18', 'text' => 'Complete Passenger Manifest submitted prior departure.', 'note' => 'If No, to be RECTIFIED', 'codes' => ['VS82404']],
            ['n' => 25, 'ref' => 'MC 191', 'text' => 'No indication observed that any Officer is under the influence of alcohol or prohibited drugs.', 'note' => 'If No, to be RECTIFIED', 'codes' => ['VS82412']],
            ['n' => 26, 'ref' => 'MC 134, MC ____ Sec III.A.24', 'text' => 'Embarkation / Disembarkation ladder is provided with non-skid steps and guard rails.', 'note' => 'If No, to be RECTIFIED', 'codes' => ['VS82408']],
            ['n' => 27, 'ref' => 'MC 134, MC ____ Sec III.A.24', 'text' => 'The Official Number is painted on both sides of the motorized banca', 'note' => 'If No, to be RECTIFIED', 'codes' => ['VS82613']]
        ]]
    ];
}

// 1 to 27
function pdic_numbers()
{
    $n = [];
    foreach (pdic_sections() as $s) foreach ($s[1] as $it) $n[] = $it['n'];
    return $n;
}

function pdic_table()
{
    static $done;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS trip_pdic (trip_id INT PRIMARY KEY, answers TEXT NOT NULL, inspector VARCHAR(100) NOT NULL, started DATETIME NULL, completed DATETIME NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE) ENGINE=InnoDB");
    } catch (PDOException $x) {
    }
    try {   // the "Remarks" column of the form (one short note per item); older databases get the column here
        if (!db()->query("SHOW COLUMNS FROM trip_pdic LIKE 'remarks'")->fetch()) db()->exec("ALTER TABLE trip_pdic ADD COLUMN remarks TEXT NULL");
    } catch (PDOException $x) {
    }
}

// Save the PDIC of a trip: answers [1 => 'yes', 2 => 'no', ...], the inspector's name, when the inspection started,
// and the remarks [3 => 'Expires next month', ...] (optional, only the items that have one)
function pdic_save($tid, $ans, $inspector, $started, $remarks = [])
{
    pdic_table();
    db()->prepare("INSERT INTO trip_pdic (trip_id, answers, inspector, started, completed, remarks) VALUES (?, ?, ?, ?, NOW(), ?)
        ON DUPLICATE KEY UPDATE answers=VALUES(answers), inspector=VALUES(inspector), started=VALUES(started), completed=VALUES(completed), remarks=VALUES(remarks)")
        ->execute([(int)$tid, json_encode($ans), $inspector, $started, json_encode((object)$remarks)]);
}

// The saved PDIC of a trip, or [] if there is none: ['answers' => [...], 'remarks' => [...], 'inspector' => '', 'started' => '', 'completed' => '']
function pdic_get($tid)
{
    pdic_table();
    $s = db()->prepare("SELECT answers, remarks, inspector, started, completed FROM trip_pdic WHERE trip_id=?");
    $s->execute([(int)$tid]);
    $r = $s->fetch();
    if (!$r) return [];
    $r['answers'] = json_decode($r['answers'], true) ?: [];
    $r['remarks'] = json_decode((string)($r['remarks'] ?? ''), true) ?: [];
    return $r;
}

// Everything on the form that fills in by itself, as ready-to-print text. Returns null if the trip does not exist.
function pdic_data($tid)
{
    $s = db()->prepare("SELECT t.id, t.cluster_id, t.trip_date, t.departure_time, m.motorbanca_name, m.owner_name, c.name cluster
        FROM trips t JOIN motorbancas m ON m.id=t.motorbanca_id JOIN clusters c ON c.id=t.cluster_id WHERE t.id=?");
    $s->execute([(int)$tid]);
    $t = $s->fetch();
    if (!$t) return null;
    $b = db()->prepare("SELECT name FROM barangays WHERE cluster_id=? ORDER BY id");
    $b->execute([$t['cluster_id']]);
    $names = $b->fetchAll(PDO::FETCH_COLUMN);
    $pc = db()->prepare("SELECT COUNT(*) FROM passengers WHERE trip_id=?");
    $pc->execute([$t['id']]);
    $g = pdic_get($t['id']);
    $when = strtotime($t['departure_time'] ?: ($t['trip_date'] . ' 00:00:00'));
    $from = !empty($g['started']) ? strtotime($g['started']) : 0;
    $to = !empty($g['completed']) ? strtotime($g['completed']) : 0;
    return [
        'vessel' => $t['motorbanca_name'],
        'owner' => $t['owner_name'],
        'trading' => PDIC_TRADING,
        'hull' => '',
        'gt' => '',
        'nt' => '',
        'date' => date('F j, Y', $when),
        'port' => MDSD_BERTH,
        'destination' => $t['cluster'] . ($names ? ' (' . implode(', ', $names) . ')' : ''),
        'started' => $from ? date('g:i A', $from) : '',
        'completed' => $to ? date('g:i A', $to) : '',
        'pax' => (string)(int)$pc->fetchColumn(),
        'station' => MDSD_STATION,
        'inspector' => $g['inspector'] ?? '',
    ];
}

// The checklist rows of the modal / page, with a Yes / No choice and an optional remark each. $cur = answers already ticked, $rem = remarks already typed.
function pdic_rows($cur, $rem = [])
{
    $h = '';
    foreach (pdic_sections() as $s) {
        $h .= "<h2 class='md-h'>" . e($s[0]) . "</h2>";
        foreach ($s[1] as $it) {
            $k = $it['n'];
            $t = "<b>" . $k . ".</b> " . e($it['text']);
            if (!empty($it['bullets'])) {
                $t .= "<ul class='md-bl'>";
                foreach ($it['bullets'] as $bl) if (strpos($bl, '{pax}') === false) $t .= "<li>" . e($bl) . "</li>";
                $t .= "</ul>";
            }
            if ($it['note'] !== '') $t .= "<span class='md-nt'>(" . e($it['note']) . ")</span>";
            $rm = "<input class='md-rm' name='r[$k]' maxlength='120' placeholder='Remarks (optional)' autocomplete='off' aria-label='Remarks for item $k' value='" . e($rem[$k] ?? '') . "'>";
            $o = '';
            foreach (['yes' => 'Yes', 'no' => 'No'] as $val => $lab)
                $o .= "<label class='md-o md-$val'><input type='radio' name='p[$k]' value='$val'" . (($cur[$k] ?? '') === $val ? ' checked' : '') . "><span>$lab</span></label>";
            $h .= "<div class='md-q md-two' role='radiogroup' aria-labelledby='pd-$k'><div class='md-t' id='pd-$k'>$t</div><div class='md-ans'><div class='md-opts'>$o</div>$rm</div></div>";
        }
    }
    return $h;
}
