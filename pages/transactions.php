<?php
// transactions.php: FR02 (thêm, sửa, xóa giao dịch) + FR04 (lọc, tìm kiếm) + cảnh báo hạn mức (BR05).
// Giao diện và chức năng do bạn cùng nhóm làm; đã đổi tên bảng/cột cho KHỚP CSDL CHUNG (database/schema.sql):
//   transaction_id -> id | transaction_date -> trans_date | category_id (khóa) -> id | INCOME/EXPENSE -> THU/CHI
$pageTitle  = 'Quản lý giao dịch';
$activePage = 'transactions';
require __DIR__ . '/../includes/auth.php';
require_login();
$uid = current_user_id();            // luôn lấy từ session: mỗi người chỉ thấy/sửa dữ liệu của mình (BR03)

function vnd($n) { return number_format((int)$n, 0, ',', '.'); }

// ----- Thông báo "một lần" (flash): lưu vào session rồi tải lại trang, tránh gửi form lại khi F5 -----
function flash($key, $msg) { $_SESSION[$key] = $msg; }

$errors = [];
$form   = null;                      // dữ liệu form khi nhập sai, để điền lại

// ===================== XỬ LÝ NÚT GỬI FORM (POST) =====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ----- 1. XÓA GIAO DỊCH -----
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $st = $pdo->prepare('DELETE FROM transactions WHERE id = ? AND user_id = ?');
        $st->execute([$id, $uid]);
        if ($st->rowCount() > 0) {
            flash('flash_ok', 'Đã xóa giao dịch thành công.');
        } else {
            flash('flash_err', 'Không thể xóa: giao dịch không tồn tại.');
        }
        header('Location: transactions.php');
        exit;
    }

    // ----- 2. THÊM HOẶC CẬP NHẬT GIAO DỊCH -----
    if ($action === 'save') {
        $id         = (int)($_POST['id'] ?? 0);                 // 0 = thêm mới, >0 = đang sửa
        $categoryId = (int)($_POST['category_id'] ?? 0);
        $amountRaw  = trim($_POST['amount'] ?? '');
        $transDate  = trim($_POST['trans_date'] ?? '');
        $note       = trim($_POST['note'] ?? '');
        $form = ['id' => $id, 'category_id' => $categoryId, 'amount' => $amountRaw,
                 'trans_date' => $transDate, 'note' => $note];

        // Kiểm tra dữ liệu (validation)
        $amount = filter_var($amountRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($amount === false) {
            $errors['amount'] = 'Số tiền phải là số nguyên lớn hơn 0 (VND) - BR01.';
        }
        $d = DateTime::createFromFormat('Y-m-d', $transDate);
        if (!$d || $d->format('Y-m-d') !== $transDate) {
            $errors['trans_date'] = 'Vui lòng chọn ngày giao dịch hợp lệ.';
        }
        if (mb_strlen($note) > 255) {
            $errors['note'] = 'Ghi chú tối đa 255 ký tự.';
        }
        $cat = null;
        if ($categoryId <= 0) {
            $errors['category_id'] = 'Vui lòng chọn danh mục.';
        } else {
            $st = $pdo->prepare('SELECT name, type FROM categories WHERE id = ? AND user_id = ?');
            $st->execute([$categoryId, $uid]);
            $cat = $st->fetch();
            if (!$cat) {
                $errors['category_id'] = 'Danh mục không hợp lệ.';
            }
        }
        if ($id > 0) {                               // đang sửa: giao dịch phải của chính người dùng
            $st = $pdo->prepare('SELECT id FROM transactions WHERE id = ? AND user_id = ?');
            $st->execute([$id, $uid]);
            if (!$st->fetch()) {
                $errors['general'] = 'Giao dịch không tồn tại.';
            }
        }

        if (!$errors) {
            if ($id > 0) {
                $st = $pdo->prepare('UPDATE transactions SET category_id = ?, amount = ?, trans_date = ?, note = ? WHERE id = ? AND user_id = ?');
                $st->execute([$categoryId, $amount, $transDate, $note === '' ? null : $note, $id, $uid]);
                flash('flash_ok', 'Cập nhật giao dịch thành công.');
            } else {
                $st = $pdo->prepare('INSERT INTO transactions (user_id, category_id, amount, trans_date, note) VALUES (?, ?, ?, ?, ?)');
                $st->execute([$uid, $categoryId, $amount, $transDate, $note === '' ? null : $note]);
                flash('flash_ok', 'Thêm giao dịch mới thành công.');
            }

            // ----- Cảnh báo hạn mức nếu là khoản CHI (BR05) -----
            if ($cat['type'] === 'CHI') {
                $y = (int)substr($transDate, 0, 4);
                $m = (int)substr($transDate, 5, 2);
                $st = $pdo->prepare('SELECT limit_amount FROM budgets WHERE user_id = ? AND category_id = ? AND year = ? AND month = ?');
                $st->execute([$uid, $categoryId, $y, $m]);
                $limit = (int)($st->fetchColumn() ?: 0);
                if ($limit > 0) {
                    $first = sprintf('%04d-%02d-01', $y, $m);
                    $last  = date('Y-m-t', strtotime($first));
                    $st = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM transactions WHERE user_id = ? AND category_id = ? AND trans_date BETWEEN ? AND ?');
                    $st->execute([$uid, $categoryId, $first, $last]);
                    $spent   = (int)$st->fetchColumn();
                    $percent = round($spent * 100 / $limit, 1);
                    if ($spent > $limit) {
                        flash('flash_warn', "Cảnh báo vượt hạn mức: danh mục '{$cat['name']}' tháng " . sprintf('%02d/%04d', $m, $y)
                            . " đã chi " . vnd($spent) . " / " . vnd($limit) . " VND.");
                    } elseif ($percent >= 80) {
                        flash('flash_warn', "Lưu ý hạn mức: danh mục '{$cat['name']}' tháng " . sprintf('%02d/%04d', $m, $y)
                            . " đã dùng {$percent}% hạn mức (" . vnd($spent) . " / " . vnd($limit) . " VND).");
                    }
                }
            }
            header('Location: transactions.php');
            exit;
        }
    }
}

