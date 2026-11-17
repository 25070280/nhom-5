<?php
session_start();
require_once __DIR__ . '/../config/db.php';

$user_id = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 1;

$message = '';
$error = '';
$edit_cat = null;

// 1. XỬ LÝ XÓA DANH MỤC
if (isset($_GET['delete'])) {
    $del_id = (int)$_GET['delete'];
    // Kiểm tra xem danh mục có đang được dùng trong giao dịch hay hạn mức không
    $check_tx = $pdo->prepare("SELECT COUNT(*) FROM transactions WHERE category_id = ? AND user_id = ?");
    $check_tx->execute([$del_id, $user_id]);
    
    if ((int)$check_tx->fetchColumn() > 0) {
        $error = "Không thể xóa danh mục này vì đã có giao dịch sử dụng.";
    } else {
        $del = $pdo->prepare("DELETE FROM categories WHERE category_id = ? AND user_id = ?");
        if ($del->execute([$del_id, $user_id])) {
            $message = "Đã xóa danh mục thành công.";
        } else {
            $error = "Có lỗi xảy ra khi xóa danh mục.";
        }
    }
}

// 2. XỬ LÝ THÊM MỚI HOẶC CẬP NHẬT DANH MỤC
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cat_id = isset($_POST['category_id']) ? (int)$_POST['category_id'] : 0;
    $name   = trim($_POST['name'] ?? '');
    $type   = $_POST['type'] ?? '';

    if ($name === '' || !in_array($type, ['INCOME', 'EXPENSE'], true)) {
        $error = "Vui lòng nhập tên danh mục và chọn loại Thu hoặc Chi hợp lệ.";
    } else {
        // Kiểm tra trùng tên danh mục cùng loại của user
        $dup = $pdo->prepare("SELECT category_id FROM categories WHERE user_id = ? AND name = ? AND type = ? AND category_id != ?");
        $dup->execute([$user_id, $name, $type, $cat_id]);
        if ($dup->fetch()) {
            $error = "Danh mục này đã tồn tại.";
        } else {
            if ($cat_id > 0) {
                // Cập nhật danh mục
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, type = ? WHERE category_id = ? AND user_id = ?");
                $ok = $stmt->execute([$name, $type, $cat_id, $user_id]);
                $message = $ok ? "Cập nhật danh mục thành công." : "Lỗi khi cập nhật danh mục.";
            } else {
                // Thêm mới danh mục
                $stmt = $pdo->prepare("INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)");
                $ok = $stmt->execute([$user_id, $name, $type]);
                $message = $ok ? "Thêm danh mục mới thành công." : "Lỗi khi thêm danh mục.";
            }
        }
    }
}

// 3. LẤY THÔNG TIN DANH MỤC ĐANG SỬA (NẾU BẤM NÚT SỬA)
if (isset($_GET['edit'])) {
    $edit_id = (int)$_GET['edit'];
    $e_stmt = $pdo->prepare("SELECT * FROM categories WHERE category_id = ? AND user_id = ?");
    $e_stmt->execute([$edit_id, $user_id]);
    $edit_cat = $e_stmt->fetch();
}

// 4. LẤY DANH SÁCH TOÀN BỘ DANH MỤC CỦA USER
$filter_type = $_GET['type'] ?? '';
if (in_array($filter_type, ['INCOME', 'EXPENSE'], true)) {
    $list_stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? AND type = ? ORDER BY type ASC, name ASC");
    $list_stmt->execute([$user_id, $filter_type]);
} else {
    $list_stmt = $pdo->prepare("SELECT * FROM categories WHERE user_id = ? ORDER BY type ASC, name ASC");
    $list_stmt->execute([$user_id]);
}
$categories = $list_stmt->fetchAll();

$pageTitle  = 'Quản lý danh mục';
$activePage = 'categories';
require __DIR__ . '/../includes/header.php';
?>

<div class="top">
    <h1>Quản lý danh mục Thu / Chi</h1>
</div>

<?php if ($message): ?>
    <div style="background:#dcfce7;color:#166534;padding:12px;border-radius:6px;margin-bottom:14px;"><?= htmlspecialchars($message) ?></div>
