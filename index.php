<?php
// index.php: trang gốc. Đã đăng nhập thì vào Tổng quan, chưa thì về trang đăng nhập.
session_start();
header('Location: pages/' . (isset($_SESSION['user_id']) ? 'dashboard.php' : 'login.php'));
exit;
