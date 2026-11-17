<?php
// dashboard.php: màn hình 7 "Tổng quan" trong phác thảo.
// HIỆN TẠI là trang mẫu với số liệu cố định; sẽ thay bằng dữ liệu thật ở FR05, FR06.
$pageTitle  = 'Tổng quan';
$activePage = 'dashboard';
require __DIR__ . '/../includes/auth.php';
require_login();          // chưa đăng nhập thì chuyển về trang login
require __DIR__ . '/../includes/header.php';
?>
<div class="top">
  <h1>Tổng quan tháng 11/2026</h1>
  <a class="btn" href="transactions.php">+ Thêm giao dịch</a>
</div>

<div class="warn" role="status">
  <strong>Cảnh báo hạn mức:</strong> danh mục Ăn uống đã chi 870.000 / 800.000 VND.
</div>

<div class="stats">
  <div class="card"><div class="lbl">Tổng thu</div><div class="big">9.000.000</div></div>
  <div class="card"><div class="lbl">Tổng chi</div><div class="big">1.110.000</div></div>
  <div class="card"><div class="lbl">Số dư (thu − chi)</div><div class="big">7.890.000</div></div>
</div>

<div class="tablebox">
  <table class="tb">
    <thead><tr><th>Ngày</th><th>Danh mục</th><th class="r">Số tiền (VND)</th></tr></thead>
    <tbody>
      <tr><td>12/11</td><td>Giải trí</td><td class="r">−150.000</td></tr>
      <tr><td>10/11</td><td>Ăn uống</td><td class="r">−395.000</td></tr>
      <tr><td>08/11</td><td>Di chuyển</td><td class="r">−90.000</td></tr>
    </tbody>
  </table>
</div>

<div class="hint">Dữ liệu minh họa, chưa lấy từ MySQL.</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
