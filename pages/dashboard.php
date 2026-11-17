<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

// Lấy tháng và năm hiện tại
$current_month = (int)date('m');
$current_year  = (int)date('Y');
$month_label   = date('m/Y');

// 1. TÍNH TỔNG THU, TỔNG CHI VÀ SỐ DƯ TRONG THÁNG HIỆN TẠI
$stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN c.type = 'INCOME' THEN t.amount ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN c.type = 'EXPENSE' THEN t.amount ELSE 0 END), 0) AS total_out
    FROM transactions t
    JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = ? 
      AND MONTH(t.transaction_date) = ? 
      AND YEAR(t.transaction_date) = ?
");
$stmt->execute([$user_id, $current_month, $current_year]);
$stats = $stmt->fetch();

$total_in  = (float)($stats['total_in'] ?? 0);
$total_out = (float)($stats['total_out'] ?? 0);
$balance   = $total_in - $total_out;

// 2. KIỂM TRA CẢNH BÁO HẠN MỨC CHI TIÊU (NẾU CÓ THIẾT LẬP TRONG BẢNG budgets)
$warnings = [];
try {
    $b_stmt = $pdo->prepare("
        SELECT c.name AS cat_name, b.amount_limit,
               COALESCE(SUM(t.amount), 0) AS spent
        FROM budgets b
        JOIN categories c ON b.category_id = c.category_id
        LEFT JOIN transactions t 
               ON t.category_id = b.category_id 
              AND t.user_id = b.user_id
              AND MONTH(t.transaction_date) = b.month
              AND YEAR(t.transaction_date) = b.year
        WHERE b.user_id = ? AND b.month = ? AND b.year = ?
        GROUP BY b.budget_id, c.name, b.amount_limit
        HAVING spent > b.amount_limit
    ");
    $b_stmt->execute([$user_id, $current_month, $current_year]);
    $warnings = $b_stmt->fetchAll();
} catch (Exception $e) {
    $warnings = [];
}

// 3. LẤY 5 GIAO DỊCH GẦN ĐÂY NHẤT CỦA NGƯỜI DÙNG
$tx_stmt = $pdo->prepare("
    SELECT t.transaction_date, t.amount, t.note, c.name AS category_name, c.type
    FROM transactions t
    JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = ?
    ORDER BY t.transaction_date DESC, t.transaction_id DESC
    LIMIT 5
");
$tx_stmt->execute([$user_id]);
$recent_txs = $tx_stmt->fetchAll();

$pageTitle  = 'Tổng quan';
$activePage = 'dashboard';
require __DIR__ . '/../includes/auth.php';
require_login();          // chưa đăng nhập thì chuyển về trang login
require __DIR__ . '/../includes/header.php';
?>

<div class="top">
    <h1>Tổng quan tháng <?= htmlspecialchars($month_label) ?></h1>
    <a class="btn" href="transactions.php">+ Thêm giao dịch</a>
</div>

<?php foreach ($warnings as $w): ?>
    <div class="warn" role="status">
        <strong>Cảnh báo hạn mức:</strong> danh mục <?= htmlspecialchars($w['cat_name']) ?> đã chi 
        <?= number_format($w['spent'], 0, ',', '.') ?> / <?= number_format($w['amount_limit'], 0, ',', '.') ?> VND.
    </div>
<?php endforeach; ?>

<div class="stats">
    <div class="card">
        <div class="lbl">Tổng thu</div>
        <div class="big"><?= number_format($total_in, 0, ',', '.') ?></div>
    </div>
    <div class="card">
        <div class="lbl">Tổng chi</div>
        <div class="big"><?= number_format($total_out, 0, ',', '.') ?></div>
    </div>
    <div class="card">
        <div class="lbl">Số dư (thu - chi)</div>
        <div class="big"><?= number_format($balance, 0, ',', '.') ?></div>
    </div>
</div>

<div class="tablebox">
    <table class="tb">
        <thead>
            <tr>
                <th>Ngày</th>
                <th>Danh mục</th>
                <th class="r">Số tiền (VND)</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($recent_txs)): ?>
                <tr>
                    <td colspan="3" style="text-align:center;color:#64748b;">Chưa có giao dịch nào. Hãy bấm "+ Thêm giao dịch" để bắt đầu.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($recent_txs as $tx): ?>
                    <tr>
                        <td><?= date('d/m', strtotime($tx['transaction_date'])) ?></td>
                        <td><?= htmlspecialchars($tx['category_name']) ?></td>
                        <td class="r">
                            <?= $tx['type'] === 'INCOME' ? '+' : '-' ?><?= number_format($tx['amount'], 0, ',', '.') ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>