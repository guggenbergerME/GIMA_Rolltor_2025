<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/scheduler_error.log');
require_once 'db.php';
date_default_timezone_set('Europe/Berlin');

/* ============================================================
   KONFIGURATION
   ============================================================ */
$arduino_ip   = "10.140.1.10";
$arduino_port = 8888;
$arduino_pass = "1234";

$relais_auf        = 1;   // Relais 1 = Daueröffnung
$relais_schliessen = 2;   // Relais 2 = Schließimpuls
$impuls_dauer_ms   = 1000; // Impulsdauer 1 Sekunde
$impuls_interval_min = 5;  // nur alle 3 Minuten ein Impuls erlaubt
$impuls_sperrzeit_sec = 4; // 2 Minuten Sperrzeit seit letztem Impuls

$last_pulse_file = __DIR__ . '/last_pulse.txt';

/* ============================================================
   UDP SENDEN
   ============================================================ */
function send_udp($ip, $port, $message) {
    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if ($sock === false) {
        error_log("Socket konnte nicht erstellt werden");
        return;
    }
    socket_sendto($sock, $message, strlen($message), 0, $ip, $port);
    socket_close($sock);
}

/* ============================================================
   FEIERTAGE BAYERN
   ============================================================ */
function feiertage_bayern($jahr) {
    $ostern = easter_date($jahr);
    return [
        date('Y-m-d', $ostern - 2 * 86400),
        date('Y-m-d', $ostern + 1 * 86400),
        date('Y-m-d', $ostern + 39 * 86400),
        date('Y-m-d', $ostern + 50 * 86400),
        date('Y-m-d', $ostern + 60 * 86400),
        "$jahr-01-01", "$jahr-01-06", "$jahr-05-01",
        "$jahr-08-15", "$jahr-10-03", "$jahr-11-01",
        "$jahr-12-25", "$jahr-12-26"
    ];
}

/* ============================================================
   AKTUELLE REGEL
   ============================================================ */
$heute     = date('Y-m-d');
$uhrzeit   = date('H:i');
$minute    = intval(date('i'));
$wochentag = date('N');
$jahr      = date('Y');

$stmt = $pdo->query("SELECT * FROM torzeiten");
$torzeiten = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $torzeiten[$row['modus']] = $row;
}

$sondertag = $pdo->query("SELECT * FROM sondertage WHERE datum = '$heute'")->fetch(PDO::FETCH_ASSOC);
$feiertage = feiertage_bayern($jahr);

if ($sondertag) {
    $regel  = "sonder";
    $status = $sondertag['status'];
} elseif (in_array($heute, $feiertage) || $wochentag >= 6) {
    $regel  = "feiertag";
    $status = "geschlossen";
} else {
    $modus  = (date('I') == 1) ? "sommer" : "winter";
    $regel  = $modus;
    $status = "automatisch";
}

/* ============================================================
   ENTSCHEIDUNG: TOR AUF ODER ZU
   ============================================================ */
$tor_offen = false;
if ($regel === "sonder") {
    $tor_offen = ($status === "offen");
} elseif ($regel === "feiertag") {
    $tor_offen = false;
} else {
    $start = $torzeiten[$modus]['startzeit'];
    $ende  = $torzeiten[$modus]['endzeit'];
    $tor_offen = ($uhrzeit >= $start && $uhrzeit < $ende);
}

/* ============================================================
   AKTUELLEN UND LETZTEN RELAISZUSTAND LADEN
   ============================================================ */
$stmt = $pdo->prepare("SELECT desired_state, current_state FROM relais_status WHERE ip = :ip LIMIT 1");
$stmt->execute(['ip' => $arduino_ip]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$desired = $row['desired_state'] ?? '0000';
$current = $row['current_state'] ?? $desired;
$bitmap  = str_split($desired);

// Relais 1 = Daueröffnung
$letzter_auf_status    = $bitmap[$relais_auf - 1];
$aktueller_auf_status  = $tor_offen ? '1' : '0';

$log  = "[" . date('Y-m-d H:i:s') . "] Regel: $regel | Zeit: $uhrzeit | Tor: ";
$log .= $tor_offen ? "OFFEN" : "GESCHLOSSEN";

/* ============================================================
   BITMAP ANPASSEN (Relais 1)
   ============================================================ */
if ($letzter_auf_status !== $aktueller_auf_status) {
    $bitmap[$relais_auf - 1] = $aktueller_auf_status;
    $desired = implode('', $bitmap);
    $msg = "PASS=$arduino_pass;BITMAP=$desired";
    send_udp($arduino_ip, $arduino_port, $msg);
    $log .= " → Relais 1 (" . ($tor_offen ? "ON" : "OFF") . ")";
    
    $stmt = $pdo->prepare("
        INSERT INTO relais_status (ip, desired_state, current_state, updated_at)
        VALUES (:ip, :desired, :current, NOW())
        ON DUPLICATE KEY UPDATE desired_state=:desired, updated_at=NOW()
    ");
    $stmt->execute(['ip' => $arduino_ip, 'desired' => $desired, 'current' => $current]);
} else {
    $log .= " (keine Änderung)";
}

/* ============================================================
   SCHLIEẞIMPULS (Relais 2) AUSSERHALB DER ÖFFNUNGSZEITEN
   ============================================================ */
$send_pulse = false;
$now = time();

// Prüfen, ob Sperrzeit eingehalten wurde
if (file_exists($last_pulse_file)) {
    $last_pulse_time = intval(file_get_contents($last_pulse_file));
    $since_last_pulse = $now - $last_pulse_time;
    if ($since_last_pulse >= $impuls_sperrzeit_sec) {
        $send_pulse = true;
    } else {
        $log .= " → Kein Impuls (zu früh, " . $since_last_pulse . "s seit letztem)\n";
    }
} else {
    $send_pulse = true; // Erste Ausführung
}

// Impuls nur senden, wenn außerhalb Öffnungszeit UND Intervall erfüllt
if (!$tor_offen && $send_pulse && $minute % $impuls_interval_min == 0) {
    $log .= " → Schließimpuls (Relais 2 für {$impuls_dauer_ms} ms)\n";
    $msg = "PASS=$arduino_pass;PULSE={$relais_schliessen},{$impuls_dauer_ms}";
    send_udp($arduino_ip, $arduino_port, $msg);
    file_put_contents($last_pulse_file, $now); // Zeitpunkt speichern
} else {
    $log .= "\n";
}

/* ============================================================
   LOGDATEI SCHREIBEN
   ============================================================ */
file_put_contents(__DIR__ . "/tor.log", $log, FILE_APPEND);
echo nl2br(htmlspecialchars($log));
