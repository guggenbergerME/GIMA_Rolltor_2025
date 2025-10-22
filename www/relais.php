<?php
require_once 'db.php';
session_start();
date_default_timezone_set('Europe/Berlin');

// === Konfiguration ===
$arduino_ip   = "10.140.1.10";
$arduino_port = 8888;
$arduino_pass = "1234";     // Passwort für UDP-Gerät
$web_pass     = "toradmin"; // 🔐 Passwort für Web-Zugang
$meldung      = "";

/* ============================================================
   Login / Logout
   ============================================================ */
if (isset($_POST['web_pass'])) {
    if ($_POST['web_pass'] === $web_pass) {
        $_SESSION['auth'] = true;
    } else {
        $meldung = "❌ Falsches Passwort!";
    }
}
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: relais.php");
    exit;
}
if (empty($_SESSION['auth'])):
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8"><title>Relaissteuerung – Login</title>
<link rel="stylesheet" href="style.css">
<style>
body{font-family:Arial,sans-serif;background:#f5f5f5;text-align:center;padding-top:100px}
.login-box{display:inline-block;background:#fff;padding:30px 40px;border-radius:10px;box-shadow:0 2px 6px rgba(0,0,0,.2)}
input[type=password]{font-size:1em;padding:8px;border-radius:6px;border:1px solid #ccc}
button{font-size:1em;padding:8px 16px;background:#4CAF50;color:#fff;border:none;border-radius:6px;cursor:pointer;margin-left:8px}
.message{color:red;margin-bottom:10px}
</style>
</head>
<body>
<div class="login-box">
  <h2>🔒 Relaissteuerung gesperrt</h2>
  <?php if($meldung): ?><p class="message"><?= htmlspecialchars($meldung) ?></p><?php endif; ?>
  <form method="post">
    <input type="password" name="web_pass" placeholder="Passwort" required>
    <button type="submit">Anmelden</button>
  </form>
</div>
</body></html>
<?php exit; endif;

/* ============================================================
   UDP senden
   ============================================================ */
function send_udp($ip,$port,$message){
    $sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if(!$sock) throw new Exception("Socket konnte nicht erstellt werden");
    socket_sendto($sock,$message,strlen($message),0,$ip,$port);
    socket_close($sock);
}

/* ============================================================
   Aktuelles Bitmuster laden
   ============================================================ */
$stmt = $pdo->prepare("SELECT desired_state, current_state FROM relais_status WHERE ip=:ip LIMIT 1");
$stmt->execute(['ip'=>$arduino_ip]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$desired = $row['desired_state'] ?? '0000';
$current = $row['current_state'] ?? $desired;

/* ============================================================
   Button-Handler
   ============================================================ */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['relais'],$_POST['action'])) {
    $relais = max(1, min(4, intval($_POST['relais']))); // 1–4
    $action = strtoupper($_POST['action'])==='ON' ? '1' : '0';

    // neues Bitmuster erzeugen
    $pos = $relais - 1;
    $desired[$pos] = $action;

    // in DB schreiben
    $stmt = $pdo->prepare("
        INSERT INTO relais_status (ip, desired_state, current_state, updated_at)
        VALUES (:ip, :desired, :current, NOW())
        ON DUPLICATE KEY UPDATE desired_state=:desired, updated_at=NOW()
    ");
    $stmt->execute(['ip'=>$arduino_ip,'desired'=>$desired,'current'=>$current]);

    // UDP senden
    $msg = "PASS=$arduino_pass;R{$relais}=" . ($action==='1'?'ON':'OFF');
    try{
        send_udp($arduino_ip,$arduino_port,$msg);
        $meldung = "✅ Befehl gesendet: $msg (" . date("H:i:s") . ")";
    }catch(Exception $e){
        $meldung = "❌ Fehler beim Senden: ".$e->getMessage();
    }
}

/* ============================================================
   Anzeige vorbereiten
   ============================================================ */
$stmt = $pdo->prepare("SELECT desired_state FROM relais_status WHERE ip=:ip LIMIT 1");
$stmt->execute(['ip'=>$arduino_ip]);
$bitmap = $stmt->fetchColumn() ?: $desired;

$relais = [];
for($i=0;$i<4;$i++){
    $state = isset($bitmap[$i]) && $bitmap[$i]==='1' ? 'ON' : 'OFF';
    $relais[] = ['nummer'=>$i+1,'status'=>$state];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<title>Relaissteuerung – Rolltor</title>
<link rel="stylesheet" href="style.css">
<style>
body{font-family:Arial,sans-serif;background:#f5f5f5;padding:20px}
.relais-container{display:flex;justify-content:space-around;flex-wrap:wrap;margin-top:40px;gap:20px}
.relais-card{background:#fff;border-radius:12px;box-shadow:0 2px 5px rgba(0,0,0,.1);padding:20px;text-align:center;width:180px}
.relais-card h2{margin-bottom:10px;color:#333}
.relais-state{font-weight:bold;margin-bottom:10px}
button{width:80px;height:40px;font-size:1em;border:none;border-radius:6px;margin:5px;cursor:pointer}
button.on{background:#4CAF50;color:#fff}
button.off{background:#f44336;color:#fff}
.message{background:#e0f7fa;border-left:4px solid #0097a7;padding:10px;margin-bottom:15px}
.logout{position:absolute;top:20px;right:30px;background:#444;color:#fff;padding:6px 10px;border-radius:5px;text-decoration:none}
.logout:hover{background:#000}
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
<h1>Manuelle Relaissteuerung (Arduino <?=htmlspecialchars($arduino_ip)?>)</h1>

<?php if($meldung): ?><p class="message"><?=htmlspecialchars($meldung)?></p><?php endif; ?>

<div class="relais-container">
<?php foreach($relais as $r): 
  $isOn = $r['status']==='ON';
?>
  <div class="relais-card">
    <h2>Relais <?= $r['nummer'] ?></h2>
    <div class="relais-state" style="color:<?= $isOn?'#4CAF50':'#f44336' ?>">
      <?= $isOn?'EIN':'AUS' ?>
    </div>
    <form method="post" style="display:inline;">
      <input type="hidden" name="relais" value="<?= $r['nummer'] ?>">
      <input type="hidden" name="action" value="ON">
      <button type="submit" class="on" <?= $isOn?'disabled':'' ?>>EIN</button>
    </form>
    <form method="post" style="display:inline;">
      <input type="hidden" name="relais" value="<?= $r['nummer'] ?>">
      <input type="hidden" name="action" value="OFF">
      <button type="submit" class="off" <?= !$isOn?'disabled':'' ?>>AUS</button>
    </form>
  </div>
<?php endforeach; ?>
</div>

<?php include __DIR__.'/footer.php'; ?>
</body></html>
