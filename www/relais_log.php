<?php
require_once 'db.php';
date_default_timezone_set('Europe/Berlin');

/* -----------------------------
   Daten abrufen
   ----------------------------- */

// Letzte 200 Logeinträge (neueste zuerst)
$stmt = $pdo->query("
    SELECT id, ip, reported_state, desired_state, action_taken, created_at
    FROM relais_log
    ORDER BY id DESC
    LIMIT 200
");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Rolltor Steuerung – Relais-Log</title>
<link rel="stylesheet" href="style.css">
<style>
/* --- Dunkles Tabellen-Design --- */
body {
  background-color: #ffffffff;
  color: #e0e0e0;
}
table.log-table {
  width: 100%;
  border-collapse: collapse;
  margin-top: 15px;
  background-color: #111;
  border: 1px solid #333;
  border-radius: 6px;
  overflow: hidden;
}
table.log-table th, table.log-table td {
  padding: 8px 10px;
  border-bottom: 1px solid #333;
  text-align: left;
  color: #ddd;
}
table.log-table th {
  background: #222;
  color: #fff;
  font-weight: bold;
}
tr.log-on   { background-color: #154f28; }  /* Grünlich für ON */
tr.log-off  { background-color: #4f1515; }  /* Rötlich für OFF */
tr.log-info { background-color: #1b1b1b; }  /* Neutral */
tr:hover {
  background-color: #333;
}
h1 {
  color: #050505ff;
}
a, a:visited {
  color: #66b3ff;
}
a.active {
  color: #fff;
  font-weight: bold;
}
</style>
</head>
<body>

<?PHP 
include("include/header_nav.php");
?>

<main class="container">
<h1>Relais Log – Letzte 200 Einträge</h1>

<?php if(count($logs) > 0): ?>
  <table class="log-table">
    <tr>
      <th>ID</th>
      <th>Datum / Uhrzeit</th>
      <th>IP Modul</th>
      <th>Relais > Ist&nbsp;Status</th>
      <th>Relais > Soll&nbsp;Status</th>
      <th>Aktion</th>
    </tr>
    <?php foreach($logs as $row): 
      $act = strtolower($row['action_taken'] ?? '');
      $class = 'log-info';
      if(str_contains($act, 'on'))  $class = 'log-on';
      if(str_contains($act, 'off')) $class = 'log-off';
    ?>
      <tr class="<?= $class ?>">
        <td><?= htmlspecialchars($row['id']) ?></td>
        <td><?= htmlspecialchars(date('d.m.Y H:i:s', strtotime($row['created_at']))) ?></td>
        <td><?= htmlspecialchars($row['ip'] ?? '-') ?></td>
        <td><?= htmlspecialchars($row['reported_state'] ?? '-') ?></td>
        <td><?= htmlspecialchars($row['desired_state'] ?? '-') ?></td>
        <td><?= htmlspecialchars($row['action_taken'] ?? '-') ?></td>
      </tr>
    <?php endforeach; ?>
  </table>
<?php else: ?>
  <p><i>Keine Logeinträge vorhanden.</i></p>
<?php endif; ?>
</main>

<?php include __DIR__.'/footer.php'; ?>

<script>
// Seite alle 60 Sekunden neu laden
setTimeout(() => { location.reload(); }, 60000);
</script>

</body>
</html>
