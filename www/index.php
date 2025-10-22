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

// Aktueller Relaisstatus aus DB (aktueller Stand vom Arduino)
$stmt = $pdo->query("SELECT device_ip, r1, r2, r3, r4, updated_at FROM relais_status ORDER BY id DESC LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);

$relais = [];
if ($row) {
    $relais = [
        ['relais_nummer' => 1, 'status' => $row['r1'] ? 'ON' : 'OFF', 'timestamp' => $row['updated_at']],
        ['relais_nummer' => 2, 'status' => $row['r2'] ? 'ON' : 'OFF', 'timestamp' => $row['updated_at']],
        ['relais_nummer' => 3, 'status' => $row['r3'] ? 'ON' : 'OFF', 'timestamp' => $row['updated_at']],
        ['relais_nummer' => 4, 'status' => $row['r4'] ? 'ON' : 'OFF', 'timestamp' => $row['updated_at']],
    ];
}


// Bestimme Gesamtstatus (z. B. Tor offen, wenn Relais 1 = ON)
$gesamtStatus = 'unbekannt';
if(count($relais) > 0){
    $onCount = array_sum(array_map(fn($r)=>$r['status']==='ON'?1:0, $relais));
    $gesamtStatus = ($onCount > 0) ? 'offen' : 'geschlossen';
}

// Nächster geplanter Befehl
$next = find_next_command($pdo,365);
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
.relais-unknown{background:#eee;border-color:#aaa;}
.status-box{padding:10px;border-radius:8px;margin-bottom:10px;}
.status-offen{background:#c8f7c5;border:2px solid #4CAF50;}
.status-geschlossen{background:#f7c5c5;border:2px solid #f44336;}
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

<!-- Aktueller Gesamtstatus -->
<div class="status-box <?= ($gesamtStatus==='offen')?'status-offen':'status-geschlossen' ?>">
  <b>Aktueller Status:</b> <?= strtoupper(htmlspecialchars($gesamtStatus)) ?><br>
  <small><?= (new IntlDateFormatter('de_DE',IntlDateFormatter::FULL,IntlDateFormatter::SHORT,'Europe/Berlin',IntlDateFormatter::GREGORIAN,'EEEE, dd. MMMM yyyy'))->format(new DateTime()) ?></small>
</div>

<!-- Relaisstatus -->
<section>
  <h2>Aktuelle Relais-Zustände (Arduino)</h2>
  <div id="relaisContainer" class="relais-status">
    <?php foreach($relais as $r):
      $cls = ($r['status']==='ON') ? 'relais-on' : (($r['status']==='OFF') ? 'relais-off' : 'relais-unknown'); ?>
      <div class="relais-box <?= $cls ?>" id="relais<?= htmlspecialchars($r['relais_nummer']) ?>">
        <h3>Relais <?= htmlspecialchars($r['relais_nummer']) ?></h3>
        <p><strong><?= htmlspecialchars($r['status']) ?></strong></p>
        <small><?= date('d.m.Y H:i',strtotime($r['timestamp'])) ?></small>
      </div>
    <?php endforeach; ?>
  </div>
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

<script>
// === Relaisstatus automatisch aktualisieren ===
async function updateRelaisStatus(){
  try {
    const res = await fetch('api/relais_status.php?_t=' + Date.now());
    if(!res.ok) return;
    const data = await res.json();
    data.forEach(r=>{
      const el = document.getElementById('relais'+r.relais_nummer);
      if(!el) return;
      el.querySelector('strong').textContent = r.status;
      el.querySelector('small').textContent = new Date(r.timestamp).toLocaleString('de-DE');
      el.className = 'relais-box ' + (r.status==='ON'?'relais-on':(r.status==='OFF'?'relais-off':'relais-unknown'));
    });
  } catch(e){ console.error(e); }
}
setInterval(updateRelaisStatus, 30000);
</script>

</body>
</html>
