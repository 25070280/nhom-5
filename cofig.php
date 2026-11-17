// cofig.local
<?php
return [
    'host'   => '127.0.0.1',
    'dbname' => 'expense_manager',
    'user'   => 'root',
    'pass'   => '',
];

// cofig.sample
<?php
return [
    'host'   => '127.0.0.1',
    'dbname' => 'expense_manager',
    'user'   => 'root',
    'pass'   => '',   
];

// db
<?php
$cfg  = require __DIR__ . '/config.local.php';
$port = $cfg['port'] ?? 3306;  

$pdo = new PDO(
    "mysql:host={$cfg['host']};port={$port};dbname={$cfg['dbname']};charset=utf8mb4",
    $cfg['user'],
    $cfg['pass'],
    [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
);
