<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Lấy user_id từ session nếu có, mặc định dùng user_id = 1 để chạy độc lập (BR03)
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

$message = '';
$error = '';
$budget_warning = '';

// 1. XỬ LÝ XÓA GIAO DỊCH (FR02 - BR03)
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE transaction_id = ? AND user_id = ?");
    $stmt->execute([$delete_id, $user_id]);
    if ($stmt->rowCount() > 0) {
        $message = "Đã xóa giao dịch thành công.";
    } else {
        $error = "Không thể xóa giao dịch hoặc giao dịch không tồn tại.";
    }
}

// 2. XỬ LÝ THÊM HOẶC CẬP NHẬT GIAO DỊCH (FR02 - BR01, BR03, BR04, BR05)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $transaction_id   = isset($_POST['transaction_id']) ? (int)$_POST['transaction_id'] : 0;
    $category_id      = (int)($_POST['category_id'] ?? 0);
    $amount           = (float)($_POST['amount'] ?? 0);
    $transaction_date = $_POST['transaction_date'] ?? '';
    $note             = trim($_POST['note'] ?? '');

    if ($amount <= 0) {
        $error = "Số tiền giao dịch phải lớn hơn 0 (BR01).";
    } elseif ($category_id <= 0 || empty($transaction_date)) {
        $error = "Vui lòng chọn đầy đủ danh mục và ngày giao dịch.";
    } else {
        $cat_stmt = $pdo->prepare("SELECT name, type FROM categories WHERE category_id = ? AND (user_id = ? OR user_id IS NULL)");
        $cat_stmt->execute([$category_id, $user_id]);
        $cat_info = $cat_stmt->fetch();

        if (!$cat_info) {
            $error = "Danh mục không hợp lệ.";
        } else {
            if ($transaction_id > 0) {
                $stmt = $pdo->prepare("UPDATE transactions SET category_id = ?, amount = ?, transaction_date = ?, note = ? WHERE transaction_id = ? AND user_id = ?");
                if ($stmt->execute([$category_id, $amount, $transaction_date, $note, $transaction_id, $user_id])) {
                    $message = "Cập nhật giao dịch thành công.";
                }
            } else {
                $stmt = $pdo->prepare("INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES (?, ?, ?, ?, ?)");
                if ($stmt->execute([$user_id, $category_id, $amount, $transaction_date, $note])) {
                    $message = "Thêm giao dịch mới thành công.";
                }
            }

            // Kiểm tra cảnh báo hạn mức ngân sách tháng nếu là khoản CHI (BR05)
            if ($cat_info['type'] === 'EXPENSE') {
                $month_str = date('Y-m', strtotime($transaction_date));
                $b_stmt = $pdo->prepare("SELECT limit_amount FROM budgets WHERE user_id = ? AND category_id = ? AND DATE_FORMAT(month_year, '%Y-%m') = ?");
                $b_stmt->execute([$user_id, $category_id, $month_str]);
                $budget = $b_stmt->fetch();

                if ($budget && $budget['limit_amount'] > 0) {
                    $s_stmt = $pdo->prepare("SELECT COALESCE(SUM(amount), 0) AS total_spent FROM transactions WHERE user_id = ? AND category_id = ? AND DATE_FORMAT(transaction_date, '%Y-%m') = ?");
                    $s_stmt->execute([$user_id, $category_id, $month_str]);
                    $spent = (float)$s_stmt->fetch()['total_spent'];
                    $limit = (float)$budget['limit_amount'];
                    $percent = round(($spent / $limit) * 100, 1);

                    if ($percent >= 100) {
                        $budget_warning = "Cảnh báo vượt hạn mức: Danh mục '{$cat_info['name']}' tháng {$month_str} đã chi " . number_format($spent) . "đ / " . number_format($limit) . "đ ({$percent}%).";
                    } elseif ($percent >= 80) {
                        $budget_warning = "Lưu ý hạn mức: Danh mục '{$cat_info['name']}' tháng {$month_str} đã đạt {$percent}% ngân sách (" . number_format($spent) . "đ / " . number_format($limit) . "đ).";
                    }
                }
            }
        }
    }
}

// 3. LẤY DỮ LIỆU SỬA NẾU CÓ ?edit_id=...
$edit_data = null;
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE transaction_id = ? AND user_id = ?");
    $stmt->execute([$edit_id, $user_id]);
    $edit_data = $stmt->fetch();
}

