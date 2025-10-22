<?php
require_once 'db.php';
date_default_timezone_set('Europe/Berlin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_times'])) {
    foreach (['sommer', 'winter'] as $modus) {
        $start = $_POST[$modus . '_start'];
        $ende  = $_POST[$modus . '_ende'];
        $stmt = $pdo->prepare("UPDATE torzeiten SET startzeit=?, endzeit=? WHERE modus=?");
        $stmt->execute([$start, $ende, $modus]);
    }
    $meldung = "Zeiten gespeichert!";
}

$stmt = $pdo->query("SELECT * FROM torzeiten");
$zeiten = [];
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $zeiten[$row['modus']] = $row;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Sommer- und Winterzeiten</title>
<link rel="stylesheet" href="style.css">
</head>
<body>

<header class="navbar">
  <img src="logo.png" class="nav-logo" alt="GIMA">
  <nav>
    <a href="index.php">Übersicht</a>
    <a href="zeiten.php" class="active">Zeiten</a>
    <a href="sondertage.php">Sondertage</a>
    <a href="vorschau.php">Vorschau</a>
  </nav>
</header>

<h1>Sommer- und Winterzeiten</h1>

<?php if (!empty($meldung)): ?>
  <p class="success"><?= htmlspecialchars($meldung) ?></p>
<?php endif; ?>

<form method="post">
  <input type="hidden" name="save_times" value="1">
  <div class="time-settings">
    <div class="time-box">
      <h3>Sommerzeit</h3>
      Öffnen um: <input type="time" name="sommer_start" value="<?= $zeiten['sommer']['startzeit'] ?>">  
      Schließen um: <input type="time" name="sommer_ende" value="<?= $zeiten['sommer']['endzeit'] ?>">
    </div>
    <div class="time-box">
      <h3>Winterzeit</h3>
      Öffnen um: <input type="time" name="winter_start" value="<?= $zeiten['winter']['startzeit'] ?>">  
      Schließen um: <input type="time" name="winter_ende" value="<?= $zeiten['winter']['endzeit'] ?>">
    </div>
  </div>
  <br>
  <button type="submit">Speichern</button>
</form>

<?php
// Footer per include (separate Datei)
include __DIR__ . '/footer.php';
?>

</body>
</html>
