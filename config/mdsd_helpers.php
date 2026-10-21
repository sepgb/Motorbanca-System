<?php
// MDSD: Master's Declaration of Safe Departure (Coast Guard Form C).
// The PCG answers the checklist when a boat is departed (admin/mdsd.php); the printable form (admin/mdsd_print.php) fills in the rest by itself.
require_once __DIR__ . '/live.php';

// Details that are the same for every departure. Change them here if they are not right.
const MDSD_STATION = 'Coast Guard Sub-Station Binangonan';   // "Station/Detachment"
const MDSD_BERTH = 'Binangonan Port';                        // "presently berthed / anchored at"
const MDSD_PLACE = 'Binangonan, Rizal';                      // "City/Municipality of"
const MDSD_INFANT_MAX = 3;                                   // age 0 to 3 = infant
const MDSD_CHILD_MAX = 11;                                   // age 4 to 11 = child (same as the fares page), 12 and up = adult

// Item 2 a) to l)
function mdsd_items()
{
    return [
        ['a', 'Completeness and validity of ships safety certificates and documents;'],
        ['b', 'Compliance with safe manning requirement and crew certification and qualification document validity;'],
        ['c', 'Officers and crew are in healthy and sound condition, not under the influence of alcohol or prohibited drugs and fit to perform duties and responsibilities for the intended voyage;'],
        ['d', 'Carriage of cargo and passengers are with authorized limits, and properly documented;'],
        ['e', 'Required communications and navigational equipment are complete and in good operating condition;'],
        ['f', 'Main and auxiliary engines are in good and operating condition;'],
        ['g', 'Fire-fighting and life-saving appliances are complete, in good operating condition, available and accessible to passengers;'],
        ['h', 'Proper ship stability and trim, cargo stowage securing and lashings are strictly observed;'],
        ['i', 'Prevailing weather conditions for intended voyage is safe and latest weather bulletin and advisory are available;'],
        ['j', 'The crew, passengers and cargoes have undergone the necessary security screening;'],
        ['k', 'Hull in excellent condition; and'],
        ['l', 'Attached documents are properly accomplished:']
    ];
}

// Item 2 l) i. to vii.: the attached documents
function mdsd_docs()
{
    return [
        ['d1', 'i.', 'Voyage Plan'],
        ['d2', 'ii.', 'Crew List'],
        ['d3', 'iii.', 'Passenger Main Manifest'],
        ['d4', 'iv.', 'Passenger Supplemental Manifest'],
        ['d5', 'v.', 'Coasting Manifest'],
        ['d6', 'vi.', 'Dangerous Cargo Manifest and Permit to Carry Dangerous Cargo'],
        ['d7', 'vii.', 'Cargo Manifest']
    ];
}

// Every question that needs a Yes / No / N/A answer
function mdsd_keys()
{
    $k = [];
    foreach (mdsd_items() as $i) $k[] = $i[0];
    foreach (mdsd_docs() as $d) $k[] = $d[0];
    return $k;
}

function mdsd_table()
{
    static $done;
    if ($done) return;
    $done = true;
    try {
        db()->exec("CREATE TABLE IF NOT EXISTS trip_mdsd (trip_id INT PRIMARY KEY, answers TEXT NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (trip_id) REFERENCES trips(id) ON DELETE CASCADE) ENGINE=InnoDB");
    } catch (PDOException $x) {
    }
}

// Save the answers of a trip: ['a' => 'yes', 'b' => 'no', 'd1' => 'na', ...]
function mdsd_save($tid, $ans)
{
    mdsd_table();
    db()->prepare("INSERT INTO trip_mdsd (trip_id, answers) VALUES (?, ?) ON DUPLICATE KEY UPDATE answers=VALUES(answers)")->execute([(int)$tid, json_encode($ans)]);
}

function mdsd_get($tid)
{
    mdsd_table();
    $s = db()->prepare("SELECT answers FROM trip_mdsd WHERE trip_id=?");
    $s->execute([(int)$tid]);
    $r = $s->fetchColumn();
    return $r ? (json_decode($r, true) ?: []) : [];
}

// Everything on the form that fills in by itself, as ready-to-print text. Returns null if the trip does not exist.
function mdsd_data($tid)
{
    $s = db()->prepare("SELECT t.id, t.trip_date, t.departure_time, t.cluster_id, m.motorbanca_name, m.owner_name, m.capacity, c.name cluster
        FROM trips t JOIN motorbancas m ON m.id=t.motorbanca_id JOIN clusters c ON c.id=t.cluster_id WHERE t.id=?");
    $s->execute([(int)$tid]);
    $t = $s->fetch();
    if (!$t) return null;
    $tc = trip_crew_get($t['id']);
    $master = '';
    foreach (($tc['crew'] ?? []) as $c) if ($c['position'] === 'BOAT CAPTAIN') $master = $c['name'];
    if ($master === '') $master = $t['owner_name'];
    $ps = db()->prepare("SELECT age, barangay FROM passengers WHERE trip_id=?");
    $ps->execute([$t['id']]);
    $adults = $children = $infants = 0;
    foreach ($ps->fetchAll() as $p) {
        $age = (int)$p['age'];
        if ($age <= MDSD_INFANT_MAX) $infants++;
        elseif ($age <= MDSD_CHILD_MAX) $children++;
        else $adults++;
    }
    // "sail for": the barangays of the boat's cluster, with "and" before the last one (Kasile and Bombong / A, B and C)
    $bq = db()->prepare("SELECT name FROM barangays WHERE cluster_id=? ORDER BY id");
    $bq->execute([$t['cluster_id']]);
    $brgys = $bq->fetchAll(PDO::FETCH_COLUMN);
    $lastBrgy = array_pop($brgys);
    $sailFor = $brgys ? implode(', ', $brgys) . ' and ' . $lastBrgy : (string)$lastBrgy;
    $when = strtotime($t['departure_time'] ?: ($t['trip_date'] . ' 00:00:00'));
    return [
        'vessel' => $t['motorbanca_name'],
        'voyage' => (string)$t['id'],
        'date' => date('F j, Y', $when),
        'station' => MDSD_STATION,
        'master' => $master,
        'berthed' => MDSD_BERTH,
        'sail_for' => $sailFor !== '' ? $sailFor : $t['cluster'],
        'hrs' => $t['departure_time'] ? date('H:i', $when) : '',
        'capacity' => (string)(int)$t['capacity'],
        'total' => (string)($adults + $children + $infants),
        'adults' => (string)$adults,
        'children' => (string)$children,
        'infants' => (string)$infants,
        'cargo' => 'NONE',
        'sign_day' => date('jS', $when) . ' day of ' . date('F Y', $when),
        'place' => MDSD_PLACE,
        'contact' => $tc['contact'] ?? '',
        'recv_date' => date('F j, Y', $when),
        'recv_time' => $t['departure_time'] ? date('g:i A', $when) : '',
    ];
}
