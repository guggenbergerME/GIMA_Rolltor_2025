<?php
require_once 'db.php';
date_default_timezone_set('Europe/Berlin');

/* -----------------------------
   Hilfsfunktionen
   ----------------------------- */
function feiertage_bayern($jahr){
    $ostern = easter_date($jahr);
    return [
        'Neujahr'=>"$jahr-01-01",'Heilige Drei Könige'=>"$jahr-01-06",'Tag der Arbeit'=>"$jahr-05-01",
        'Mariä Himmelfahrt'=>"$jahr-08-15",'Tag der Deutschen Einheit'=>"$jahr-10-03",'Allerheiligen'=>"$jahr-11-01",
        '1. Weihnachtstag'=>"$jahr-12-25",'2. Weihnachtstag'=>"$jahr-12-26",
        'Karfreitag'=>date('Y-m-d',$ostern-2*86400),'Ostermontag'=>date('Y-m-d',$ostern+1*86400),
        'Christi Himmelfahrt'=>date('Y-m-d',$ostern+39*86400),'Pfingstmontag'=>date('Y-m-d',$ostern+50*86400),
        'Fronleichnam'=>date('Y-m-d',$ostern+60*86400)
    ];
}

function intervals_for_date(string $date,$pdo):array{
    $tz = new DateTimeZone('Europe/Berlin');
    $r = [];
    $stmt = $pdo->prepare("SELECT status, kommentar FROM sondertage WHERE datum=?");
    $stmt->execute([$date]);
    if($s = $stmt->fetch(PDO::FETCH_ASSOC)){
        if($s['status'] === 'offen')
            $r[] = ['start'=>new DateTime("$date 00:00:00",$tz),'end'=>new DateTime("$date 23:59:59",$tz),'reason'=>'Sondertag: '.$s['kommentar']];
        return $r;
    }
    $feiertage = feiertage_bayern((int)substr($date,0,4));
    $wd = (int)date('N',strtotime($date));
    if(in_array($date,$feiertage) || $wd >= 6) return $r;
    $modus = ((new DateTime("$date 12:00:00",$tz))->format('I')==1)?'sommer':'winter';
    $stmt = $pdo->prepare("SELECT startzeit,endzeit FROM torzeiten WHERE modus=?");
    $stmt->execute([$modus]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if(!$row) return $r;
    $start = new DateTime("$date ".$row['startzeit'],$tz);
    $end   = new DateTime("$date ".$row['endzeit'],$tz);
    if($start < $end)
        $r[] = ['start'=>$start,'end'=>$end,'reason'=>ucfirst($modus).'zeit'];
    return $r;
}

function find_next_command($pdo,$daysAhead=365){
    $tz = new DateTimeZone('Europe/Berlin');
    $now = new DateTime('now',$tz);
    for($d=0;$d<=$daysAhead;$d++){
        $date = (clone$now)->modify("+{$d} day")->format('Y-m-d');
        foreach(intervals_for_date($date,$pdo) as $iv){
            $s = $iv['start']; $e = $iv['end'];
            if($e <= $now) continue;
            if($s > $now) return ['when'=>$s,'action'=>'öffnen','reason'=>$iv['reason']];
            if($s <= $now && $now < $e) return ['when'=>$e,'action'=>'schließen','reason'=>$iv['reason']];
        }
    }
    return null;
}

/* -----------------------------
   Daten laden
   ----------------------------- */

// Sommer-/Winterzeiten
$stmt = $pdo->query("SELECT * FROM torzeiten");
$zeiten = [];
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
    $zeiten[$row['modus']] = $row;
}

// Sondertage
$stmt = $pdo->query("SELECT * FROM sondertage ORDER BY datum ASC LIMIT 10");
$sondertage_preview = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Nächster geplanter Befehl
$next = find_next_command($pdo,365);

