<?php
require_once 'db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $datum = $_POST['datum'];
    $start = $_POST['startzeit'];
    $ende = $_POST['endzeit'];
    $aktion = $_POST['aktion'];
    $kommentar = $_POST['kommentar'];

    $stmt = $pdo->prepare("INSERT INTO steuerzeiten (datum, startzeit, endzeit, aktion, kommentar)
                           VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$datum, $start, $ende, $aktion, $kommentar]);
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Neuer Eintrag – Rolltor Steuerung</title>
<link rel="stylesheet" href="style.css">
</head>
<body>
<h1>Neuer Steuer-Eintrag</h1>

<form method="post">
  <label>Datum:</label>
  <input type="date" name="datum" required><br>

  <label>Startzeit:</label>
  <input type="time" name="startzeit" required><br>

  <label>Endzeit:</label>
  <input type="time" name="endzeit" required><br>

  <label>Aktion:</label>
  <select name="aktion">
    <option value="öffnen">Öffnen</option>
    <option value="schließen">Schließen</option>
  </select><br>

  <label>Kommentar:</label>
  <input type="text" name="kommentar"><br>

  <button type="submit">Speichern</button>
</form>

<a href="index.php">Zurück</a>
</body>
</html>
