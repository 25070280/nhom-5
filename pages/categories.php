<?php
// categories.php: trang tạm. Sẽ làm ở chức năng FR03.
$pageTitle  = 'Danh mục';
$activePage = 'categories';
require __DIR__ . '/../includes/auth.php';
require_login();          // chưa đăng nhập thì chuyển về trang login
require __DIR__ . '/../includes/header.php';
?>
<div class="top"><h1>Danh mục</h1></div>
<div class="card">Trang này sẽ được làm ở chức năng FR03.</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
