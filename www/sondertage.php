<?php
require_once 'db.php';
date_default_timezone_set('Europe/Berlin');

$meldung = "";

// --- Sondertag speichern ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'save' &&
    !empty($_POST['datum']) &&
    !empty($_POST['status'])
) {
    $datum = $_POST['datum'];
    $status = $_POST['status'];
    $kommentar = $_POST['kommentar'] ?? '';

    try {
        $stmt = $pdo->prepare("REPLACE INTO sondertage (datum, status, kommentar) VALUES (?, ?, ?)");
        $stmt->execute([$datum, $status, $kommentar]);
        $meldung = "✅ Sondertag gespeichert: $datum ($status)";
    } catch (PDOException $e) {
        $meldung = "❌ Fehler beim Speichern: " . $e->getMessage();
    }
}

// --- Sondertag löschen ---
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['action']) &&
    $_POST['action'] === 'delete' &&
    !empty($_POST['datum'])
) {
    $datum = $_POST['datum'];
    try {
        $stmt = $pdo->prepare("DELETE FROM sondertage WHERE datum = ?");
        $stmt->execute([$datum]);
        $meldung = "🗑️ Sondertag gelöscht: $datum";
    } catch (PDOException $e) {
        $meldung = "❌ Fehler beim Löschen: " . $e->getMessage();
    }
}

// --- Alle Sondertage abrufen ---
$stmt = $pdo->query("SELECT * FROM sondertage ORDER BY datum ASC");
$tage = $stmt->fetchAll(PDO::FETCH_ASSOC);

// --- Datumsausgabeformat vorbereiten ---
$formatter = new IntlDateFormatter(
    'de_DE',
    IntlDateFormatter::FULL,
    IntlDateFormatter::NONE,
    'Europe/Berlin',
    IntlDateFormatter::GREGORIAN,
    'EEEE, dd. MMMM yyyy'
);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Sondertage – Rolltor Steuerung</title>
<link rel="stylesheet" href="style.css">
<style>
body { font-family: Arial, sans-serif; padding: 20px; background: #f8f8f8; }
h1 { color: #333; }
.success { color: green; }
.error { color: red; }
table { width: 100%; border-collapse: collapse; margin-top: 10px; background: #fff; }
th, td { border: 1px solid #ccc; padding: 6px; text-align: left; }
button { padding: 4px 8px; border: none; border-radius: 4px; cursor: pointer; }
button.delete { background: #f44336; color: white; }
button.save { background: #4CAF50; color: white; margin-top: 8px; }
form.inline { display: inline; margin: 0; padding: 0; }
</style>
</head>
<body>

<header class="navbar">
  <img src="logo.png" class="nav-logo" alt="GIMA">
  <nav>
    <a href="index.php" class="active">Übersicht</a>
    <a href="zeiten.php">Zeiten</a>
    <a href="sondertage.php">Sondertage</a>
    <a href="vorschau.php">Vorschau</a>
  </nav>
</header>

<h1>Sondertage verwalten</h1>

<?php if (!empty($meldung)): ?>
  <p class="success"><?= htmlspecialchars($meldung) ?></p>
<?php endif; ?>

<!-- Formular: Neuer Sondertag -->
<form method="post">
  <input type="hidden" name="action" value="save">
  <label>Datum:</label>
  <input type="date" name="datum" required>
  <label>Status:</label>
  <select name="status" required>
    <option value="geschlossen">Geschlossen</option>
    <option value="offen">Offen</option>
  </select>
  <label>Kommentar:</label>
  <input type="text" name="kommentar" placeholder="z. B. Brückentag">
  <button type="submit" class="save">Speichern</button>
</form>

<h2>Vorhandene Sondertage</h2>

<?php if (count($tage) > 0): ?>
<table>
  <tr><th>Datum</th><th>Status</th><th>Kommentar</th><th>Aktion</th></tr>
  <?php foreach ($tage as $tag): ?>
  <?php
    // Datum schön formatieren
    $datumObj = new DateTime($tag['datum']);
    $datumDeutsch = $formatter->format($datumObj);
  ?>
  <tr>
    <td><?= htmlspecialchars($datumDeutsch) ?></td>
    <td><?= htmlspecialchars($tag['status']) ?></td>
    <td><?= htmlspecialchars($tag['kommentar']) ?></td>
    <td>
      <form method="post" class="inline"
        onsubmit="return confirm('Sondertag <?= htmlspecialchars($datumDeutsch) ?> wirklich löschen?');">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="datum" value="<?= htmlspecialchars($tag['datum']) ?>">
        <button type="submit" class="delete">🗑️ Löschen</button>
      </form>
    </td>
  </tr>
  <?php endforeach; ?>
</table>
<?php else: ?>
<p><i>Keine Sondertage eingetragen.</i></p>
<?php endif; ?>

<a href="index.php" class="button">Zurück</a>

<?php include __DIR__ . '/footer.php'; ?>

</body>
</html>
