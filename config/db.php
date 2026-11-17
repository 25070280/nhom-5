<?php
$cfg  = require __DIR__ . '/config.local.php';
$port = $cfg['port'] ?? 3306;   // không ghi cổng thì dùng 3306

$pdo = new PDO(
    "mysql:host={$cfg['host']};port={$port};dbname={$cfg['dbname']};charset=utf8mb4",
    $cfg['user'],
    $cfg['pass'],
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);