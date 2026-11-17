<?php
// header.php: phần ĐẦU chung của mọi trang (menu bên trái theo phác thảo).
// Trước khi include file này, mỗi trang đặt:
//   $pageTitle  = 'Tên trang';      // hiện trên thẻ trình duyệt
//   $activePage = 'dashboard';      // mục menu cần tô đậm
//   $showMenu   = false;            // CHỈ đặt khi không muốn menu (trang đăng nhập, đăng ký)
$pageTitle  = $pageTitle  ?? 'Quản lý thu chi';
$activePage = $activePage ?? '';
$showMenu   = $showMenu   ?? true;

// Danh sách mục menu: 'mã' => ['chữ hiển thị', 'file trang']
$menu = [
    'dashboard'    => ['Tổng quan', 'dashboard.php'],
    'transactions' => ['Giao dịch', 'transactions.php'],
    'categories'   => ['Danh mục',  'categories.php'],
    'budgets'      => ['Hạn mức',   'budgets.php'],
    'reports'      => ['Thống kê',  'reports.php'],
    'profile'      => ['Hồ sơ',     'profile.php'],
];
?>
<!doctype html>
<html lang="vi">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle) ?> | Thu chi cá nhân</title>
<link rel="stylesheet" href="../assets/style.css">
</head>
<body>
<?php if ($showMenu): ?>
<div class="shell">
  <nav class="side" aria-label="Menu chính">
    <div class="brand">Thu chi cá nhân</div>
    <?php foreach ($menu as $key => [$label, $file]): ?>
      <a class="nav<?= $key === $activePage ? ' on' : '' ?>" href="<?= $file ?>"><?= $label ?></a>
    <?php endforeach; ?>
    <?php if (!empty($_SESSION['user_id'])): ?>
      <div class="who">
        <div>Xin chào, <strong><?= htmlspecialchars($_SESSION['user_name'] ?? '') ?></strong></div>
        <a href="logout.php">Đăng xuất</a>
      </div>
    <?php endif; ?>
  </nav>
  <main class="main">
<?php else: ?>
<main class="solo">
<?php endif; ?>
