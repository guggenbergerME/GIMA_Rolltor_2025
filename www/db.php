<?php
$dsn = 'mysql:host=172.18.0.2;dbname=rolltor;charset=utf8mb4';
$user = 'rolltor_user';
$pass = 'rolltor_pass';

try {
    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB-Fehler: " . $e->getMessage());
}
?>
