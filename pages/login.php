<?php
// login.php: FR01 - đăng nhập (màn hình 1 trong phác thảo)
require __DIR__ . '/../includes/auth.php';

if (current_user_id()) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Vui lòng nhập đủ tên đăng nhập và mật khẩu.';
    } else {
        $st = $pdo->prepare('SELECT id, username, password_hash, full_name FROM users WHERE username = ?');
        $st->execute([$username]);
        $user = $st->fetch();

        // password_verify so mật khẩu gõ vào với bản đã băm trong CSDL
        if ($user && password_verify($password, $user['password_hash'])) {
            session_regenerate_id(true);               // đổi mã phiên, tránh bị đánh cắp phiên
            $_SESSION['user_id']   = (int)$user['id'];
            $_SESSION['user_name'] = $user['full_name'] ?: $user['username'];
            header('Location: dashboard.php');
            exit;
        }
        // Cố ý chỉ báo chung chung, không cho biết sai tên hay sai mật khẩu
        $error = 'Tên đăng nhập hoặc mật khẩu không đúng.';
    }
}

$pageTitle = 'Đăng nhập';
$showMenu  = false;
require __DIR__ . '/../includes/header.php';
?>
<h1>Thu chi cá nhân</h1>
<div class="card auth">
  <h2>Đăng nhập</h2>
  <?php if (isset($_GET['registered'])): ?><div class="ok">Đăng ký thành công. Mời bạn đăng nhập.</div><?php endif; ?>
  <?php if (isset($_GET['logout'])): ?><div class="ok">Bạn đã đăng xuất.</div><?php endif; ?>
  <?php if ($error): ?><div class="err"><?= e($error) ?></div><?php endif; ?>
  <form method="post" class="form" novalidate>
    <div class="field">
      <label for="username">Tên đăng nhập</label>
      <input class="inp" id="username" name="username" value="<?= e($username) ?>" autofocus>
    </div>
    <div class="field">
      <label for="password">Mật khẩu</label>
      <input class="inp" id="password" name="password" type="password">
    </div>
    <button class="btn" type="submit">Đăng nhập</button>
  </form>
  <p class="hint">Chưa có tài khoản? <a href="register.php">Đăng ký</a></p>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
