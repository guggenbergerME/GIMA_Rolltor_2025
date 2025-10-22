<?php
// www/api/arduino-relay-status.php
// (c) 2025 Tobias Guggenberger

require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

date_default_timezone_set('Europe/Berlin');

// --- JSON einlesen ---
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['ip']) || !isset($data['status'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON: expected {"ip":"x.x.x.x", "status":"1010"}']);
    exit;
}

$ip = $data['ip'];
$current = $data['status'];

// --- Datenbankverbindung ---
try {
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Sollzustand abrufen
    $stmt = $pdo->prepare("SELECT desired_state, current_state FROM relais_status WHERE ip = :ip LIMIT 1");
    $stmt->execute(['ip' => $ip]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        // Falls unbekannte IP → Eintrag anlegen
        $insert = $pdo->prepare("INSERT INTO relais_status (ip, desired_state, current_state) VALUES (:ip, :desired, :current)");
        $insert->execute(['ip' => $ip, 'desired' => $current, 'current' => $current]);
        $desired = $current;
        $action = "NEW_ENTRY";
    } else {
        $desired = $row['desired_state'];

        // Istzustand aktualisieren
        $update = $pdo->prepare("UPDATE relais_status SET current_state = :current, updated_at = NOW() WHERE ip = :ip");
        $update->execute(['current' => $current, 'ip' => $ip]);

        // Vergleich Soll/Ist
        if ($desired !== $current) {
            $action = "UPDATED";
        } else {
            $action = "UNCHANGED";
        }
    }

    // Log-Eintrag schreiben
    $log = $pdo->prepare("INSERT INTO relais_log (ip, reported_state, desired_state, action_taken) VALUES (:ip, :reported, :desired, :action)");
    $log->execute(['ip' => $ip, 'reported' => $current, 'desired' => $desired, 'action' => $action]);

    // Antwort an Arduino
    echo json_encode(['desired' => $desired]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
?>
