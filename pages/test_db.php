<?php
require __DIR__ . '/../config/db.php';

$n = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
echo 'Kết nối thành công. Số người dùng trong CSDL: ' . $n;
