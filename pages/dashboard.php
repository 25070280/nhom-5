<?php
// dashboard.php: màn hình "Tổng quan" - số liệu THẬT lấy từ MySQL (FR05, FR06 phần cơ bản).
// Chọn tháng bằng địa chỉ: dashboard.php?m=2026-11 (không ghi thì lấy tháng hiện tại).
$pageTitle  = 'Tổng quan';
$activePage = 'dashboard';
require __DIR__ . '/../includes/auth.php';
require_login();
$uid = current_user_id();                 // chỉ đọc dữ liệu của người đang đăng nhập (BR03)

// ----- 1. Xác định tháng cần xem -----
$m = $_GET['m'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)) {
    $m = date('Y-m');
}
[$year, $month] = array_map('intval', explode('-', $m));
$from = sprintf('%04d-%02d-01', $year, $month);
$to   = date('Y-m-t', strtotime($from));          // ngày cuối tháng
$prev = date('Y-m', strtotime($from . ' -1 month'));
$next = date('Y-m', strtotime($from . ' +1 month'));

// ----- 2. Tổng thu, tổng chi, số dư trong tháng (BR02: số dư = thu - chi) -----
$st = $pdo->prepare(
    "SELECT
        COALESCE(SUM(CASE WHEN c.type = 'THU' THEN t.amount ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN c.type = 'CHI' THEN t.amount ELSE 0 END), 0) AS total_out
     FROM transactions t
     JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ? AND t.trans_date BETWEEN ? AND ?"
);
$st->execute([$uid, $from, $to]);
$sum = $st->fetch();
$totalIn  = (int)$sum['total_in'];
$totalOut = (int)$sum['total_out'];
$balance  = $totalIn - $totalOut;

// ----- 3. Hạn mức của tháng và số đã chi (để cảnh báo khi vượt) -----
$st = $pdo->prepare(
    "SELECT c.name, b.limit_amount, COALESCE(SUM(t.amount), 0) AS spent
     FROM budgets b
     JOIN categories c ON c.id = b.category_id
     LEFT JOIN transactions t
            ON t.category_id = b.category_id
           AND t.user_id = b.user_id
           AND t.trans_date BETWEEN ? AND ?
     WHERE b.user_id = ? AND b.year = ? AND b.month = ?
     GROUP BY b.id, c.name, b.limit_amount
     ORDER BY c.name"
);
$st->execute([$from, $to, $uid, $year, $month]);
$budgets = $st->fetchAll();
$over = array_filter($budgets, fn($b) => (int)$b['spent'] > (int)$b['limit_amount']);

// ----- 4. 5 giao dịch gần nhất trong tháng -----
$st = $pdo->prepare(
    "SELECT t.trans_date, c.name AS cat_name, c.type, t.amount, t.note
     FROM transactions t
     JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ? AND t.trans_date BETWEEN ? AND ?
     ORDER BY t.trans_date DESC, t.id DESC
     LIMIT 5"
);
$st->execute([$uid, $from, $to]);
$recent = $st->fetchAll();

// Định dạng số tiền kiểu Việt Nam: 1.234.000
function vnd($n) { return number_format((int)$n, 0, ',', '.'); }

require __DIR__ . '/../includes/header.php';
?>
<div class="top">
  <h1>Tổng quan tháng <?= sprintf('%02d/%04d', $month, $year) ?></h1>
  <div>
    <a class="btn ghost" href="?m=<?= e($prev) ?>">&lt; Tháng trước</a>
    <a class="btn ghost" href="?m=<?= e($next) ?>">Tháng sau &gt;</a>
    <a class="btn" href="transactions.php">+ Thêm giao dịch</a>
  </div>
</div>

<?php foreach ($over as $b): ?>
  <div class="warn" role="status">
    <strong>Cảnh báo hạn mức:</strong>
    danh mục <?= e($b['name']) ?> đã chi <?= vnd($b['spent']) ?> / <?= vnd($b['limit_amount']) ?> VND
    (vượt <?= vnd($b['spent'] - $b['limit_amount']) ?>).
  </div>
<?php endforeach; ?>

<div class="stats">
  <div class="card"><div class="lbl">Tổng thu</div><div class="big"><?= vnd($totalIn) ?></div></div>
  <div class="card"><div class="lbl">Tổng chi</div><div class="big"><?= vnd($totalOut) ?></div></div>
  <div class="card"><div class="lbl">Số dư (thu − chi)</div><div class="big"><?= ($balance < 0 ? '−' : '') . vnd(abs($balance)) ?></div></div>
</div>

<div class="tablebox">
  <table class="tb">
    <thead><tr><th>Ngày</th><th>Danh mục</th><th>Ghi chú</th><th class="r">Số tiền (VND)</th></tr></thead>
    <tbody>
    <?php if (!$recent): ?>
      <tr><td colspan="4" class="hint">Tháng này chưa có giao dịch.</td></tr>
    <?php endif; ?>
    <?php foreach ($recent as $r): ?>
      <tr>
        <td><?= e(date('d/m', strtotime($r['trans_date']))) ?></td>
        <td><?= e($r['cat_name']) ?></td>
        <td><?= e($r['note']) ?></td>
        <td class="r"><?= $r['type'] === 'THU' ? '+' : '−' ?><?= vnd($r['amount']) ?></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>