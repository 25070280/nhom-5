<?php
// profile.php: FR01 - xem và sửa hồ sơ, đổi mật khẩu (màn hình 2 trong phác thảo)
require __DIR__ . '/../includes/auth.php';
require_login();
$uid = current_user_id();      // luôn lấy id từ session, không lấy từ form (kiểm soát truy cập)

$errors = [];
$okMsg  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'info') {                       // ----- Lưu thông tin cá nhân -----
        $fullName = trim($_POST['full_name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        if ($fullName === '') {
            $errors['full_name'] = 'Vui lòng nhập họ tên.';
        } elseif (mb_strlen($fullName) > 100) {
            $errors['full_name'] = 'Họ tên tối đa 100 ký tự.';
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Email không đúng định dạng.';
        }
        if (!$errors) {
            $st = $pdo->prepare('UPDATE users SET full_name = ?, email = ? WHERE id = ?');
            $st->execute([$fullName, $email === '' ? null : $email, $uid]);
            $_SESSION['user_name'] = $fullName;
            $okMsg = 'Đã lưu thông tin.';
        }
    } elseif ($action === 'password') {             // ----- Đổi mật khẩu -----
        $current = $_POST['current'] ?? '';
        $new     = $_POST['new'] ?? '';
        $confirm = $_POST['confirm'] ?? '';
        $st = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
        $st->execute([$uid]);
        $row = $st->fetch();
        if (!$row || !password_verify($current, $row['password_hash'])) {
            $errors['current'] = 'Mật khẩu hiện tại không đúng.';
        }
        if (strlen($new) < 6) {
            $errors['new'] = 'Mật khẩu mới phải có ít nhất 6 ký tự.';
        }
        if ($confirm !== $new) {
            $errors['confirm'] = 'Mật khẩu nhập lại không khớp.';
        }
        if (!$errors) {
            $st = $pdo->prepare('UPDATE users SET password_hash = ? WHERE id = ?');
            $st->execute([password_hash($new, PASSWORD_DEFAULT), $uid]);
            $okMsg = 'Đã đổi mật khẩu.';
        }
    }
}

// Đọc thông tin hiện tại để hiển thị
$st = $pdo->prepare('SELECT username, full_name, email, created_at FROM users WHERE id = ?');
$st->execute([$uid]);
$user = $st->fetch();
$fullNameShow = ($_POST['action'] ?? '') === 'info' && $errors ? ($_POST['full_name'] ?? '') : $user['full_name'];
$emailShow    = ($_POST['action'] ?? '') === 'info' && $errors ? ($_POST['email'] ?? '')     : $user['email'];

$pageTitle  = 'Hồ sơ';
$activePage = 'profile';
require __DIR__ . '/../includes/header.php';
?>
<div class="top"><h1>Hồ sơ cá nhân</h1></div>

<?php if ($okMsg): ?><div class="ok"><?= e($okMsg) ?></div><?php endif; ?>
<?php if ($errors): ?><div class="err">Vui lòng sửa các lỗi bên dưới.</div><?php endif; ?>

<div class="card">
  <h2>Thông tin</h2>
  <form method="post" class="form" novalidate>
    <input type="hidden" name="action" value="info">
    <div class="field">
      <label>Tên đăng nhập (không đổi được)</label>
      <input class="inp" value="<?= e($user['username']) ?>" disabled>
    </div>
    <div class="field">
      <label for="full_name">Họ tên</label>
      <input class="inp<?= isset($errors['full_name']) ? ' bad' : '' ?>" id="full_name" name="full_name" value="<?= e($fullNameShow) ?>">
      <?php if (isset($errors['full_name'])): ?><div class="msg"><?= e($errors['full_name']) ?></div><?php endif; ?>
    </div>
    <div class="field">
      <label for="email">Email</label>
      <input class="inp<?= isset($errors['email']) ? ' bad' : '' ?>" id="email" name="email" value="<?= e($emailShow) ?>">
      <?php if (isset($errors['email'])): ?><div class="msg"><?= e($errors['email']) ?></div><?php endif; ?>
    </div>
    <div><button class="btn" type="submit">Lưu thông tin</button></div>
  </form>
</div>

<div class="card">
  <h2>Đổi mật khẩu</h2>
  <form method="post" class="form" novalidate>
    <input type="hidden" name="action" value="password">
    <div class="field">
      <label for="current">Mật khẩu hiện tại</label>
      <input class="inp<?= isset($errors['current']) ? ' bad' : '' ?>" id="current" name="current" type="password">
      <?php if (isset($errors['current'])): ?><div class="msg"><?= e($errors['current']) ?></div><?php endif; ?>
    </div>
    <div class="field">
      <label for="new">Mật khẩu mới (tối thiểu 6 ký tự)</label>
      <input class="inp<?= isset($errors['new']) ? ' bad' : '' ?>" id="new" name="new" type="password">
      <?php if (isset($errors['new'])): ?><div class="msg"><?= e($errors['new']) ?></div><?php endif; ?>
    </div>
    <div class="field">
      <label for="confirm">Nhập lại mật khẩu mới</label>
      <input class="inp<?= isset($errors['confirm']) ? ' bad' : '' ?>" id="confirm" name="confirm" type="password">
      <?php if (isset($errors['confirm'])): ?><div class="msg"><?= e($errors['confirm']) ?></div><?php endif; ?>
    </div>
    <div><button class="btn" type="submit">Đổi mật khẩu</button></div>
  </form>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
