<?php
// auth.php: dùng chung cho mọi trang. Làm 3 việc:
//   1. bật session (để "nhớ" người đang đăng nhập)
//   2. kết nối MySQL (biến $pdo)
//   3. cung cấp vài hàm tiện ích
session_start();
require __DIR__ . '/../config/db.php';

// e(): in chữ ra HTML một cách an toàn (chống chèn mã độc XSS)
function e($text) {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// Trả về id người đang đăng nhập, hoặc null nếu chưa đăng nhập
function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}

// Gọi ở đầu mỗi trang cần đăng nhập: chưa đăng nhập thì đưa về trang login
function require_login() {
    if (!current_user_id()) {
        header('Location: login.php');
        exit;
    }
}
