<?php
// categories.php: FR03 - quản lý danh mục thu / chi (thêm, sửa, xóa, lọc theo loại).
// Giao diện và chức năng do bạn cùng nhóm làm; đã sửa cho KHỚP CSDL CHUNG (database/schema.sql):
//   category_id -> id | INCOME/EXPENSE -> THU/CHI | thêm kiểm tra danh mục đang được dùng (giao dịch, hạn mức)
$pageTitle  = 'Quản lý danh mục';
$activePage = 'categories';
require __DIR__ . '/../includes/auth.php';
require_login();
$uid = current_user_id();            // mọi truy vấn đều lọc theo người đang đăng nhập (BR03)

function flash($key, $msg) { $_SESSION[$key] = $msg; }

$errors = [];
$form   = null;                      // dữ liệu form khi nhập sai, để điền lại

// ===================== XỬ LÝ NÚT GỬI FORM (POST) =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ----- 1. XÓA DANH MỤC -----
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        // danh mục phải là của người dùng này
        $st = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ?');
        $st->execute([$id, $uid]);
        if (!$st->fetch()) {
            flash('flash_err', 'Danh mục không tồn tại.');
        } else {
            // đang được dùng trong giao dịch hoặc hạn mức thì không cho xóa
            $st = $pdo->prepare('SELECT COUNT(*) FROM transactions WHERE category_id = ? AND user_id = ?');
            $st->execute([$id, $uid]);
            $usedTx = (int)$st->fetchColumn();
            $st = $pdo->prepare('SELECT COUNT(*) FROM budgets WHERE category_id = ? AND user_id = ?');
            $st->execute([$id, $uid]);
            $usedBd = (int)$st->fetchColumn();

            if ($usedTx > 0) {
                flash('flash_err', "Không thể xóa danh mục này vì đã có $usedTx giao dịch sử dụng.");
            } elseif ($usedBd > 0) {
                flash('flash_err', "Không thể xóa danh mục này vì đang có $usedBd hạn mức sử dụng.");
            } else {
                $st = $pdo->prepare('DELETE FROM categories WHERE id = ? AND user_id = ?');
                $st->execute([$id, $uid]);
                flash('flash_ok', 'Đã xóa danh mục thành công.');
            }
        }
        header('Location: categories.php');
        exit;
    }

    // ----- 2. THÊM MỚI HOẶC CẬP NHẬT DANH MỤC -----
    if ($action === 'save') {
        $id   = (int)($_POST['id'] ?? 0);              // 0 = thêm mới, >0 = đang sửa
        $name = trim($_POST['name'] ?? '');
        $type = $_POST['type'] ?? '';
        $form = ['id' => $id, 'name' => $name, 'type' => $type];

        if ($name === '') {
            $errors['name'] = 'Vui lòng nhập tên danh mục.';
        } elseif (mb_strlen($name) > 100) {
            $errors['name'] = 'Tên danh mục tối đa 100 ký tự.';
        }
        if (!in_array($type, ['THU', 'CHI'], true)) {
            $errors['type'] = 'Vui lòng chọn loại Thu hoặc Chi hợp lệ.';
        }

        // Trùng tên trong cùng người dùng (CSDL cũng không cho trùng tên, bất kể loại)
        if (!$errors) {
            $st = $pdo->prepare('SELECT id FROM categories WHERE user_id = ? AND name = ? AND id <> ?');
            $st->execute([$uid, $name, $id]);
            if ($st->fetch()) {
                $errors['name'] = 'Tên danh mục này đã tồn tại.';
            }
        }

        // Khi sửa: phải là danh mục của mình; đã có giao dịch/hạn mức thì không được đổi loại (BR04)
        if (!$errors && $id > 0) {
            $st = $pdo->prepare('SELECT type FROM categories WHERE id = ? AND user_id = ?');
            $st->execute([$id, $uid]);
            $old = $st->fetch();
            if (!$old) {
                $errors['general'] = 'Danh mục không tồn tại.';
            } elseif ($old['type'] !== $type) {
                $st = $pdo->prepare('SELECT (SELECT COUNT(*) FROM transactions WHERE category_id = ? AND user_id = ?)
                                          + (SELECT COUNT(*) FROM budgets WHERE category_id = ? AND user_id = ?)');
                $st->execute([$id, $uid, $id, $uid]);
                if ((int)$st->fetchColumn() > 0) {
                    $errors['type'] = 'Không thể đổi loại vì danh mục đã có giao dịch hoặc hạn mức.';
                }
            }
        }

        if (!$errors) {
            if ($id > 0) {
                $st = $pdo->prepare('UPDATE categories SET name = ?, type = ? WHERE id = ? AND user_id = ?');
                $st->execute([$name, $type, $id, $uid]);
                flash('flash_ok', 'Cập nhật danh mục thành công.');
            } else {
                $st = $pdo->prepare('INSERT INTO categories (user_id, name, type) VALUES (?, ?, ?)');
                $st->execute([$uid, $name, $type]);
                flash('flash_ok', 'Thêm danh mục mới thành công.');
            }
            header('Location: categories.php');
            exit;
        }
    }
}

