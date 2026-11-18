<?php
// budgets.php: FR05 - đặt hạn mức chi theo THÁNG cho từng danh mục CHI, cảnh báo khi vượt (BR05).
// Chọn tháng bằng địa chỉ: budgets.php?m=2026-11 (không ghi thì lấy tháng hiện tại).
$pageTitle  = 'Hạn mức chi';
$activePage = 'budgets';
require __DIR__ . '/../includes/auth.php';
require_login();
$uid = current_user_id();            // chỉ làm việc với dữ liệu của người đang đăng nhập (BR03)

function vnd($n) { return number_format((int)$n, 0, ',', '.'); }
function flash($key, $msg) { $_SESSION[$key] = $msg; }

// ----- Xác định tháng đang xem -----
// Khi gửi form thì lấy tháng từ ô ẩn "m", khi xem trang thì lấy từ địa chỉ
$m = $_POST['m'] ?? $_GET['m'] ?? date('Y-m');
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)) {
    $m = date('Y-m');
}
[$year, $month] = array_map('intval', explode('-', $m));
$from = sprintf('%04d-%02d-01', $year, $month);
$to   = date('Y-m-t', strtotime($from));
$prev = date('Y-m', strtotime($from . ' -1 month'));
$next = date('Y-m', strtotime($from . ' +1 month'));

$errors = [];
$form   = null;

// ===================== XỬ LÝ NÚT GỬI FORM (POST) =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ----- Xóa hạn mức -----
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('DELETE FROM budgets WHERE id = ? AND user_id = ?');
        $st->execute([$id, $uid]);
        flash($st->rowCount() > 0 ? 'flash_ok' : 'flash_err',
              $st->rowCount() > 0 ? 'Đã xóa hạn mức.' : 'Hạn mức không tồn tại.');
        header('Location: budgets.php?m=' . $m);
        exit;
    }

    // ----- Thêm mới hoặc cập nhật hạn mức -----
    if ($action === 'save') {
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $limitRaw   = trim($_POST['limit_amount'] ?? '');
        $form = ['category_id' => $categoryId, 'limit_amount' => $limitRaw];

        $limit = filter_var($limitRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($limit === false) {
            $errors['limit_amount'] = 'Hạn mức phải là số nguyên lớn hơn 0 (VND).';
        }
        // Danh mục phải là của người dùng VÀ là loại CHI (hạn mức chỉ áp dụng cho khoản chi)
        $st = $pdo->prepare('SELECT id FROM categories WHERE id = ? AND user_id = ? AND type = \'CHI\'');
        $st->execute([$categoryId, $uid]);
        if (!$st->fetch()) {
            $errors['category_id'] = 'Vui lòng chọn một danh mục Chi hợp lệ.';
        }

        if (!$errors) {
            // Mỗi danh mục chỉ có MỘT hạn mức cho mỗi tháng: đã có thì cập nhật, chưa có thì thêm
            $st = $pdo->prepare('SELECT id FROM budgets WHERE user_id = ? AND category_id = ? AND year = ? AND month = ?');
            $st->execute([$uid, $categoryId, $year, $month]);
            $existing = $st->fetchColumn();
            if ($existing) {
                $st = $pdo->prepare('UPDATE budgets SET limit_amount = ? WHERE id = ? AND user_id = ?');
                $st->execute([$limit, $existing, $uid]);
                flash('flash_ok', 'Đã cập nhật hạn mức tháng ' . sprintf('%02d/%04d', $month, $year) . '.');
            } else {
                $st = $pdo->prepare('INSERT INTO budgets (user_id, category_id, year, month, limit_amount) VALUES (?, ?, ?, ?, ?)');
                $st->execute([$uid, $categoryId, $year, $month, $limit]);
                flash('flash_ok', 'Đã đặt hạn mức tháng ' . sprintf('%02d/%04d', $month, $year) . '.');
            }
            header('Location: budgets.php?m=' . $m);
            exit;
        }
    }
}

$msgOk = $_SESSION['flash_ok'] ?? ''; $msgErr = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

// ===================== DỮ LIỆU ĐỂ HIỂN THỊ =====================
// Danh mục CHI của người dùng (cho ô chọn)
$st = $pdo->prepare('SELECT id, name FROM categories WHERE user_id = ? AND type = \'CHI\' ORDER BY name');
$st->execute([$uid]);
$catList = $st->fetchAll();

// Bấm "Sửa": điền sẵn danh mục và số tiền vào form
$editCat = null; $editLimit = '';
if ($form !== null) {
    $editCat = $form['category_id']; $editLimit = $form['limit_amount'];
} elseif (isset($_GET['edit'])) {
    $st = $pdo->prepare('SELECT category_id, limit_amount FROM budgets WHERE id = ? AND user_id = ? AND year = ? AND month = ?');
    $st->execute([(int)$_GET['edit'], $uid, $year, $month]);
    if ($row = $st->fetch()) { $editCat = (int)$row['category_id']; $editLimit = $row['limit_amount']; }
}

// Hạn mức của tháng kèm số tiền ĐÃ CHI thực tế (cộng các giao dịch của danh mục trong tháng)
$st = $pdo->prepare(
    'SELECT b.id, c.name, b.limit_amount, COALESCE(SUM(t.amount), 0) AS spent
     FROM budgets b
     JOIN categories c ON c.id = b.category_id
     LEFT JOIN transactions t
            ON t.category_id = b.category_id AND t.user_id = b.user_id AND t.trans_date BETWEEN ? AND ?
     WHERE b.user_id = ? AND b.year = ? AND b.month = ?
     GROUP BY b.id, c.name, b.limit_amount
     ORDER BY c.name'
);
$st->execute([$from, $to, $uid, $year, $month]);
$budgets = $st->fetchAll();
$over = array_filter($budgets, fn($b) => (int)$b['spent'] > (int)$b['limit_amount']);

