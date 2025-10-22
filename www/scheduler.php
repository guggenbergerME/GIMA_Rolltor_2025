<?php
require_once 'db.php';
date_default_timezone_set('Europe/Berlin');

/* ============================================================
   KONFIGURATION
   ============================================================ */
$arduino_ip   = "10.140.1.10";
$arduino_port = 8888;
$arduino_pass = "1234";

$relais_auf   = 1;   // Tor-Auf (dauerhaft)
$relais_schliessen = 2; // Schließimpuls

$impuls_interval_min = 5;   // alle 5 Minuten Impuls
$impuls_dauer_ms     = 500; // Impulsdauer 0,5s

/* ============================================================
   UDP SENDEN
   ============================================================ */
function send_udp($ip, $port, $message) {
    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    socket_sendto($sock, $message, strlen($message), 0, $ip, $port);
    socket_close($sock);
}

/* ============================================================
   STATUS IN DB SPEICHERN
   ============================================================ */
function save_relais_status($pdo, $relais, $status) {
    $stmt = $pdo->prepare("REPLACE INTO relais_status (relais_nummer, status) VALUES (?, ?)");
    $stmt->execute([$relais, $status]);
}

/* ============================================================
   STATUS AUS DB LADEN
   ============================================================ */
function get_relais_status($pdo, $relais) {
    $stmt = $pdo->prepare("SELECT status FROM relais_status WHERE relais_nummer = ?");
    $stmt->execute([$relais]);
    return $stmt->fetchColumn();
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
   AKTUELLE UND LETZTE RELAIS-ZUSTÄNDE LADEN
   ============================================================ */
$letzter_auf_status = get_relais_status($pdo, $relais_auf);
$aktueller_auf_status = $tor_offen ? "ON" : "OFF";

$log = "[" . date('Y-m-d H:i:s') . "] Regel: $regel | Zeit: $uhrzeit | Tor: $aktueller_auf_status ";

/* ============================================================
   RELAIS-STATUS WIEDERHERSTELLUNG BEIM START
   ============================================================ */
if ($letzter_auf_status === false) {
    // Erststart – setze aktuellen Sollzustand
    send_udp($arduino_ip, $arduino_port, "PASS=$arduino_pass;R{$relais_auf}=$aktueller_auf_status");
    save_relais_status($pdo, $relais_auf, $aktueller_auf_status);
    $log .= "→ Initial gesetzt\n";
}
/* ============================================================
   STATUS-ÄNDERUNG STEUERN
   ============================================================ */
elseif ($letzter_auf_status !== $aktueller_auf_status) {
    send_udp($arduino_ip, $arduino_port, "PASS=$arduino_pass;R{$relais_auf}=$aktueller_auf_status");
    save_relais_status($pdo, $relais_auf, $aktueller_auf_status);
    $log .= "→ Relais $relais_auf = $aktueller_auf_status\n";
} else {
    $log .= "(keine Änderung)\n";
}

/* ============================================================
   SCHLIEẞIMPULS ALLE 5 MINUTEN
   ============================================================ */
if (!$tor_offen && $minute % $impuls_interval_min == 0) {
    send_udp($arduino_ip, $arduino_port, "PASS=$arduino_pass;R{$relais_schliessen}=ON");
    usleep($impuls_dauer_ms * 1000);
    send_udp($arduino_ip, $arduino_port, "PASS=$arduino_pass;R{$relais_schliessen}=OFF");
    save_relais_status($pdo, $relais_schliessen, "OFF");
    $log .= "→ Schließimpuls (Relais 2)\n";
}

/* ============================================================
   LOGDATEI
   ============================================================ */
file_put_contents(__DIR__ . "/tor.log", $log, FILE_APPEND);
echo nl2br(htmlspecialchars($log));
