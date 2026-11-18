<?php
// reports.php: FR06 - thống kê tổng thu, tổng chi, số dư và biểu đồ theo tháng, theo danh mục.
// Biểu đồ được VẼ BẰNG PHP dưới dạng SVG (hình vector), không cần thư viện ngoài nên chạy được cả khi không có mạng.
// Chọn kỳ xem: reports.php?y=2026&m=0&t=CHI  (m=0 là cả năm, m=1..12 là từng tháng, t=CHI hoặc THU cho biểu đồ danh mục)
$pageTitle  = 'Thống kê';
$activePage = 'reports';
require __DIR__ . '/../includes/auth.php';
require_login();
$uid = current_user_id();            // chỉ thống kê dữ liệu của người đang đăng nhập (BR03)

function vnd($n) { return number_format((int)$n, 0, ',', '.'); }
// Rút gọn số trên trục: 8000000 -> "8 tr", 500000 -> "500 N"
function short_vnd($n) {
    if ($n >= 1000000) { $v = $n / 1000000; return rtrim(rtrim(number_format($v, 1, ',', ''), '0'), ',') . ' tr'; }
    if ($n >= 1000)    { return rtrim(rtrim(number_format($n / 1000, 1, ',', ''), '0'), ',') . ' N'; }
    return (string)(int)$n;
}

// ----- 1. Đọc bộ lọc (kiểm tra giá trị để tránh nhập bậy trên địa chỉ) -----
$y = (int)($_GET['y'] ?? date('Y'));
if ($y < 2000 || $y > 2100) { $y = (int)date('Y'); }
$mo = (int)($_GET['m'] ?? 0);
if ($mo < 0 || $mo > 12) { $mo = 0; }
$catType = (($_GET['t'] ?? 'CHI') === 'THU') ? 'THU' : 'CHI';

if ($mo === 0) { $from = sprintf('%04d-01-01', $y); $to = sprintf('%04d-12-31', $y); }
else           { $from = sprintf('%04d-%02d-01', $y, $mo); $to = date('Y-m-t', strtotime($from)); }
$periodLabel = $mo === 0 ? "năm $y" : sprintf('tháng %02d/%04d', $mo, $y);

// ----- 2. Các năm có dữ liệu (cho ô chọn năm) -----
$st = $pdo->prepare('SELECT DISTINCT SUBSTR(trans_date, 1, 4) AS yr FROM transactions WHERE user_id = ? ORDER BY yr DESC');
$st->execute([$uid]);
$years = array_map('intval', array_column($st->fetchAll(), 'yr'));
if (!in_array((int)date('Y'), $years, true)) { $years[] = (int)date('Y'); }
if (!in_array($y, $years, true)) { $years[] = $y; }
rsort($years);

// ----- 3. Tổng thu, tổng chi, số dư của kỳ đang xem (BR02: số dư = thu - chi) -----
$st = $pdo->prepare(
    "SELECT c.type, COALESCE(SUM(t.amount), 0) AS total
     FROM transactions t JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ? AND t.trans_date BETWEEN ? AND ?
     GROUP BY c.type"
);
$st->execute([$uid, $from, $to]);
$totalIn = 0; $totalOut = 0;
foreach ($st->fetchAll() as $r) {
    if ($r['type'] === 'THU') { $totalIn = (int)$r['total']; } else { $totalOut = (int)$r['total']; }
}
$balance = $totalIn - $totalOut;

// ----- 4. Thu, chi theo từng tháng trong năm (biểu đồ cột) -----
$st = $pdo->prepare(
    "SELECT SUBSTR(t.trans_date, 6, 2) AS mm, c.type, SUM(t.amount) AS total
     FROM transactions t JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ? AND t.trans_date BETWEEN ? AND ?
     GROUP BY mm, c.type"
);
$st->execute([$uid, sprintf('%04d-01-01', $y), sprintf('%04d-12-31', $y)]);
$mIn = array_fill(1, 12, 0); $mOut = array_fill(1, 12, 0);
foreach ($st->fetchAll() as $r) {
    $i = (int)$r['mm'];
    if ($r['type'] === 'THU') { $mIn[$i] = (int)$r['total']; } else { $mOut[$i] = (int)$r['total']; }
}

// ----- 5. Cơ cấu theo danh mục của kỳ đang xem (biểu đồ tròn) -----
$st = $pdo->prepare(
    "SELECT c.name, SUM(t.amount) AS total
     FROM transactions t JOIN categories c ON c.id = t.category_id
     WHERE t.user_id = ? AND c.type = ? AND t.trans_date BETWEEN ? AND ?
     GROUP BY c.id, c.name
     ORDER BY total DESC"
);
$st->execute([$uid, $catType, $from, $to]);
$cats = $st->fetchAll();
$catSum = array_sum(array_map(fn($r) => (int)$r['total'], $cats));
// Gộp các danh mục nhỏ vào "Khác" nếu quá 7 danh mục để biểu đồ dễ đọc
if (count($cats) > 7) {
    $top = array_slice($cats, 0, 7);
    $rest = array_sum(array_map(fn($r) => (int)$r['total'], array_slice($cats, 7)));
    $top[] = ['name' => 'Khác', 'total' => $rest];
    $cats = $top;
}
$palette = ['#2563eb', '#dc2626', '#16a34a', '#d97706', '#7c3aed', '#0891b2', '#db2777', '#6b7280'];