// Thông báo một lần
$msgOk = $_SESSION['flash_ok'] ?? ''; $msgErr = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

// ===================== DỮ LIỆU ĐỂ HIỂN THỊ =====================
$editCat = null;
if ($form !== null) {
    $editCat = $form;                                   // nhập sai: giữ lại những gì vừa gõ
} elseif (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT id, name, type FROM categories WHERE id = ? AND user_id = ?');
    $st->execute([(int)$_GET['edit'], $uid]);
    $editCat = $st->fetch() ?: null;
}
$isEditing = $editCat && !empty($editCat['id']);

$filterType = $_GET['type'] ?? '';
if ($filterType === 'THU' || $filterType === 'CHI') {
    $st = $pdo->prepare('SELECT id, name, type FROM categories WHERE user_id = ? AND type = ? ORDER BY (type = \'CHI\'), name ASC');
    $st->execute([$uid, $filterType]);
} else {
    $filterType = '';
    $st = $pdo->prepare('SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY (type = \'CHI\'), name ASC');
    $st->execute([$uid]);
}
$categories = $st->fetchAll();

require __DIR__ . '/../includes/header.php';
?>
<div class="top"><h1>Quản lý danh mục Thu / Chi</h1></div>

<?php if ($msgOk):  ?><div class="ok"><?= e($msgOk) ?></div><?php endif; ?>
<?php if ($msgErr): ?><div class="err"><?= e($msgErr) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="err"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

<!-- Form Thêm / Sửa danh mục -->
<div class="card">
  <h2><?= $isEditing ? 'Sửa danh mục' : 'Thêm danh mục mới' ?></h2>
  <form method="post" action="categories.php" novalidate
        style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-top:12px;">
    <input type="hidden" name="action" value="save">
    <?php if ($isEditing): ?><input type="hidden" name="id" value="<?= (int)$editCat['id'] ?>"><?php endif; ?>

    <div class="field" style="flex:1;min-width:200px;">
      <label for="name">Tên danh mục *</label>
      <input class="inp<?= isset($errors['name']) ? ' bad' : '' ?>" id="name" type="text" name="name" maxlength="100" required
             placeholder="VD: Tiền nhà, Lương, Cà phê..." value="<?= e($editCat['name'] ?? '') ?>">
    </div>
    <div class="field" style="min-width:180px;">
      <label for="type">Loại *</label>
      <select class="inp<?= isset($errors['type']) ? ' bad' : '' ?>" id="type" name="type" required>
        <option value="THU" <?= ($editCat['type'] ?? '') === 'THU' ? 'selected' : '' ?>>Khoản Thu</option>
        <option value="CHI" <?= ($editCat['type'] ?? 'CHI') === 'CHI' ? 'selected' : '' ?>>Khoản Chi</option>
      </select>
    </div>
    <div style="display:flex;gap:8px;white-space:nowrap;">
      <button type="submit" class="btn"><?= $isEditing ? 'Cập nhật' : '+ Thêm danh mục' ?></button>
      <?php if ($isEditing): ?><a class="btn ghost" href="categories.php">Hủy</a><?php endif; ?>
    </div>
  </form>
</div>

<!-- Bộ lọc nhanh theo loại -->
<div style="display:flex;gap:8px;">
  <a class="btn<?= $filterType === '' ? '' : ' ghost' ?>" href="categories.php">Tất cả</a>
  <a class="btn<?= $filterType === 'THU' ? '' : ' ghost' ?>" href="categories.php?type=THU">Khoản Thu</a>
  <a class="btn<?= $filterType === 'CHI' ? '' : ' ghost' ?>" href="categories.php?type=CHI">Khoản Chi</a>
</div>

<!-- Bảng danh mục -->
<div class="tablebox">
  <table class="tb">
    <thead><tr><th>Tên danh mục</th><th>Loại</th><th class="r">Thao tác</th></tr></thead>
    <tbody>
    <?php if (!$categories): ?>
      <tr><td colspan="3" class="hint" style="text-align:center;padding:20px;">Chưa có danh mục nào.</td></tr>
    <?php else: foreach ($categories as $c): ?>
      <tr>
        <td><strong><?= e($c['name']) ?></strong></td>
        <td>
          <?php if ($c['type'] === 'THU'): ?>
            <span style="background:#dcfce7;color:#166534;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600;">Khoản Thu</span>
          <?php else: ?>
            <span style="background:#fee2e2;color:#991b1b;padding:3px 8px;border-radius:4px;font-size:12px;font-weight:600;">Khoản Chi</span>
          <?php endif; ?>
        </td>
        <td class="r" style="white-space:nowrap;">
          <a class="lnk" href="categories.php?edit=<?= (int)$c['id'] ?>">Sửa</a>
          <form method="post" action="categories.php" style="display:inline"
                onsubmit="return confirm('Bạn chắc chắn muốn xóa danh mục này?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
            <button type="submit" class="lnk" style="background:none;border:0;cursor:pointer;font:inherit;color:#dc2626;margin:0;">Xóa</button>
          </form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
