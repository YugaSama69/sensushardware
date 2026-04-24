<?php

declare(strict_types=1);

$dbHost = '127.0.0.1';
$dbPort = '3306';
$dbName = 'sensus_hardware';
$dbUser = 'root';
$dbPass = '';

try {
    $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
    $pdo = new PDO($dsn, $dbUser, $dbPass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
} catch (PDOException $exception) {
    if (PHP_SAPI !== 'cli') {
        http_response_code(500);
        echo '<h1>Koneksi database gagal</h1>';
        echo '<p>Silakan import database terlebih dahulu dan sesuaikan file <code>config/database.php</code>.</p>';
    }
    exit;
}
