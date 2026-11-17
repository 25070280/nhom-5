<?php
session_start();
require_once '../config/db.php';

// Kiểm tra đăng nhập (Bật lại khi ghép với FR01 của bạn)
// if (!isset($_SESSION['user_id'])) { header("Location: login.php"); exit; }
$user_id = $_SESSION['user_id'] ?? 1;

$success_msg = '';
$warning_msg = '';
$errors = [];

$form_data = [
    'transaction_id' => '',
    'category_id' => '',
    'amount' => '',
    'transaction_date' => date('Y-m-d'),
    'note' => ''
];

// 1. XÓA GIAO DỊCH (FR02 - Có xác nhận & kiểm tra quyền BR03)
if (isset($_GET['delete_id'])) {
    $delete_id = (int)$_GET['delete_id'];
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE transaction_id = ? AND user_id = ?");
    if ($stmt->execute([$delete_id, $user_id]) && $stmt->rowCount() > 0) {
        header("Location: transactions.php?msg=deleted");
        exit;
    } else {
        $errors['general'] = "Không thể xóa giao dịch này.";
    }
}

// 2. THÊM / SỬA GIAO DỊCH (FR02 & UC04)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'add';
    $trans_id = (int)($_POST['transaction_id'] ?? 0);
    $category_id = (int)($_POST['category_id'] ?? 0);
    $amount_raw = trim($_POST['amount'] ?? '');
    $transaction_date = trim($_POST['transaction_date'] ?? '');
    $note = trim($_POST['note'] ?? '');

    $form_data = [
        'transaction_id' => $trans_id,
        'category_id' => $category_id,
        'amount' => $amount_raw,
        'transaction_date' => $transaction_date,
        'note' => $note
    ];

    // Kiểm tra danh mục thuộc quyền sở hữu người dùng (BR03 & Mục 3.1.3)
    if ($category_id <= 0) {
        $errors['category_id'] = "Vui lòng chọn danh mục.";
    } else {
        $stmt_cat_check = $pdo->prepare("SELECT category_id, name, type FROM categories WHERE category_id = ? AND (user_id = ? OR is_default = 1)");
        $stmt_cat_check->execute([$category_id, $user_id]);
        $valid_category = $stmt_cat_check->fetch();
        if (!$valid_category) {
            $errors['category_id'] = "Danh mục không hợp lệ.";
        }
    }

    // Kiểm tra số tiền > 0 (BR01)
    if ($amount_raw === '' || !is_numeric($amount_raw) || (int)$amount_raw <= 0) {
        $errors['amount'] = "Số tiền phải lớn hơn 0.";
    }

    // Kiểm tra ngày hợp lệ
    if (empty($transaction_date)) {
        $errors['transaction_date'] = "Vui lòng chọn ngày giao dịch.";
    } else {
        $d = DateTime::createFromFormat('Y-m-d', $transaction_date);
        if (!$d || $d->format('Y-m-d') !== $transaction_date) {
            $errors['transaction_date'] = "Ngày giao dịch không hợp lệ.";
        }
    }

    if (empty($errors)) {
        $amount = (int)$amount_raw;

        if ($action === 'add') {
            $stmt = $pdo->prepare("INSERT INTO transactions (user_id, category_id, amount, transaction_date, note) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $category_id, $amount, $transaction_date, $note]);
            $msg_type = 'added';
        } else {
            $stmt = $pdo->prepare("UPDATE transactions SET category_id = ?, amount = ?, transaction_date = ?, note = ? WHERE transaction_id = ? AND user_id = ?");
            $stmt->execute([$category_id, $amount, $transaction_date, $note, $trans_id, $user_id]);
            $msg_type = 'updated';
        }

        // UC04 Bước 5: Kiểm tra cảnh báo vượt hạn mức (nếu là khoản CHI)
        $warn_param = '';
        if ($valid_category['type'] === 'EXPENSE') {
            $m = (int)date('m', strtotime($transaction_date));
            $y = (int)date('Y', strtotime($transaction_date));
            try {
                $stmt_b = $pdo->prepare("SELECT limit_amount FROM budgets WHERE user_id = ? AND category_id = ? AND month = ? AND year = ?");
                $stmt_b->execute([$user_id, $category_id, $m, $y]);
                $budget = $stmt_b->fetch();

                if ($budget && $budget['limit_amount'] > 0) {
                    $stmt_s = $pdo->prepare("SELECT SUM(amount) AS total FROM transactions WHERE user_id = ? AND category_id = ? AND MONTH(transaction_date) = ? AND YEAR(transaction_date) = ?");
                    $stmt_s->execute([$user_id, $category_id, $m, $y]);
                    $spent = (int)($stmt_s->fetch()['total'] ?? 0);

                    if ($spent > $budget['limit_amount']) {
                        $vuot = number_format($spent - $budget['limit_amount'], 0, ',', '.');
                        $warn_param = "&warn=" . urlencode("Cảnh báo: Chi tiêu '{$valid_category['name']}' tháng {$m}/{$y} đã vượt hạn mức {$vuot} đ!");
                    }
                }
            } catch (PDOException $e) {}
        }

        header("Location: transactions.php?msg={$msg_type}{$warn_param}");
        exit;
    }
}

