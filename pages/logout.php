<?php
// logout.php: đăng xuất = xóa sạch session rồi về trang đăng nhập
session_start();
$_SESSION = [];
session_destroy();
header('Location: login.php?logout=1');
exit;
