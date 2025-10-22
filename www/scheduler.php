<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/logs/scheduler_error.log');
require_once __DIR__ . '/db.php';
date_default_timezone_set('Europe/Berlin');

/* ============================================================
   KONFIGURATION
   ============================================================ */
$arduino_ip   = "10.140.1.10";
$arduino_port = 8888;
$arduino_pass = "1234";

$relais_auf        = 1; // Tor-Auf (dauerhaft)
$relais_schliessen = 2; // Schließimpuls
$impuls_interval_min = 5;
$impuls_dauer_ms     = 500;

/* ============================================================
   UDP SENDEN
   ============================================================ */
function send_udp($ip, $port, $message) {
    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
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
$heute = date('Y-m-d');
$uhrzeit = date('H:i');
$minute = intval(date('i'));
$wochentag = date('N');
$jahr = date('Y');

$stmt = $pdo->query("SELECT * FROM torzeiten");
$torzeiten = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $torzeiten[$row['modus']] = $row;
}

$sondertag = $pdo->query("SELECT * FROM sondertage WHERE datum = '$heute'")->fetch(PDO::FETCH_ASSOC);
$feiertage = feiertage_bayern($jahr);

if ($sondertag) {
    $regel = "sonder";
    $status = $sondertag['status'];
} elseif (in_array($heute, $feiertage) || $wochentag >= 6) {
    $regel = "feiertag";
    $status = "geschlossen";
} else {
    $modus = (date('I') == 1) ? "sommer" : "winter";
    $regel = $modus;
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
   AKTUELLE BITMAP LADEN
   ============================================================ */
$stmt = $pdo->prepare("SELECT desired_state FROM relais_status WHERE ip = :ip LIMIT 1");
$stmt->execute(['ip' => $arduino_ip]);
$bitmap = $stmt->fetchColumn();
if (!$bitmap) $bitmap = "0000"; // Standardwert

$desired_bitmap = $bitmap;
$relais_index = $relais_auf - 1;

/* ============================================================
   TORSTATUS SETZEN IM BITMUSTER
   ============================================================ */
$aktueller_auf_status = $tor_offen ? "1" : "0";
$desired_bitmap[$relais_index] = $aktueller_auf_status;

$log = "[" . date('Y-m-d H:i:s') . "] Regel: $regel | Zeit: $uhrzeit | Tor: " . ($tor_offen ? "OFFEN" : "ZU");

/* ============================================================
   SCHLIEẞIMPULS ALLE 5 MINUTEN
   ============================================================ */
if (!$tor_offen && $minute % $impuls_interval_min == 0) {
    send_udp($arduino_ip, $arduino_port, "PASS=$arduino_pass;R{$relais_schliessen}=ON");
    usleep($impuls_dauer_ms * 1000);
    send_udp($arduino_ip, $arduino_port, "PASS=$arduino_pass;R{$relais_schliessen}=OFF");
    $log .= " → Schließimpuls gesendet";
}

/* ============================================================
   DATENBANK AKTUALISIEREN
   ============================================================ */
$stmt = $pdo->prepare("
    INSERT INTO relais_status (ip, desired_state, current_state, updated_at)
    VALUES (:ip, :desired, :desired, NOW())
    ON DUPLICATE KEY UPDATE desired_state = :desired, updated_at = NOW()
");
$stmt->execute(['ip' => $arduino_ip, 'desired' => $desired_bitmap]);

/* ============================================================
   LOGDATEI
   ============================================================ */
file_put_contents(__DIR__ . "/logs/tor.log", $log . "\n", FILE_APPEND);
echo nl2br(htmlspecialchars($log));