// 4. LẤY DANH SÁCH DANH MỤC CỦA NGƯỜI DÙNG
$c_stmt = $pdo->prepare("SELECT category_id, name, type FROM categories WHERE user_id = ? OR user_id IS NULL ORDER BY type ASC, name ASC");
$c_stmt->execute([$user_id]);
$cat_list = $c_stmt->fetchAll();

// 5. XỬ LÝ BỘ LỌC & TÌM KIẾM ĐA TIÊU CHÍ (FR04)
$from_date   = $_GET['from_date'] ?? '';
$to_date     = $_GET['to_date'] ?? '';
$filter_type = $_GET['type'] ?? '';
$filter_cat  = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$keyword     = trim($_GET['keyword'] ?? '');

$sql = "SELECT t.transaction_id, t.amount, t.transaction_date, t.note, c.name AS category_name, c.type AS category_type
        FROM transactions t
        JOIN categories c ON t.category_id = c.category_id
        WHERE t.user_id = ?";
$params = [$user_id];

if ($from_date !== '') {
    $sql .= " AND t.transaction_date >= ?";
    $params[] = $from_date;
}
if ($to_date !== '') {
    $sql .= " AND t.transaction_date <= ?";
    $params[] = $to_date;
}
if ($filter_type === 'INCOME' || $filter_type === 'EXPENSE') {
    $sql .= " AND c.type = ?";
    $params[] = $filter_type;
}
if ($filter_cat > 0) {
    $sql .= " AND t.category_id = ?";
    $params[] = $filter_cat;
}
if ($keyword !== '') {
    $sql .= " AND t.note LIKE ?";
    $params[] = "%" . $keyword . "%";
}

$sql .= " ORDER BY t.transaction_date DESC, t.transaction_id DESC";
$t_stmt = $pdo->prepare($sql);
$t_stmt->execute($params);
$transactions = $t_stmt->fetchAll();

$total_income  = 0;
$total_expense = 0;
foreach ($transactions as $row) {
    if ($row['category_type'] === 'INCOME') {
        $total_income += (float)$row['amount'];
    } else {
        $total_expense += (float)$row['amount'];
    }
}

// Gọi khung giao diện chung của nhóm
$pageTitle  = 'Quản lý giao dịch';
$activePage = 'transactions';
require __DIR__ . '/../includes/header.php';
?>

<div class="top">
    <h1>Quản lý giao dịch</h1>
</div>

<?php if ($message): ?>
    <div style="background:#dcfce7;color:#166534;padding:12px;border-radius:6px;"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:6px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>
<?php if ($budget_warning): ?>
    <div class="warn" role="status"><strong><?= htmlspecialchars($budget_warning) ?></strong></div>
<?php endif; ?>

<!-- Thống kê nhanh theo kết quả đang lọc -->
<div class="stats">
    <div class="card">
        <div class="lbl">Tổng thu (đang lọc)</div>
        <div class="big" style="color:#16a34a;">+<?= number_format($total_income) ?> đ</div>
    </div>
    <div class="card">
        <div class="lbl">Tổng chi (đang lọc)</div>
        <div class="big" style="color:#dc2626;">-<?= number_format($total_expense) ?> đ</div>
    </div>
    <div class="card">
        <div class="lbl">Chênh lệch</div>
        <div class="big"><?= number_format($total_income - $total_expense) ?> đ</div>
    </div>
</div>