<?php endif; ?>
<?php if ($error): ?>
    <div style="background:#fee2e2;color:#991b1b;padding:12px;border-radius:6px;margin-bottom:14px;"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<!-- Form Thêm / Sửa danh mục -->
<div style="background:#fff;padding:16px;border:1px solid #d1d5db;border-radius:8px;margin-bottom:18px;">
    <h2 style="margin-bottom:12px;font-size:16px;"><?= $edit_cat ? 'Sửa danh mục' : 'Thêm danh mục mới' ?></h2>
    <form method="POST" action="categories.php" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
        <input type="hidden" name="category_id" value="<?= (int)($edit_cat['category_id'] ?? 0) ?>">
        
        <div style="flex:1;min-width:200px;">
            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Tên danh mục *</label>
            <input type="text" name="name" required placeholder="VD: Tiền nhà, Lương, Cà phê..." 
                   value="<?= htmlspecialchars($edit_cat['name'] ?? '') ?>" 
                   style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;">
        </div>

        <div style="min-width:160px;">
            <label style="display:block;font-size:13px;font-weight:600;margin-bottom:4px;">Loại *</label>
            <select name="type" required style="width:100%;padding:8px;border:1px solid #cbd5e1;border-radius:6px;">
                <option value="EXPENSE" <?= (($edit_cat['type'] ?? '') === 'EXPENSE') ? 'selected' : '' ?>>Khoản Chi (EXPENSE)</option>
                <option value="INCOME" <?= (($edit_cat['type'] ?? '') === 'INCOME') ? 'selected' : '' ?>>Khoản Thu (INCOME)</option>
            </select>
        </div>

        <div style="display:flex;gap:8px;">
            <button type="submit" class="btn"><?= $edit_cat ? 'Cập nhật' : '+ Thêm danh mục' ?></button>
            <?php if ($edit_cat): ?>
                <a href="categories.php" style="padding:8px 14px;border:1px solid #cbd5e1;border-radius:6px;text-decoration:none;color:#334155;">Hủy</a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- Bộ lọc nhanh -->
<div style="margin-bottom:12px;display:flex;gap:8px;">
    <a href="categories.php" style="padding:6px 12px;border-radius:6px;text-decoration:none;border:1px solid #cbd5e1;<?= $filter_type === '' ? 'background:#1e293b;color:#fff;' : 'background:#fff;color:#334155;' ?>">Tất cả</a>
    <a href="categories.php?type=INCOME" style="padding:6px 12px;border-radius:6px;text-decoration:none;border:1px solid #cbd5e1;<?= $filter_type === 'INCOME' ? 'background:#16a34a;color:#fff;' : 'background:#fff;color:#334155;' ?>">Khoản Thu</a>
    <a href="categories.php?type=EXPENSE" style="padding:6px 12px;border-radius:6px;text-decoration:none;border:1px solid #cbd5e1;<?= $filter_type === 'EXPENSE' ? 'background:#dc2626;color:#fff;' : 'background:#fff;color:#334155;' ?>">Khoản Chi</a>
</div>

<!-- Bảng danh sách danh mục -->
<div class="tablebox">
    <table class="tb">
        <thead>
            <tr>
                <th>Tên danh mục</th>
                <th>Loại</th>
                <th class="r">Thao tác</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($categories)): ?>
                <tr>
                    <td colspan="3" style="text-align:center;color:#64748b;">Chưa có danh mục nào.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td><strong><?= htmlspecialchars($cat['name']) ?></strong></td>
                        <td>
                            <?php if ($cat['type'] === 'INCOME'): ?>
                                <span style="background:#dcfce7;color:#166534;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600;">Khoản Thu</span>
                            <?php else: ?>
                                <span style="background:#fee2e2;color:#991b1b;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600;">Khoản Chi</span>
                            <?php endif; ?>
                        </td>
                        <td class="r">
                            <a href="categories.php?edit=<?= (int)$cat['category_id'] ?>" style="color:#2563eb;text-decoration:none;margin-right:10px;">Sửa</a>
                            <a href="categories.php?delete=<?= (int)$cat['category_id'] ?>" onclick="return confirm('Bạn chắc chắn muốn xóa danh mục này?')" style="color:#dc2626;text-decoration:none;">Xóa</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>