<?php
require_once 'db.php';
date_default_timezone_set('Europe/Berlin');

/* ============================================================
   MONATSNAVIGATION & PARAMETER
   ============================================================ */
$currentMonth = isset($_GET['month']) ? intval($_GET['month']) : intval(date('m'));
$currentYear  = isset($_GET['year'])  ? intval($_GET['year'])  : intval(date('Y'));

if ($currentMonth < 1) { $currentMonth = 1; }
if ($currentMonth > 12) { $currentMonth = 12; }

$monthName = (new IntlDateFormatter(
    'de_DE',
    IntlDateFormatter::FULL,
    IntlDateFormatter::NONE,
    'Europe/Berlin',
    IntlDateFormatter::GREGORIAN,
    'MMMM yyyy'
))->format(new DateTime("$currentYear-$currentMonth-01"));

$prevMonth = $currentMonth - 1;
$nextMonth = $currentMonth + 1;
$prevYear  = $currentYear;
$nextYear  = $currentYear;
if ($prevMonth < 1) { $prevMonth = 12; $prevYear--; }
if ($nextMonth > 12) { $nextMonth = 1; $nextYear++; }

/* ============================================================
   FEIERTAGE BAYERN
   ============================================================ */
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

/* ============================================================
   ZEITEN UND SONDER- / FEIERTAGE LADEN
   ============================================================ */
$stmt = $pdo->query("SELECT * FROM torzeiten");
$torzeiten = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $torzeiten[$row['modus']] = $row;
}

$stmt = $pdo->query("SELECT * FROM sondertage");
$sondertage_map = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $sondertage_map[$row['datum']] = $row;
}

$feiertage = feiertage_bayern($currentYear);
$daysInMonth = cal_days_in_month(CAL_GREGORIAN, $currentMonth, $currentYear);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Rolltor Vorschau – <?= htmlspecialchars($monthName) ?></title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<!-- ============================================================
     NAVIGATION (TOPBAR)
     ============================================================ -->
<header class="navbar">
  <img src="logo.png" class="nav-logo" alt="GIMA">
  <nav>
    <a href="index.php">Übersicht</a>
    <a href="zeiten.php">Zeiten</a>
    <a href="sondertage.php">Sondertage</a>
    <a href="vorschau.php" class="active">Vorschau</a>
  </nav>
</header>

<h1>Vorschau der Schaltzeiten – <?= htmlspecialchars($monthName) ?></h1>

<!-- ============================================================
     MONATSNAVIGATION
     ============================================================ -->
<div class="month-nav">
  <?php if (!($currentMonth == intval(date('m')) && $currentYear == intval(date('Y')))): ?>
    <a href="?month=<?= $prevMonth ?>&year=<?= $prevYear ?>" class="button">&lt; Zurück</a>
  <?php else: ?>
    <span></span>
  <?php endif; ?>

  <?php if (!($currentMonth == intval(date('m', strtotime('+11 months'))) && $currentYear == intval(date('Y', strtotime('+11 months'))))): ?>
    <a href="?month=<?= $nextMonth ?>&year=<?= $nextYear ?>" class="button">Weiter &gt;</a>
  <?php endif; ?>
</div>

<!-- ============================================================
     TABELLENANSICHT
     ============================================================ -->
<div class="scroll-table">
<table>
  <tr>
    <th>Datum</th>
    <th>Wochentag</th>
    <th>Regel</th>
    <th>Status</th>
    <th>Öffnen</th>
    <th>Schließen</th>
    <th>Kommentar</th>
  </tr>

<?php
for ($d = 1; $d <= $daysInMonth; $d++) {
    $datum = sprintf('%04d-%02d-%02d', $currentYear, $currentMonth, $d);
    $wochentagIndex = date('N', strtotime($datum));

    $formatterDay = new IntlDateFormatter(
        'de_DE', IntlDateFormatter::NONE, IntlDateFormatter::NONE,
        'Europe/Berlin', IntlDateFormatter::GREGORIAN, 'EEE'
    );
    $wochentagName = $formatterDay->format(new DateTime($datum));

    $regel = '';
    $status = '';
    $von = $bis = $kommentar = '';
    $color = '';

    // === 1️⃣ Sondertag ===
    if (isset($sondertage_map[$datum])) {
        $regel = 'Sondertag';
        $status = $sondertage_map[$datum]['status'];
        $kommentar = $sondertage_map[$datum]['kommentar'];
        $color = 'color-orange';
    }
    // === 2️⃣ Feiertag ===
    elseif (in_array($datum, $feiertage)) {
        $regel = 'Feiertag';
        $status = 'geschlossen';
        $kommentar = array_search($datum, $feiertage);
        $color = 'color-red';
    }
    // === 3️⃣ Wochenende ===
    elseif ($wochentagIndex >= 6) {
        $regel = 'Wochenende';
        $status = 'geschlossen';
        $color = 'color-red';
    }
    // === 4️⃣ Werktag ===
    else {
        $modus = (date('I', strtotime($datum)) == 1) ? 'sommer' : 'winter';
        $regel = ucfirst($modus) . 'zeit';
        $status = 'offen';
        $von = $torzeiten[$modus]['startzeit'] ?? '';
        $bis = $torzeiten[$modus]['endzeit'] ?? '';
        $color = 'color-green';
    }

    echo "<tr class='$color'>";
    echo "<td>" . date('d.m.Y', strtotime($datum)) . "</td>";
    echo "<td>" . htmlspecialchars($wochentagName) . "</td>";
    echo "<td>" . htmlspecialchars($regel) . "</td>";
    echo "<td style='font-weight:bold'>" . ucfirst($status) . "</td>";
    echo "<td>" . ($von ?: '-') . "</td>";
    echo "<td>" . ($bis ?: '-') . "</td>";
    echo "<td>" . htmlspecialchars($kommentar ?: '-') . "</td>";
    echo "</tr>";
}
?>
</table>
</div>

<footer>
  <small>© <?= date('Y') ?> GIMA Rolltorsteuerung</small>
</footer>

</body>
</html>
