<?php
session_start();
require_once __DIR__ . '/../config/db.php';

// Dùng chung user_id với trang Giao dịch (mặc định 1 nếu chưa đăng nhập)
$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

$message = '';
$error = '';

// 1. XỬ LÝ CẬP NHẬT THÔNG TIN HỒ SƠ
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username     = trim($_POST['username'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';

    if ($username === '' || $email === '') {
        $error = "Tên đăng nhập và Email không được để trống.";
    } else {
        // Kiểm tra trùng email với người dùng khác
        $check = $pdo->prepare("SELECT user_id FROM Users WHERE email = ? AND user_id != ?");
        $check->execute([$email, $user_id]);
        if ($check->fetch()) {
            $error = "Email này đã được tài khoản khác sử dụng.";
        } else {
            if ($new_password !== '') {
                $hash = password_hash($new_password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("UPDATE Users SET username = ?, email = ?, password_hash = ? WHERE user_id = ?");
                $ok = $stmt->execute([$username, $email, $hash, $user_id]);
            } else {
                $stmt = $pdo->prepare("UPDATE Users SET username = ?, email = ? WHERE user_id = ?");
                $ok = $stmt->execute([$username, $email, $user_id]);
            }

            if ($ok) {
                $_SESSION['username'] = $username; // Đồng bộ tên lên thanh menu/header
                $message = "Cập nhật hồ sơ thành công.";
            } else {
                $error = "Có lỗi xảy ra khi lưu thông tin.";
            }
        }
    }
}

// 2. LẤY THÔNG TIN TÀI KHOẢN HIỆN TẠI
$stmt = $pdo->prepare("SELECT user_id, username, email FROM Users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// 3. THỐNG KÊ NHANH TỔNG THU / CHI CỦA USER NÀY (Nối dữ liệu từ transactions)
$s_stmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN c.type = 'INCOME' THEN t.amount ELSE 0 END), 0) AS total_in,
        COALESCE(SUM(CASE WHEN c.type = 'EXPENSE' THEN t.amount ELSE 0 END), 0) AS total_out,
        COUNT(t.transaction_id) AS total_tx
    FROM transactions t
    JOIN categories c ON t.category_id = c.category_id
    WHERE t.user_id = ?
");
$s_stmt->execute([$user_id]);
$summary = $s_stmt->fetch();

// Gọi khung giao diện chung của nhóm
$pageTitle  = 'Hồ sơ cá nhân';
$activePage = 'profile';
require __DIR__ . '/../includes/header.php';
?>

<div class="top">
    <h1>Hồ sơ cá nhân</h1>
</div>

<?php if ($message): ?>
    <div style="background:#dcfce7;color:#166534;padding:12px;border-radius:6px;margin-bottom:14px;"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:6px;margin-bottom:14px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Thống kê hoạt động nối từ bảng transactions -->
<div class="stats">
    <div class="card">
        <div class="lbl">Tổng số giao dịch</div>
        <div class="big"><?= (int)$summary['total_tx'] ?></div>
    </div>
    <div class="card">
        <div class="lbl">Tổng thu tích lũy</div>
        <div class="big" style="color:#16a34a;">+<?= number_format($summary['total_in']) ?> đ</div>
    </div>
    <div class="card">
        <div class="lbl">Tổng chi tích lũy</div>
        <div class="big" style="color:#dc2626;">-<?= number_format($summary['total_out']) ?> đ</div>
    </div>
</div>

<!-- Form cập nhật thông tin tài khoản -->
<div style="background:#fff;padding:18px;border:1px solid #d1d5db;border-radius:8px;max-width:560px;">
    <h2 style="margin-bottom:14px;">Thông tin tài khoản</h2>
    <form method="POST" action="profile.php" style="display:flex;flex-direction:column;gap:12px;">
        <div>
            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Tên đăng nhập *</label>
            <input type="text" name="username" required value="<?= htmlspecialchars($user['username'] ?? '') ?>" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;">
        </div>

        <div>
            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Email *</label>
            <input type="email" name="email" required value="<?= htmlspecialchars($user['email'] ?? '') ?>" style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;">
        </div>

        <div>
            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Mật khẩu mới (bỏ trống nếu không đổi)</label>
            <input type="password" name="new_password" placeholder="Nhập mật khẩu mới..." style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;">
        </div>

        <div>
            <button type="submit" class="btn">Lưu thay đổi</button>
        </div>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>