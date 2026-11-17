<?php
// reports.php: trang tạm. Sẽ làm ở chức năng FR06.
$pageTitle  = 'Thống kê';
$activePage = 'reports';
require __DIR__ . '/../includes/auth.php';
require_login();          // chưa đăng nhập thì chuyển về trang login
require __DIR__ . '/../includes/header.php';
?>
<div class="top"><h1>Thống kê</h1></div>
<div class="card">Trang này sẽ được làm ở chức năng FR06.</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