// Lấy thông báo một lần
$msgOk = $_SESSION['flash_ok'] ?? ''; $msgErr = $_SESSION['flash_err'] ?? ''; $msgWarn = $_SESSION['flash_warn'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_err'], $_SESSION['flash_warn']);

// ===================== DỮ LIỆU ĐỂ HIỂN THỊ =====================
// Dữ liệu cho form: nhập sai thì giữ lại, bấm "Sửa" thì lấy từ CSDL, không thì form trống
$editData = null;
if ($form !== null) {
    $editData = $form;
} elseif (isset($_GET['edit_id'])) {
    $st = $pdo->prepare('SELECT id, category_id, amount, trans_date, note FROM transactions WHERE id = ? AND user_id = ?');
    $st->execute([(int)$_GET['edit_id'], $uid]);
    $editData = $st->fetch() ?: null;
}
$isEditing = $editData && !empty($editData['id']);

// Danh sách danh mục của người dùng (cho ô chọn)
$st = $pdo->prepare('SELECT id, name, type FROM categories WHERE user_id = ? ORDER BY (type = \'CHI\'), name ASC');
$st->execute([$uid]);
$catList = $st->fetchAll();

// ----- Bộ lọc và tìm kiếm (FR04) -----
$fromDate   = $_GET['from_date'] ?? '';
$toDate     = $_GET['to_date'] ?? '';
$filterType = $_GET['type'] ?? '';
$filterCat  = isset($_GET['category_id']) ? (int)$_GET['category_id'] : 0;
$keyword    = trim($_GET['keyword'] ?? '');

$sql = 'SELECT t.id, t.amount, t.trans_date, t.note, c.name AS category_name, c.type AS category_type
        FROM transactions t
        JOIN categories c ON c.id = t.category_id
        WHERE t.user_id = ?';
$params = [$uid];
if ($fromDate !== '') { $sql .= ' AND t.trans_date >= ?'; $params[] = $fromDate; }
if ($toDate   !== '') { $sql .= ' AND t.trans_date <= ?'; $params[] = $toDate; }
if ($filterType === 'THU' || $filterType === 'CHI') { $sql .= ' AND c.type = ?'; $params[] = $filterType; }
if ($filterCat > 0)   { $sql .= ' AND t.category_id = ?'; $params[] = $filterCat; }
if ($keyword !== '')  { $sql .= ' AND t.note LIKE ?'; $params[] = '%' . $keyword . '%'; }
$sql .= ' ORDER BY t.trans_date DESC, t.id DESC';
$st = $pdo->prepare($sql);
$st->execute($params);
$transactions = $st->fetchAll();

$totalIn = 0; $totalOut = 0;
foreach ($transactions as $row) {
    if ($row['category_type'] === 'THU') { $totalIn += (int)$row['amount']; }
    else                                 { $totalOut += (int)$row['amount']; }
}
$diff = $totalIn - $totalOut;

require __DIR__ . '/../includes/header.php';
?>
<div class="top"><h1>Quản lý giao dịch</h1></div>

<?php if ($msgOk):   ?><div class="ok"><?= e($msgOk) ?></div><?php endif; ?>
<?php if ($msgErr):  ?><div class="err"><?= e($msgErr) ?></div><?php endif; ?>
<?php if ($msgWarn): ?><div class="warn" role="status"><strong><?= e($msgWarn) ?></strong></div><?php endif; ?>
<?php if ($errors):  ?><div class="err"><?= implode('<br>', array_map('e', $errors)) ?></div><?php endif; ?>

<div class="stats">
  <div class="card"><div class="lbl">Tổng thu (đang lọc)</div><div class="big" style="color:#16a34a;">+<?= vnd($totalIn) ?> đ</div></div>
  <div class="card"><div class="lbl">Tổng chi (đang lọc)</div><div class="big" style="color:#dc2626;">−<?= vnd($totalOut) ?> đ</div></div>
  <div class="card"><div class="lbl">Chênh lệch</div><div class="big"><?= ($diff < 0 ? '−' : '') . vnd(abs($diff)) ?> đ</div></div>
</div>

<!-- Form Thêm / Sửa giao dịch (FR02) -->
<div class="card">
  <h2><?= $isEditing ? 'Cập nhật giao dịch' : 'Thêm giao dịch mới' ?></h2>
  <form method="post" action="transactions.php" novalidate
        style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;align-items:end;margin-top:12px;">
    <input type="hidden" name="action" value="save">
    <?php if ($isEditing): ?><input type="hidden" name="id" value="<?= (int)$editData['id'] ?>"><?php endif; ?>

    <div class="field">
      <label for="category_id">Danh mục *</label>
      <select class="inp<?= isset($errors['category_id']) ? ' bad' : '' ?>" id="category_id" name="category_id" required>
        <option value="">-- Chọn danh mục --</option>
        <?php foreach ($catList as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ($editData && (int)$editData['category_id'] === (int)$c['id']) ? 'selected' : '' ?>>
            [<?= $c['type'] === 'THU' ? 'Thu' : 'Chi' ?>] <?= e($c['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="field">
      <label for="amount">Số tiền (VNĐ) *</label>
      <input class="inp<?= isset($errors['amount']) ? ' bad' : '' ?>" id="amount" type="number" name="amount" min="1" step="1" required
             value="<?= $editData ? e($editData['amount']) : '' ?>" placeholder="VD: 50000">
    </div>
    <div class="field">
      <label for="trans_date">Ngày giao dịch *</label>
      <input class="inp<?= isset($errors['trans_date']) ? ' bad' : '' ?>" id="trans_date" type="date" name="trans_date" required
             value="<?= $editData ? e($editData['trans_date']) : date('Y-m-d') ?>">
    </div>
    <div class="field">
      <label for="note">Ghi chú</label>
      <input class="inp" id="note" type="text" name="note" maxlength="255"
             value="<?= $editData ? e($editData['note']) : '' ?>" placeholder="Nội dung chi tiêu...">
    </div>
    <div style="display:flex;gap:8px;white-space:nowrap;">
      <button type="submit" class="btn"><?= $isEditing ? 'Lưu cập nhật' : '+ Thêm giao dịch' ?></button>
      <?php if ($isEditing): ?><a class="btn ghost" href="transactions.php">Hủy</a><?php endif; ?>
    </div>
  </form>
</div>

<!-- Thanh lọc và tìm kiếm (FR04) -->
<div class="card">
  <form method="get" action="transactions.php"
        style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:10px;align-items:end;">
    <div class="field"><label for="from_date">Từ ngày</label>
      <input class="inp" id="from_date" type="date" name="from_date" value="<?= e($fromDate) ?>"></div>
    <div class="field"><label for="to_date">Đến ngày</label>
      <input class="inp" id="to_date" type="date" name="to_date" value="<?= e($toDate) ?>"></div>
    <div class="field"><label for="type">Loại</label>
      <select class="inp" id="type" name="type">
        <option value="">Tất cả</option>
        <option value="THU" <?= $filterType === 'THU' ? 'selected' : '' ?>>Khoản Thu</option>
        <option value="CHI" <?= $filterType === 'CHI' ? 'selected' : '' ?>>Khoản Chi</option>
      </select></div>
    <div class="field"><label for="f_cat">Danh mục</label>
      <select class="inp" id="f_cat" name="category_id">
        <option value="0">Tất cả danh mục</option>
        <?php foreach ($catList as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= $filterCat === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select></div>
    <div class="field"><label for="keyword">Tìm ghi chú</label>
      <input class="inp" id="keyword" type="text" name="keyword" value="<?= e($keyword) ?>" placeholder="Nhập từ khóa..."></div>
    <div style="display:flex;gap:6px;">
      <button type="submit" class="btn">Lọc</button>
      <a class="btn ghost" href="transactions.php">Xóa lọc</a>
    </div>
  </form>
</div>

<!-- Bảng danh sách giao dịch -->
<div class="tablebox">
  <table class="tb">
    <thead>
      <tr><th>Ngày</th><th>Loại</th><th>Danh mục</th><th>Ghi chú</th><th class="r">Số tiền</th><th style="text-align:center;">Thao tác</th></tr>
    </thead>
    <tbody>
    <?php if (!$transactions): ?>
      <tr><td colspan="6" class="hint" style="text-align:center;padding:20px;">Không tìm thấy giao dịch nào.</td></tr>
    <?php else: foreach ($transactions as $t): $isIn = $t['category_type'] === 'THU'; ?>
      <tr>
        <td><?= e(date('d/m/Y', strtotime($t['trans_date']))) ?></td>
        <td><span class="tag"><?= $isIn ? 'Thu' : 'Chi' ?></span></td>
        <td><?= e($t['category_name']) ?></td>
        <td><?= e($t['note']) ?></td>
        <td class="r" style="font-weight:600;color:<?= $isIn ? '#16a34a' : '#dc2626' ?>;"><?= $isIn ? '+' : '−' ?><?= vnd($t['amount']) ?> đ</td>
        <td style="text-align:center;white-space:nowrap;">
          <a class="lnk" href="transactions.php?edit_id=<?= (int)$t['id'] ?>">Sửa</a>
          <form method="post" action="transactions.php" style="display:inline"
                onsubmit="return confirm('Bạn chắc chắn muốn xóa giao dịch này?');">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
            <button type="submit" class="lnk" style="background:none;border:0;cursor:pointer;font:inherit;color:#dc2626;">Xóa</button>
          </form>
        </td>
      </tr>
    <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