// ----- 6. Tính các thông số vẽ biểu đồ cột -----
$maxVal = max(1, max($mIn), max($mOut));
$exp  = pow(10, floor(log10($maxVal)));
$step = $exp;
foreach ([1, 2, 2.5, 5, 10] as $k) { if ($maxVal / ($k * $exp) <= 5) { $step = $k * $exp; break; } }
$top = (int)(ceil($maxVal / $step) * $step);
$W = 720; $H = 300; $L = 52; $R = 8; $T = 12; $B = 30;
$plotW = $W - $L - $R; $plotH = $H - $T - $B;
$yOf = fn($v) => $T + $plotH - ($v / $top) * $plotH;
$groupW = $plotW / 12;

require __DIR__ . '/../includes/header.php';
?>
<div class="top"><h1>Thống kê thu chi</h1></div>

<!-- Bộ lọc kỳ xem -->
<div class="card">
  <form method="get" action="reports.php" style="display:flex;flex-wrap:wrap;gap:12px;align-items:flex-end;">
    <div class="field"><label for="y">Năm</label>
      <select class="inp" id="y" name="y">
        <?php foreach ($years as $yr): ?><option value="<?= $yr ?>" <?= $yr === $y ? 'selected' : '' ?>><?= $yr ?></option><?php endforeach; ?>
      </select></div>
    <div class="field"><label for="m">Tháng</label>
      <select class="inp" id="m" name="m">
        <option value="0" <?= $mo === 0 ? 'selected' : '' ?>>Cả năm</option>
        <?php for ($i = 1; $i <= 12; $i++): ?><option value="<?= $i ?>" <?= $mo === $i ? 'selected' : '' ?>>Tháng <?= $i ?></option><?php endfor; ?>
      </select></div>
    <div class="field"><label for="t">Biểu đồ danh mục</label>
      <select class="inp" id="t" name="t">
        <option value="CHI" <?= $catType === 'CHI' ? 'selected' : '' ?>>Khoản Chi</option>
        <option value="THU" <?= $catType === 'THU' ? 'selected' : '' ?>>Khoản Thu</option>
      </select></div>
    <button class="btn" type="submit">Xem thống kê</button>
  </form>
</div>

<div class="stats">
  <div class="card"><div class="lbl">Tổng thu (<?= e($periodLabel) ?>)</div><div class="big" style="color:#16a34a;"><?= vnd($totalIn) ?></div></div>
  <div class="card"><div class="lbl">Tổng chi (<?= e($periodLabel) ?>)</div><div class="big" style="color:#dc2626;"><?= vnd($totalOut) ?></div></div>
  <div class="card"><div class="lbl">Số dư (thu − chi)</div><div class="big"><?= ($balance < 0 ? '−' : '') . vnd(abs($balance)) ?></div></div>
</div>

<!-- Biểu đồ thu chi theo tháng -->
<div class="card">
  <h2>Thu và chi theo tháng, năm <?= $y ?></h2>
  <div style="display:flex;gap:16px;font-size:13px;margin:8px 0;">
    <span><span style="display:inline-block;width:12px;height:12px;background:#16a34a;border-radius:2px;vertical-align:-1px;"></span> Thu</span>
    <span><span style="display:inline-block;width:12px;height:12px;background:#dc2626;border-radius:2px;vertical-align:-1px;"></span> Chi</span>
    <span class="hint">(đơn vị: tr = triệu, N = nghìn đồng)</span>
  </div>
  <svg viewBox="0 0 <?= $W ?> <?= $H ?>" style="width:100%;height:auto;max-width:100%;" role="img"
       aria-label="Biểu đồ cột thu chi theo từng tháng năm <?= $y ?>">
    <?php for ($v = 0; $v <= $top; $v += $step): $yy = $yOf($v); ?>
      <line x1="<?= $L ?>" x2="<?= $W - $R ?>" y1="<?= $yy ?>" y2="<?= $yy ?>" stroke="#e5e7eb" stroke-width="1"/>
      <text x="<?= $L - 6 ?>" y="<?= $yy + 4 ?>" text-anchor="end" font-size="11" fill="#4b5563"><?= e(short_vnd($v)) ?></text>
    <?php endfor; ?>
    <?php for ($i = 1; $i <= 12; $i++):
        $gx = $L + ($i - 1) * $groupW; $bw = min(20, $groupW / 2 - 3);
        $cx = $gx + $groupW / 2; ?>
      <?php if ($mIn[$i] > 0): $h = $plotH - ($yOf($mIn[$i]) - $T); ?>
        <rect x="<?= $cx - $bw - 1 ?>" y="<?= $yOf($mIn[$i]) ?>" width="<?= $bw ?>" height="<?= $h ?>" fill="#16a34a" rx="2">
          <title>Tháng <?= $i ?> - Thu: <?= vnd($mIn[$i]) ?> VND</title></rect>
      <?php endif; ?>
      <?php if ($mOut[$i] > 0): $h = $plotH - ($yOf($mOut[$i]) - $T); ?>
        <rect x="<?= $cx + 1 ?>" y="<?= $yOf($mOut[$i]) ?>" width="<?= $bw ?>" height="<?= $h ?>" fill="#dc2626" rx="2">
          <title>Tháng <?= $i ?> - Chi: <?= vnd($mOut[$i]) ?> VND</title></rect>
      <?php endif; ?>
      <text x="<?= $cx ?>" y="<?= $H - 10 ?>" text-anchor="middle" font-size="11" fill="#4b5563">T<?= $i ?></text>
    <?php endfor; ?>
  </svg>
