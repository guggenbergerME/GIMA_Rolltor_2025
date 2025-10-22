<?php
require_once '../db.php';
date_default_timezone_set('Europe/Berlin');

// === Feiertagsberechnung (Bayern) ===
function feiertage_bayern($jahr) {
    $ostern = easter_date($jahr);
    return [
        'Neujahr' => "$jahr-01-01",
        'Heilige Drei Könige' => "$jahr-01-06",
        'Tag der Arbeit' => "$jahr-05-01",
        'Mariä Himmelfahrt' => "$jahr-08-15",
        'Tag der Deutschen Einheit' => "$jahr-10-03",
        'Allerheiligen' => "$jahr-11-01",
        '1. Weihnachtstag' => "$jahr-12-25",
        '2. Weihnachtstag' => "$jahr-12-26",
        'Karfreitag' => date('Y-m-d', $ostern - 2 * 86400),
        'Ostermontag' => date('Y-m-d', $ostern + 1 * 86400),
        'Christi Himmelfahrt' => date('Y-m-d', $ostern + 39 * 86400),
        'Pfingstmontag' => date('Y-m-d', $ostern + 50 * 86400),
        'Fronleichnam' => date('Y-m-d', $ostern + 60 * 86400)
    ];
}

// === Datum & Zeit ===
$jetzt = new DateTime('now', new DateTimeZone('Europe/Berlin'));
$datumHeute = $jetzt->format('Y-m-d');
$zeitJetzt = $jetzt->format('H:i:s');
$wochentag = (int)$jetzt->format('N');
$modus = ($jetzt->format('I') == 1) ? 'sommer' : 'winter';

// === 1️⃣ Prüfen, ob ein Sondertag existiert ===
$stmt = $pdo->prepare("SELECT status, kommentar FROM sondertage WHERE datum = ?");
$stmt->execute([$datumHeute]);
$sonder = $stmt->fetch(PDO::FETCH_ASSOC);

if ($sonder) {
    echo json_encode([
        'status' => $sonder['status'],
        'grund' => 'Sondertag: ' . ($sonder['kommentar'] ?? 'manuell gesetzt'),
        'zeit' => $zeitJetzt
    ]);
    exit;
}

// === 2️⃣ Feiertagsprüfung (Bayern) ===
$feiertage = feiertage_bayern((int)$jetzt->format('Y'));
if (in_array($datumHeute, $feiertage)) {
    $name = array_search($datumHeute, $feiertage);
    echo json_encode([
        'status' => 'geschlossen',
        'grund' => "Feiertag: $name",
        'zeit' => $zeitJetzt
    ]);
    exit;
}

// === 3️⃣ Wochenende prüfen ===
if ($wochentag >= 6) {
    echo json_encode([
        'status' => 'geschlossen',
        'grund' => 'Wochenende',
        'zeit' => $zeitJetzt
    ]);
    exit;
}

// === 4️⃣ Zeiten aus torzeiten lesen ===
$stmt = $pdo->prepare("SELECT startzeit, endzeit FROM torzeiten WHERE modus = ?");
$stmt->execute([$modus]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    echo json_encode(['error' => "Keine Zeiten für Modus $modus"]);
    exit;
}

$status = ($zeitJetzt >= $row['startzeit'] && $zeitJetzt <= $row['endzeit'])
    ? 'offen'
    : 'geschlossen';

header('Content-Type: application/json; charset=utf-8');
echo json_encode([
    'modus' => $modus,
    'status' => $status,
    'zeit' => $zeitJetzt,
    'von' => $row['startzeit'],
    'bis' => $row['endzeit']
]);
