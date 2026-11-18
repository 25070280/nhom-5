# Quy ước CSDL chung của nhóm (BẮT BUỘC đọc trước khi viết code)

CSDL duy nhất của nhóm: **expense_manager**, tạo bằng `database/schema.sql`, dữ liệu mẫu `database/seed.sql`.
KHÔNG dùng `database.sql` / `cofig.php` cũ ở thư mục ngoài cùng.

## Tên bảng và cột
| Bảng | Cột |
|---|---|
| users | id, username, password_hash, full_name, email, created_at |
| categories | id, user_id, name, type (`THU` hoặc `CHI`) |
| transactions | id, user_id, category_id, amount (số nguyên > 0), trans_date, note, created_at |
| budgets | id, user_id, category_id, year, month, limit_amount |

Khóa chính của MỌI bảng tên là `id`. Loại danh mục chỉ có hai giá trị `THU` / `CHI` (không dùng INCOME/EXPENSE).

## Khuôn của một trang
```php
<?php
$pageTitle  = 'Tên trang';
$activePage = 'transactions';          // dashboard | transactions | categories | budgets | reports | profile
require __DIR__ . '/../includes/auth.php';
require_login();
$uid = current_user_id();              // mọi truy vấn phải có "WHERE user_id = ?" với $uid
// ... xử lý, mọi redirect (header('Location: ...')) phải đặt TRƯỚC dòng require header.php
require __DIR__ . '/../includes/header.php';
?>
... HTML ...
<?php require __DIR__ . '/../includes/footer.php'; ?>
```

## Quy tắc làm việc chung
1. KHÔNG sửa `config/db.php`, `includes/*`, `assets/style.css` nếu chưa báo cả nhóm (file dùng chung).
2. KHÔNG gọi `session_start()` trong trang (auth.php đã gọi).
3. Luôn `git pull --no-rebase` trước khi làm và trước khi push.
4. Mỗi người sửa file của mình: FR02/FR04 -> transactions.php, FR03 -> categories.php, FR05 -> budgets.php, FR06 -> reports.php.
5. In dữ liệu ra HTML bằng `e($x)`; truy vấn luôn dùng `prepare` + `execute([...])`.