</div>

<!-- Bảng chi tiết theo tháng -->
<div class="tablebox">
  <table class="tb">
    <thead><tr><th>Tháng</th><th class="r">Tổng thu</th><th class="r">Tổng chi</th><th class="r">Số dư</th></tr></thead>
    <tbody>
    <?php for ($i = 1; $i <= 12; $i++): if ($mIn[$i] === 0 && $mOut[$i] === 0) continue; $b = $mIn[$i] - $mOut[$i]; ?>
      <tr><td>Tháng <?= $i ?></td><td class="r"><?= vnd($mIn[$i]) ?></td><td class="r"><?= vnd($mOut[$i]) ?></td>
          <td class="r"><?= ($b < 0 ? '−' : '') . vnd(abs($b)) ?></td></tr>
    <?php endfor; ?>
    <?php if (array_sum($mIn) + array_sum($mOut) === 0): ?>
      <tr><td colspan="4" class="hint" style="text-align:center;padding:20px;">Năm <?= $y ?> chưa có giao dịch.</td></tr>
    <?php else: $yb = array_sum($mIn) - array_sum($mOut); ?>
      <tr style="font-weight:700;"><td>Cả năm <?= $y ?></td><td class="r"><?= vnd(array_sum($mIn)) ?></td><td class="r"><?= vnd(array_sum($mOut)) ?></td>
          <td class="r"><?= ($yb < 0 ? '−' : '') . vnd(abs($yb)) ?></td></tr>
    <?php endif; ?>
    </tbody>
  </table>
</div>

<!-- Biểu đồ tròn theo danh mục -->
<div class="card">
  <h2>Cơ cấu <?= $catType === 'CHI' ? 'chi tiêu' : 'thu nhập' ?> theo danh mục, <?= e($periodLabel) ?></h2>
  <?php if ($catSum === 0): ?>
    <p class="hint" style="margin-top:12px;">Kỳ này chưa có khoản <?= $catType === 'CHI' ? 'chi' : 'thu' ?> nào.</p>
  <?php else: ?>
  <div style="display:flex;flex-wrap:wrap;gap:28px;align-items:center;margin-top:12px;">
    <svg viewBox="0 0 42 42" style="width:220px;height:220px;flex:0 0 auto;" role="img"
         aria-label="Biểu đồ tròn cơ cấu theo danh mục">
      <circle cx="21" cy="21" r="15.91549" fill="none" stroke="#e5e7eb" stroke-width="6"/>
      <?php $acc = 0; foreach ($cats as $i => $r):
          $pct = $r['total'] / $catSum * 100; ?>
        <circle cx="21" cy="21" r="15.91549" fill="none" stroke="<?= $palette[$i % count($palette)] ?>" stroke-width="6"
                stroke-dasharray="<?= round($pct, 3) ?> <?= round(100 - $pct, 3) ?>" stroke-dashoffset="<?= round(25 - $acc, 3) ?>">
          <title><?= e($r['name']) ?>: <?= vnd($r['total']) ?> VND (<?= round($pct, 1) ?>%)</title></circle>
      <?php $acc += $pct; endforeach; ?>
      <text x="21" y="20" text-anchor="middle" font-size="3" fill="#4b5563">Tổng</text>
      <text x="21" y="25" text-anchor="middle" font-size="4" font-weight="700" fill="#222"><?= e(short_vnd($catSum)) ?></text>
    </svg>
    <div class="tablebox" style="flex:1;min-width:280px;">
      <table class="tb" style="min-width:0;">
        <thead><tr><th>Danh mục</th><th class="r">Số tiền</th><th class="r">Tỷ lệ</th></tr></thead>
        <tbody>
        <?php foreach ($cats as $i => $r): ?>
          <tr>
            <td><span style="display:inline-block;width:12px;height:12px;border-radius:2px;background:<?= $palette[$i % count($palette)] ?>;vertical-align:-1px;margin-right:8px;"></span><?= e($r['name']) ?></td>
            <td class="r"><?= vnd($r['total']) ?></td>
            <td class="r"><?= number_format($r['total'] / $catSum * 100, 1, ',', '') ?>%</td>
          </tr>
        <?php endforeach; ?>
          <tr style="font-weight:700;"><td>Tổng</td><td class="r"><?= vnd($catSum) ?></td><td class="r">100%</td></tr>
        </tbody>
      </table>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