if (isset($_GET['msg'])) {
    $map = ['added' => 'Thêm giao dịch thành công!', 'updated' => 'Cập nhật giao dịch thành công!', 'deleted' => 'Đã xóa giao dịch!'];
    $success_msg = $map[$_GET['msg']] ?? '';
}
if (isset($_GET['warn'])) {
    $warning_msg = $_GET['warn'];
}

// 3. LẤY DỮ LIỆU KHI SỬA
$is_edit = false;
if (isset($_GET['edit_id']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE transaction_id = ? AND user_id = ?");
    $stmt->execute([(int)$_GET['edit_id'], $user_id]);
    if ($row = $stmt->fetch()) {
        $is_edit = true;
        $form_data = [
            'transaction_id' => $row['transaction_id'],
            'category_id' => $row['category_id'],
            'amount' => (int)$row['amount'],
            'transaction_date' => $row['transaction_date'],
            'note' => $row['note']
        ];
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $is_edit = true;
}

// 4. LẤY DANH MỤC & DANH SÁCH GIAO DỊCH CỦA NGƯỜI DÙNG
$stmt_cat = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? OR is_default = 1 ORDER BY type ASC, name ASC");
$stmt_cat->execute([$user_id]);
$categories = $stmt_cat->fetchAll();

$stmt_list = $pdo->prepare("SELECT t.*, c.name AS category_name, c.type AS category_type 
                            FROM transactions t 
                            JOIN categories c ON t.category_id = c.category_id 
                            WHERE t.user_id = ? 
                            ORDER BY t.transaction_date DESC, t.transaction_id DESC");
$stmt_list->execute([$user_id]);
$transactions = $stmt_list->fetchAll();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FR02 - Quản lý Giao dịch</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<!-- Thanh điều hướng chung cho các màn hình của nhóm -->
<nav class="navbar navbar-expand-lg navbar-dark bg-primary mb-4">
    <div class="container">
        <a class="navbar-brand fw-bold" href="dashboard.php">Quản Lý Thu Chi</a>
        <div class="navbar-nav">
            <a class="nav-link" href="dashboard.php">Tổng quan</a>
            <a class="nav-link active fw-semibold" href="transactions.php">Giao dịch (FR02)</a>
            <a class="nav-link" href="categories.php">Danh mục</a>
            <a class="nav-link" href="budgets.php">Hạn mức</a>
            <a class="nav-link" href="profile.php">Hồ sơ</a>
        </div>
    </div>
</nav>

<div class="container">
    <?php if ($success_msg): ?>
        <div class="alert alert-success alert-dismissible fade show"><?= htmlspecialchars($success_msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if ($warning_msg): ?>
        <div class="alert alert-warning fw-semibold alert-dismissible fade show">⚠️ <?= htmlspecialchars($warning_msg) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>
    <?php endif; ?>
    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($errors['general']) ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Form Thêm / Sửa giao dịch -->
        <div class="col-md-4 mb-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold text-primary">
                    <?= $is_edit ? 'Sửa giao dịch #' . $form_data['transaction_id'] : 'Thêm giao dịch mới' ?>
                </div>
                <div class="card-body">
                    <form method="POST" action="transactions.php" novalidate>
                        <input type="hidden" name="action" value="<?= $is_edit ? 'edit' : 'add' ?>">
                        <?php if ($is_edit): ?>
                            <input type="hidden" name="transaction_id" value="<?= htmlspecialchars($form_data['transaction_id']) ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Danh mục *</label>
                            <select name="category_id" class="form-select <?= isset($errors['category_id']) ? 'is-invalid' : '' ?>">
                                <option value="">-- Chọn danh mục --</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['category_id'] ?>" <?= ($form_data['category_id'] == $cat['category_id']) ? 'selected' : '' ?>>
                                        [<?= $cat['type'] === 'INCOME' ? 'Thu' : 'Chi' ?>] <?= htmlspecialchars($cat['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (isset($errors['category_id'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['category_id']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Số tiền (VNĐ) *</label>
                            <input type="number" name="amount" class="form-control <?= isset($errors['amount']) ? 'is-invalid' : '' ?>"
                                   value="<?= htmlspecialchars($form_data['amount']) ?>" placeholder="Nhập số tiền > 0">
                            <?php if (isset($errors['amount'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['amount']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Ngày giao dịch *</label>
                            <input type="date" name="transaction_date" class="form-control <?= isset($errors['transaction_date']) ? 'is-invalid' : '' ?>"
                                   value="<?= htmlspecialchars($form_data['transaction_date']) ?>">
                            <?php if (isset($errors['transaction_date'])): ?>
                                <div class="invalid-feedback"><?= htmlspecialchars($errors['transaction_date']) ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">Ghi chú</label>
                            <input type="text" name="note" class="form-control"
                                   value="<?= htmlspecialchars($form_data['note']) ?>" placeholder="Ghi chú giao dịch...">
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary"><?= $is_edit ? 'Cập nhật' : 'Lưu giao dịch' ?></button>
                            <?php if ($is_edit): ?>
                                <a href="transactions.php" class="btn btn-outline-secondary">Hủy</a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Bảng danh sách giao dịch -->
        <div class="col-md-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">Danh sách giao dịch</div>
                <div class="card-body p-0">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light">
                            <tr>
                                <th>Ngày</th>
                                <th>Danh mục</th>
                                <th>Ghi chú</th>
                                <th class="text-end">Số tiền</th>
                                <th class="text-center">Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr><td colspan="5" class="text-center py-4 text-muted">Không có dữ liệu</td></tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $t): ?>
                                    <tr>
                                        <td><?= date('d/m/Y', strtotime($t['transaction_date'])) ?></td>
                                        <td>
                                            <span class="badge <?= $t['category_type'] === 'INCOME' ? 'bg-success' : 'bg-danger' ?>">
                                                <?= $t['category_type'] === 'INCOME' ? 'Thu' : 'Chi' ?>
                                            </span>
                                            <?= htmlspecialchars($t['category_name']) ?>
                                        </td>
                                        <td><?= htmlspecialchars($t['note']) ?></td>
                                        <td class="text-end fw-bold <?= $t['category_type'] === 'INCOME' ? 'text-success' : 'text-danger' ?>">
                                            <?= $t['category_type'] === 'INCOME' ? '+' : '-' ?><?= number_format($t['amount'], 0, ',', '.') ?> đ
                                        </td>
                                        <td class="text-center">
                                            <a href="transactions.php?edit_id=<?= $t['transaction_id'] ?>" class="btn btn-sm btn-outline-primary">Sửa</a>
                                            <a href="transactions.php?delete_id=<?= $t['transaction_id'] ?>" class="btn btn-sm btn-outline-danger"
                                               onclick="return confirm('Xác nhận xóa giao dịch này?');">Xóa</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>