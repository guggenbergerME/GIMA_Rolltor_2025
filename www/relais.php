<?php
require_once 'db.php';
session_start();
date_default_timezone_set('Europe/Berlin');

// === Konfiguration ===
$arduino_ip   = "10.140.1.10";
$arduino_port = 8888;
$arduino_pass = "1234"; // Passwort für UDP-Gerät
$web_pass     = "toradmin"; // 🔐 Passwort für Web-Zugang

$meldung = "";

// === Login prüfen ===
if (isset($_POST['web_pass'])) {
    if ($_POST['web_pass'] === $web_pass) {
        $_SESSION['auth'] = true;
    } else {
        $meldung = "❌ Falsches Passwort!";
    }
}

// === Logout ===
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: relais.php");
    exit;
}

// === Wenn nicht eingeloggt: Login anzeigen ===
if (empty($_SESSION['auth'])):
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Relaissteuerung – Login</title>
<link rel="stylesheet" href="style.css">
<style>
body { font-family: Arial, sans-serif; background: #f5f5f5; text-align: center; padding-top: 100px; }
.login-box {
  display: inline-block; background: white; padding: 30px 40px;
  border-radius: 10px; box-shadow: 0 2px 6px rgba(0,0,0,0.2);
}
input[type=password] {
  font-size: 1em; padding: 8px; border-radius: 6px; border: 1px solid #ccc;
}
button {
  font-size: 1em; padding: 8px 16px; background: #4CAF50; color: white;
  border: none; border-radius: 6px; cursor: pointer; margin-left: 8px;
}
.message { color: red; margin-bottom: 10px; }
</style>
</head>
<body>
<div class="login-box">
  <h2>🔒 Relaissteuerung gesperrt</h2>
  <?php if ($meldung): ?><p class="message"><?= htmlspecialchars($meldung) ?></p><?php endif; ?>
  <form method="post">
    <input type="password" name="web_pass" placeholder="Passwort" required>
    <button type="submit">Anmelden</button>
  </form>
</div>
</body>
</html>
<?php
exit;
endif;

// === UDP-Sende-Funktion ===
function send_udp($ip, $port, $message) {
    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if (!$sock) {
        throw new Exception("Socket konnte nicht erstellt werden");
    }
    socket_sendto($sock, $message, strlen($message), 0, $ip, $port);
    socket_close($sock);
}

// === Button-Handler ===
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['relais']) && isset($_POST['action'])) {
    $relais = intval($_POST['relais']); // 1..4
    $action = strtoupper($_POST['action']); // ON oder OFF
    $msg = "PASS=$arduino_pass;R{$relais}=$action";

    try {
        send_udp($arduino_ip, $arduino_port, $msg);
        $meldung = "✅ Befehl gesendet: $msg (" . date("H:i:s") . ")";
    } catch (Exception $e) {
        $meldung = "❌ Fehler beim Senden: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Relaissteuerung – Rolltor</title>
<link rel="stylesheet" href="style.css">
<style>
body {
  font-family: Arial, sans-serif;
  background: #f5f5f5;
  padding: 20px;
}
.relais-container {
  display: flex;
  justify-content: space-around;
  margin-top: 40px;
}
.relais-card {
  background: white;
  border-radius: 12px;
  box-shadow: 0 2px 5px rgba(0,0,0,0.1);
  padding: 20px;
  text-align: center;
  width: 180px;
}
.relais-card h2 {
  margin-bottom: 15px;
  color: #333;
}
button {
  width: 80px;
  height: 40px;
  font-size: 1em;
  border: none;
  border-radius: 6px;
  margin: 5px;
  cursor: pointer;
}
button.on {
  background: #4CAF50;
  color: white;
}
button.off {
  background: #f44336;
  color: white;
}
.message {
  background: #e0f7fa;
  border-left: 4px solid #0097a7;
  padding: 10px;
  margin-bottom: 15px;
}
.logout {
  position: absolute;
  top: 20px;
  right: 30px;
  background: #444;
  color: white;
  padding: 6px 10px;
  border-radius: 5px;
  text-decoration: none;
}
.logout:hover { background: #000; }
</style>
</head>
<body>

<header class="navbar">
  <img src="logo.png" class="nav-logo" alt="GIMA">
  <nav>
    <a href="index.php">Übersicht</a>
    <a href="zeiten.php">Zeiten</a>
    <a href="sondertage.php">Sondertage</a>
    <a href="vorschau.php">Vorschau</a>
    <a href="relais.php" class="active">Relais</a>
  </nav>
</header>

<a href="?logout=1" class="logout">🚪 Logout</a>

<h1>Manuelle Relaissteuerung</h1>

<?php if ($meldung): ?>
  <p class="message"><?= htmlspecialchars($meldung) ?></p>
<?php endif; ?>

<div class="relais-container">
  <?php for ($i = 1; $i <= 4; $i++): ?>
    <div class="relais-card">
      <h2>Relais <?= $i ?></h2>
      <form method="post" style="display:inline;">
        <input type="hidden" name="relais" value="<?= $i ?>">
        <input type="hidden" name="action" value="on">
        <button type="submit" class="on">EIN</button>
      </form>
      <form method="post" style="display:inline;">
        <input type="hidden" name="relais" value="<?= $i ?>">
        <input type="hidden" name="action" value="off">
        <button type="submit" class="off">AUS</button>
      </form>
    </div>
  <?php endfor; ?>
</div>

<?php include __DIR__ . '/footer.php'; ?>

</body>
</html>
