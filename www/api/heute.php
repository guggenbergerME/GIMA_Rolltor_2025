<?php
require_once '../db.php';

$heute = date('Y-m-d');
$stmt = $pdo->prepare("SELECT * FROM steuerzeiten WHERE datum = ? ORDER BY startzeit");
$stmt->execute([$heute]);
$daten = $stmt->fetchAll(PDO::FETCH_ASSOC);

header('Content-Type: application/json; charset=utf-8');
echo json_encode($daten);