require __DIR__ . '/../includes/header.php';
?>
<div class="top">
  <h1>Hạn mức chi tháng <?= sprintf('%02d/%04d', $month, $year) ?></h1>
  <div>
    <a class="btn ghost" href="?m=<?= e($prev) ?>">&lt; Tháng trước</a>
    <a class="btn ghost" href="?m=<?= e($next) ?>">Tháng sau &gt;</a>
  </div>
</div>

<?php if ($msgOk):  ?><div class="ok"><?= e($msgOk) ?></div><?php endif; ?>
<?php if ($msgErr): ?><div class="err"><?= e($msgErr) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="err"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>
<?php foreach ($over as $b): ?>
  <div class="warn" role="status">
    <strong>Cảnh báo hạn mức:</strong> danh mục <?= e($b['name']) ?> đã chi <?= vnd($b['spent']) ?> / <?= vnd($b['limit_amount']) ?> VND
    (vượt <?= vnd($b['spent'] - $b['limit_amount']) ?>).
  </div>
<?php endforeach; ?>

<!-- Form đặt hạn mức -->
<div class="card">
  <h2>Đặt hạn mức cho tháng <?= sprintf('%02d/%04d', $month, $year) ?></h2>
  <p class="hint">Chọn danh mục chi và số tiền tối đa. Nếu danh mục đã có hạn mức trong tháng này, số tiền mới sẽ thay số cũ.</p>
  <?php if (!$catList): ?>
    <div class="warn">Bạn chưa có danh mục Chi nào. Hãy tạo ở trang <a href="categories.php">Danh mục</a> trước.</div>
  <?php else: ?>
  <form method="post" action="budgets.php?m=<?= e($m) ?>" novalidate
        style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;margin-top:12px;">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="m" value="<?= e($m) ?>">
    <div class="field" style="flex:1;min-width:200px;">
      <label for="category_id">Danh mục chi *</label>
      <select class="inp<?= isset($errors['category_id']) ? ' bad' : '' ?>" id="category_id" name="category_id" required>
        <option value="">-- Chọn danh mục --</option>
        <?php foreach ($catList as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= (int)$editCat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field" style="flex:1;min-width:200px;">
      <label for="limit_amount">Hạn mức (VNĐ) *</label>
      <input class="inp<?= isset($errors['limit_amount']) ? ' bad' : '' ?>" id="limit_amount" type="number" name="limit_amount" min="1" step="1" required
             value="<?= e($editLimit) ?>" placeholder="VD: 2000000">
    </div>
    <div style="display:flex;gap:8px;white-space:nowrap;">
      <button type="submit" class="btn">Lưu hạn mức</button>
      <?php if (isset($_GET['edit'])): ?><a class="btn ghost" href="budgets.php?m=<?= e($m) ?>">Hủy</a><?php endif; ?>
    </div>
  </form>
  <?php endif; ?>
</div>

<!-- Bảng hạn mức và mức đã chi -->
<div class="tablebox">
  <table class="tb">
    <thead>
      <tr><th>Danh mục</th><th class="r">Hạn mức</th><th class="r">Đã chi</th><th style="min-width:150px;">Mức sử dụng</th><th>Tình trạng</th><th class="r">Thao tác</th></tr>
    </thead>
    <tbody>
    <?php if (!$budgets): ?>
      <tr><td colspan="6" class="hint" style="text-align:center;padding:20px;">Tháng này chưa đặt hạn mức nào.</td></tr>
    <?php else: foreach ($budgets as $b):
        $limit = (int)$b['limit_amount']; $spent = (int)$b['spent'];
        $pct = $limit > 0 ? (int)round($spent * 100 / $limit) : 0;
        if ($spent > $limit)      { $status = 'Vượt ' . vnd($spent - $limit); $color = '#b45309'; }
        elseif ($pct >= 80)       { $status = 'Sắp hết (còn ' . vnd($limit - $spent) . ')'; $color = '#b45309'; }
        else                      { $status = 'Còn ' . vnd($limit - $spent); $color = '#166534'; }
    ?>
      <tr>
        <td><strong><?= e($b['name']) ?></strong></td>
        <td class="r"><?= vnd($limit) ?></td>
        <td class="r"><?= vnd($spent) ?></td>
        <td>
          <div class="bar"><div class="fill<?= $spent > $limit ? ' over' : '' ?>" style="width:<?= min(100, $pct) ?>%"></div></div>
          <div class="hint"><?= $pct ?>%</div>
        </td>
        <td style="color:<?= $color ?>;font-weight:600;"><?= e($status) ?></td>
        <td class="r" style="white-space:nowrap;">
          <a class="lnk" href="budgets.php?m=<?= e($m) ?>&amp;edit=<?= (int)$b['id'] ?>">Sửa</a>
          <form method="post" action="budgets.php?m=<?= e($m) ?>" style="display:inline"
                onsubmit="return confirm('Bạn chắc chắn muốn xóa hạn mức này?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="m" value="<?= e($m) ?>">
            <input type="hidden" name="id" value="<?= (int)$b['id'] ?>">
            <button type="submit" class="lnk" style="background:none;border:0;cursor:pointer;font:inherit;color:#dc2626;margin:0;">Xóa</button>
          </form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
