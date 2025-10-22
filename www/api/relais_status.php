<?php
require_once '../db.php';
header('Content-Type: application/json; charset=utf-8');

$stmt = $pdo->query("SELECT relais_nummer, status, timestamp FROM relais_status ORDER BY relais_nummer ASC");
$relais = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode($relais);
