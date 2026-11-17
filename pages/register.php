<?php
// register.php: FR01 - đăng ký tài khoản (màn hình 1 trong phác thảo)
require __DIR__ . '/../includes/auth.php';

if (current_user_id()) {            // đã đăng nhập thì không cần đăng ký
    header('Location: dashboard.php');
    exit;
}

$errors = [];
$old = ['username' => '', 'full_name' => '', 'email' => ''];   // để điền lại form khi có lỗi

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';
    $old = ['username' => $username, 'full_name' => $fullName, 'email' => $email];

    // ----- Kiểm tra dữ liệu (validation), mỗi lỗi có thông báo rõ ràng -----
    if ($username === '') {
        $errors['username'] = 'Vui lòng nhập tên đăng nhập.';
    } elseif (!preg_match('/^[A-Za-z0-9_]{3,50}$/', $username)) {
        $errors['username'] = 'Tên đăng nhập gồm 3-50 ký tự: chữ không dấu, số hoặc dấu gạch dưới.';
    }
    if ($fullName === '') {
        $errors['full_name'] = 'Vui lòng nhập họ tên.';
    } elseif (mb_strlen($fullName) > 100) {
        $errors['full_name'] = 'Họ tên tối đa 100 ký tự.';
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Email không đúng định dạng.';
    }
    if (strlen($password) < 6) {
        $errors['password'] = 'Mật khẩu phải có ít nhất 6 ký tự.';
    }
    if ($confirm !== $password) {
        $errors['confirm'] = 'Mật khẩu nhập lại không khớp.';
    }

    // ----- Kiểm tra trùng tên đăng nhập, rồi lưu -----
    if (!$errors) {
        $st = $pdo->prepare('SELECT id FROM users WHERE username = ?');
        $st->execute([$username]);
        if ($st->fetch()) {
            $errors['username'] = 'Tên đăng nhập này đã có người dùng.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);   // băm mật khẩu, KHÔNG lưu mật khẩu thật
            $st = $pdo->prepare('INSERT INTO users (username, password_hash, full_name, email) VALUES (?, ?, ?, ?)');
            $st->execute([$username, $hash, $fullName, $email === '' ? null : $email]);
            header('Location: login.php?registered=1');
            exit;
        }
    }
}

$pageTitle = 'Đăng ký';
$showMenu  = false;
require __DIR__ . '/../includes/header.php';
?>
<h1>Thu chi cá nhân</h1>
<div class="card auth">
  <h2>Đăng ký tài khoản</h2>
  <?php if ($errors): ?><div class="err">Vui lòng sửa các lỗi bên dưới.</div><?php endif; ?>
  <form method="post" class="form" novalidate>
    <div class="field">
      <label for="username">Tên đăng nhập</label>
      <input class="inp<?= isset($errors['username']) ? ' bad' : '' ?>" id="username" name="username" value="<?= e($old['username']) ?>">
      <?php if (isset($errors['username'])): ?><div class="msg"><?= e($errors['username']) ?></div><?php endif; ?>
    </div>
    <div class="field">
      <label for="full_name">Họ tên</label>
      <input class="inp<?= isset($errors['full_name']) ? ' bad' : '' ?>" id="full_name" name="full_name" value="<?= e($old['full_name']) ?>">
      <?php if (isset($errors['full_name'])): ?><div class="msg"><?= e($errors['full_name']) ?></div><?php endif; ?>
    </div>
    <div class="field">
      <label for="email">Email (không bắt buộc)</label>
      <input class="inp<?= isset($errors['email']) ? ' bad' : '' ?>" id="email" name="email" value="<?= e($old['email']) ?>">
      <?php if (isset($errors['email'])): ?><div class="msg"><?= e($errors['email']) ?></div><?php endif; ?>
    </div>
    <div class="field">
      <label for="password">Mật khẩu (tối thiểu 6 ký tự)</label>
      <input class="inp<?= isset($errors['password']) ? ' bad' : '' ?>" id="password" name="password" type="password">
      <?php if (isset($errors['password'])): ?><div class="msg"><?= e($errors['password']) ?></div><?php endif; ?>
    </div>
    <div class="field">
      <label for="confirm">Nhập lại mật khẩu</label>
      <input class="inp<?= isset($errors['confirm']) ? ' bad' : '' ?>" id="confirm" name="confirm" type="password">
      <?php if (isset($errors['confirm'])): ?><div class="msg"><?= e($errors['confirm']) ?></div><?php endif; ?>
    </div>
    <button class="btn" type="submit">Đăng ký</button>
  </form>
  <p class="hint">Đã có tài khoản? <a href="login.php">Đăng nhập</a></p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