// Relais-Status aus DB (aktueller Zustand des Arduino)
$arduino_ip = "10.140.1.10"; // deine feste Arduino-IP
$stmt = $pdo->prepare("SELECT current_state, updated_at FROM relais_status WHERE ip = :ip LIMIT 1");
$stmt->execute(['ip' => $arduino_ip]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$bitmap = $row['current_state'] ?? '0000';
$updated = $row['updated_at'] ?? null;
$relais = [];

for($i=0; $i<4; $i++){
    $state = isset($bitmap[$i]) && $bitmap[$i]==='1' ? 'ON' : 'OFF';
    $relais[] = [
        'nummer' => $i+1,
        'status' => $state
    ];
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Rolltor Steuerung – Übersicht</title>
<link rel="stylesheet" href="style.css">
<style>
.relais-status{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-top:10px;}
.relais-box{border:1px solid #ccc;border-radius:10px;padding:8px;text-align:center;background:#fafafa;}
.relais-on{background:#c8f7c5;border-color:#4CAF50;}
.relais-off{background:#f7c5c5;border-color:#f44336;}
.status-box{padding:10px;border-radius:8px;margin-bottom:10px;}
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
    <a href="relais.php">Relais</a>
  </nav>
</header>

<main class="container">
<h1>Rolltor Steuerung – Übersicht</h1>

<!-- Relaisstatus -->
<section>
  <h2>Aktueller Relaisstatus (Arduino <?= htmlspecialchars($arduino_ip) ?>)</h2>
  <div class="relais-status">
    <?php foreach($relais as $r): 
      $class = $r['status']==='ON'?'relais-on':'relais-off'; ?>
      <div class="relais-box <?= $class ?>" id="relais<?= $r['nummer'] ?>">
        <strong>Relais <?= $r['nummer'] ?></strong><br>
        <span><?= $r['status'] ?></span><br>
      </div>
    <?php endforeach; ?>
  </div>
  <?php if($updated): ?>
    <p><small>Letztes Update: <?= htmlspecialchars(date('d.m.Y H:i:s', strtotime($updated))) ?></small></p>
  <?php endif; ?>
</section>

<!-- Nächster geplanter Befehl -->
<section>
  <h2>Nächster geplanter Befehl</h2>
  <?php if($next):
    $when=$next['when']; $action=$next['action']; $reason=$next['reason']; ?>
    <p><strong><?= ucfirst($action) ?></strong>
    am <b><?= htmlspecialchars($when->format('d.m.Y')) ?></b>
    um <b><?= htmlspecialchars($when->format('H:i:s')) ?></b>
    (Grund: <?= htmlspecialchars($reason) ?>)</p>
  <?php else: ?>
    <p><i>Kein geplanter Befehl in den nächsten 365 Tagen.</i></p>
  <?php endif; ?>
</section>

<!-- Zeiten & Sondertage -->
<section style="display:flex;gap:20px;margin-top:12px;">
  <div style="flex:1;">
    <h3>Sommer-/Winterzeiten</h3>
    <p><b>Sommerzeit:</b> <?= htmlspecialchars($zeiten['sommer']['startzeit']??'-') ?> – <?= htmlspecialchars($zeiten['sommer']['endzeit']??'-') ?></p>
    <p><b>Winterzeit:</b> <?= htmlspecialchars($zeiten['winter']['startzeit']??'-') ?> – <?= htmlspecialchars($zeiten['winter']['endzeit']??'-') ?></p>
  </div>
  <div style="flex:1;">
    <h3>Sondertage (Auszug)</h3>
    <?php if(count($sondertage_preview)>0): ?>
    <table><tr><th>Datum</th><th>Status</th><th>Kommentar</th></tr>
      <?php foreach($sondertage_preview as $s): ?>
      <tr><td><?= htmlspecialchars($s['datum']) ?></td><td><?= htmlspecialchars($s['status']) ?></td><td><?= htmlspecialchars($s['kommentar']) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <?php else: ?><p><i>Keine Sondertage definiert.</i></p><?php endif; ?>
  </div>
</section>
</main>

<?php include __DIR__.'/footer.php'; ?>
</body>
</html>