<!-- Form Thêm / Sửa giao dịch (FR02) -->
<div style="background:#fff;padding:18px;border:1px solid #d1d5db;border-radius:8px;">
    <h2 style="margin-bottom:12px;"><?= $edit_data ? 'Cập nhật giao dịch' : 'Thêm giao dịch mới' ?></h2>
    <form method="POST" action="transactions.php" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;">
        <?php if ($edit_data): ?>
            <input type="hidden" name="transaction_id" value="<?= $edit_data['transaction_id'] ?>">
        <?php endif; ?>

        <div>
            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Danh mục *</label>
            <select name="category_id" required style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;">
                <option value="">-- Chọn danh mục --</option>
                <?php foreach ($cat_list as $cat): ?>
                    <option value="<?= $cat['category_id'] ?>" <?= ($edit_data && $edit_data['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                        [<?= $cat['type'] === 'INCOME' ? 'Thu' : 'Chi' ?>] <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div>
            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Số tiền (VNĐ) *</label>
            <input type="number" name="amount" min="1" step="any" required value="<?= $edit_data ? $edit_data['amount'] : '' ?>" placeholder="VD: 50000" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;">
        </div>

        <div>
            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Ngày giao dịch *</label>
            <input type="date" name="transaction_date" required value="<?= $edit_data ? $edit_data['transaction_date'] : date('Y-m-d') ?>" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;">
        </div>

        <div>
            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Ghi chú</label>
            <input type="text" name="note" value="<?= $edit_data ? htmlspecialchars($edit_data['note']) : '' ?>" placeholder="Nội dung chi tiêu..." style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;">
        </div>

        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn"><?= $edit_data ? 'Lưu cập nhật' : '+ Thêm giao dịch' ?></button>
            <?php if ($edit_data): ?>
                <a href="transactions.php" style="padding:8px 12px;border:1px solid #cbd5e1;border-radius:6px;text-decoration:none;color:#333;">Hủy</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Thanh Lọc & Tìm kiếm giao dịch (FR04) -->
<div style="background:#f9fafb;padding:16px;border:1px solid #d1d5db;border-radius:8px;">
    <form method="GET" action="transactions.php" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;align-items:end;">
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Từ ngày</label>
            <input type="date" name="from_date" value="<?= htmlspecialchars($from_date) ?>" style="width:100%;padding:7px;border:1px solid #cbd5e1;border-radius:6px;">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Đến ngày</label>
            <input type="date" name="to_date" value="<?= htmlspecialchars($to_date) ?>" style="width:100%;padding:7px;border:1px solid #cbd5e1;border-radius:6px;">
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Loại</label>
            <select name="type" style="width:100%;padding:7px;border:1px solid #cbd5e1;border-radius:6px;">
                <option value="">Tất cả</option>
                <option value="INCOME" <?= $filter_type === 'INCOME' ? 'selected' : '' ?>>Khoản Thu</option>
                <option value="EXPENSE" <?= $filter_type === 'EXPENSE' ? 'selected' : '' ?>>Khoản Chi</option>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Danh mục</label>
            <select name="category_id" style="width:100%;padding:7px;border:1px solid #cbd5e1;border-radius:6px;">
                <option value="0">Tất cả danh mục</option>
                <?php foreach ($cat_list as $cat): ?>
                    <option value="<?= $cat['category_id'] ?>" <?= $filter_cat === (int)$cat['category_id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($cat['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px;">Tìm ghi chú</label>
            <input type="text" name="keyword" value="<?= htmlspecialchars($keyword) ?>" placeholder="Nhập từ khóa..." style="width:100%;padding:7px;border:1px solid #cbd5e1;border-radius:6px;">
        </div>
        <div style="display:flex;gap:6px;">
            <button type="submit" class="btn">Lọc</button>
            <a href="transactions.php" style="padding:7px 12px;border:1px solid #cbd5e1;border-radius:6px;text-decoration:none;color:#333;background:#fff;">Đặt lại</a>
        </div>
    </form>
</div>

<!-- Bảng danh sách giao dịch -->
<div class="tablebox">
    <table class="tb">
        <thead>
            <tr>
                <th>Ngày</th>
                <th>Loại</th>
                <th>Danh mục</th>
                <th>Ghi chú</th>
                <th class="r">Số tiền</th>
                <th style="text-align:center;">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($transactions)): ?>
                <tr>
                    <td colspan="6" style="text-align:center;padding:20px;color:#6b7280;">Không tìm thấy giao dịch nào.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($transactions as $t): ?>
                    <tr>
                        <td><?= date('d/m/Y', strtotime($t['transaction_date'])) ?></td>
                        <td>
                            <span style="padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600;background:<?= $t['category_type'] === 'INCOME' ? '#dcfce7;color:#166534' : '#fee2e2;color:#991b1b' ?>;">
                                <?= $t['category_type'] === 'INCOME' ? 'Thu' : 'Chi' ?>
                            </span>
                        </td>
                        <td><?= htmlspecialchars($t['category_name']) ?></td>
                        <td><?= htmlspecialchars($t['note']) ?></td>
                        <td class="r" style="font-weight:600;color:<?= $t['category_type'] === 'INCOME' ? '#16a34a' : '#dc2626' ?>;">
                            <?= $t['category_type'] === 'INCOME' ? '+' : '-' ?><?= number_format($t['amount']) ?> đ
                        </td>
                        <td style="text-align:center;">
                            <a href="transactions.php?edit_id=<?= $t['transaction_id'] ?>">Sửa</a> |
                            <a href="transactions.php?delete_id=<?= $t['transaction_id'] ?>" style="color:#dc2626;" onclick="return confirm('Xác nhận xóa giao dịch này?');">Xóa</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>