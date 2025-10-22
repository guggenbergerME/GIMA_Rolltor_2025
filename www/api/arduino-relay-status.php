<?php
// arduino-relay-status.php
// ---------------------------------------------------------
// Empfängt JSON vom Arduino, prüft Sollstatus in DB,
// antwortet mit gewünschter Relais-Bitmap z. B. {"desired":"1010"}
// ---------------------------------------------------------

require_once '../db.php'; // <-- Verbindung wie in heute.php
date_default_timezone_set('Europe/Berlin');

header('Content-Type: application/json; charset=utf-8');

// ---------------------------------------------------------
// JSON-Eingang vom Arduino lesen
// ---------------------------------------------------------
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!$data || !isset($data['device_ip'])) {
    http_response_code(400);
    echo json_encode(['error' => 'invalid input', 'received' => $raw]);
    exit;
}

// Beispiel-Eingang:
// {"device_ip":"10.140.1.10","r1":1,"r2":0,"r3":1,"r4":0}

$device_ip = $data['device_ip'];
$ist = [
    'r1' => isset($data['r1']) ? (int)$data['r1'] : 0,
    'r2' => isset($data['r2']) ? (int)$data['r2'] : 0,
    'r3' => isset($data['r3']) ? (int)$data['r3'] : 0,
    'r4' => isset($data['r4']) ? (int)$data['r4'] : 0,
];

// ---------------------------------------------------------
// Soll-Zustand aus Datenbank abrufen oder Datensatz anlegen
// ---------------------------------------------------------
$stmt = $pdo->prepare("SELECT r1, r2, r3, r4 FROM relais_status WHERE device_ip = ?");
$stmt->execute([$device_ip]);
$soll = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$soll) {
    // Gerät noch nicht vorhanden → neuen Eintrag anlegen
    $stmt = $pdo->prepare("INSERT INTO relais_status (device_ip, r1, r2, r3, r4, updated_at)
                           VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->execute([$device_ip, $ist['r1'], $ist['r2'], $ist['r3'], $ist['r4']]);
    $soll = $ist; // Soll = Ist beim ersten Kontakt
} else {
    // Bestehenden Datensatz aktualisieren (aktuellen Status speichern)
    $stmt = $pdo->prepare("UPDATE relais_status
                           SET r1=?, r2=?, r3=?, r4=?, updated_at=NOW()
                           WHERE device_ip=?");
    $stmt->execute([$ist['r1'], $ist['r2'], $ist['r3'], $ist['r4'], $device_ip]);
}

// ---------------------------------------------------------
// Bitmap bilden & prüfen
// ---------------------------------------------------------
$bitmap_soll = sprintf("%d%d%d%d", $soll['r1'], $soll['r2'], $soll['r3'], $soll['r4']);
$bitmap_ist  = sprintf("%d%d%d%d", $ist['r1'],  $ist['r2'],  $ist['r3'],  $ist['r4']);
$changed = ($bitmap_soll !== $bitmap_ist);

// ---------------------------------------------------------
// Log schreiben (optional)
// ---------------------------------------------------------
try {
    $stmt = $pdo->prepare("INSERT INTO relais_log (device_ip, ist, soll, changed, created_at)
                           VALUES (?, ?, ?, ?, NOW())");
    $stmt->execute([$device_ip, $bitmap_ist, $bitmap_soll, $changed ? 1 : 0]);
} catch (Exception $e) {
    // Logtabelle ist optional – kein Abbruch bei Fehler
}

// ---------------------------------------------------------
// Antwort an Arduino
// ---------------------------------------------------------
echo json_encode(['desired' => $bitmap_soll]);
?>
