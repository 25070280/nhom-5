# Quản lý thu chi cá nhân

Đồ án CNTT - Dự án 05: **Quản lý thu chi cá nhân** (mức cơ bản, vai trò người dùng cá nhân).
Ứng dụng web viết bằng **PHP + MySQL**, chạy trên **XAMPP**.

## Thành viên nhóm

| Họ tên | MSSV | Phụ trách |
|---|---|---|
| Trần Thị Thùy Dương | 25071120 | FR01, Tổng quan, FR05, FR06 |
| Doãn Thị Thu | 25070280 |  FR02, FR03, FR04 |

## Chức năng

| Mã | Chức năng | Trang |
|---|---|---|
| FR01 | Đăng ký, đăng nhập, đăng xuất, xem và sửa hồ sơ, đổi mật khẩu | `login.php`, `register.php`, `logout.php`, `profile.php` |
| FR02 | Thêm, sửa, xóa giao dịch thu / chi | `transactions.php` |
| FR03 | Quản lý danh mục thu / chi | `categories.php` |
| FR04 | Lọc giao dịch theo thời gian, loại, danh mục và tìm theo ghi chú | `transactions.php` (thanh lọc) |
| FR05 | Đặt hạn mức chi theo tháng cho từng danh mục, cảnh báo khi vượt | `budgets.php` (cảnh báo cũng hiện ở `dashboard.php` và `transactions.php`) |
| FR06 | Thống kê tổng thu, tổng chi, số dư; biểu đồ theo tháng và theo danh mục | `reports.php` |
| - | Tổng quan tháng (số liệu, cảnh báo hạn mức, giao dịch gần nhất) | `dashboard.php` |

### Quy tắc nghiệp vụ đã áp dụng
- **BR01**: số tiền giao dịch và hạn mức phải là số nguyên lớn hơn 0 (VND).
- **BR02**: số dư = tổng thu − tổng chi.
- **BR03**: mỗi người chỉ xem và sửa được dữ liệu của chính mình (mọi truy vấn đều lọc theo `user_id` lấy từ phiên đăng nhập).
- **BR04**: mỗi danh mục thuộc đúng một loại (Thu hoặc Chi); danh mục đã có giao dịch hoặc hạn mức thì không đổi loại, không xóa.
- **BR05**: khi chi vượt hạn mức tháng của danh mục thì hiện cảnh báo (cảnh báo sớm khi đạt từ 80%).

## Công nghệ

- Ngôn ngữ: PHP 8 (kết nối CSDL bằng PDO, truy vấn có tham số - prepared statements).
- Cơ sở dữ liệu: MySQL / MariaDB (đi kèm XAMPP), gồm 4 bảng `users`, `categories`, `transactions`, `budgets` với khóa chính, khóa ngoại và ràng buộc kiểm tra.
- Giao diện: HTML + CSS thuần (`assets/style.css`), biểu đồ vẽ bằng SVG do PHP sinh ra (không cần thư viện ngoài, chạy được khi không có mạng).
- Bảo mật: mật khẩu băm bằng `password_hash`, kiểm tra bằng `password_verify`; chống SQL Injection (prepared statements) và XSS (`htmlspecialchars`); chặn truy cập trang khi chưa đăng nhập.
- Công cụ: XAMPP, phpMyAdmin, VS Code, Git / GitHub, Figma (phác thảo giao diện).

## Cấu trúc thư mục

```
expense-manager/
├── index.php                  Trang gốc: chuyển tới Tổng quan hoặc Đăng nhập
├── assets/
│   └── style.css              Giao diện chung
├── config/
│   ├── config.sample.php      Mẫu cấu hình (có trong Git)
│   ├── config.local.php       Cấu hình riêng từng máy (KHÔNG đưa lên Git)
│   └── db.php                 Kết nối MySQL, đọc cấu hình từ config.local.php
├── database/
│   ├── schema.sql             Tạo CSDL và 4 bảng
│   └── seed.sql               Dữ liệu mẫu
├── includes/
│   ├── auth.php               Bật phiên đăng nhập, kết nối CSDL, hàm require_login()
│   ├── header.php             Phần đầu chung và menu trái
│   └── footer.php             Phần cuối chung
├── pages/                     Các màn hình của ứng dụng
└── docs/
    ├── QUY_UOC_CSDL.md        Quy ước tên bảng, cột và khuôn viết trang
    └── 04_TestCases.xlsx      Bảng test case
```

## Hướng dẫn cài đặt và chạy (Windows + XAMPP)

### 1. Chuẩn bị
- Cài [XAMPP](https://www.apachefriends.org/) (có PHP 8 trở lên).
- Mở **XAMPP Control Panel**, bấm **Start** cho **Apache** và **MySQL** (chữ chuyển sang xanh).
- Nếu MySQL không bật được do trùng cổng 3306 với một MySQL khác trên máy, hãy tắt dịch vụ MySQL đó (services.msc) rồi bật lại MySQL của XAMPP.

### 2. Lấy mã nguồn
Đặt toàn bộ project vào `C:\xampp\htdocs\expense-manager`:
```
cd C:\xampp\htdocs
git clone https://github.com/25070280/nhom-5.git expense-manager
```
(hoặc tải ZIP từ GitHub rồi giải nén vào thư mục trên).

### 3. Tạo cơ sở dữ liệu
1. Mở `http://localhost/phpmyadmin`.
2. Chọn tab **Nhập (Import)** → **Chọn tệp** → `database/schema.sql` → kéo xuống bấm **Nhập**.
3. Làm tương tự với `database/seed.sql` để có dữ liệu mẫu.

Lưu ý: chạy lại `schema.sql` sẽ **xóa toàn bộ dữ liệu cũ** của CSDL `expense_manager`.

### 4. Cấu hình kết nối
1. Trong thư mục `config`, chép `config.sample.php` thành `config.local.php`.
2. Mở `config.local.php` và sửa cho đúng máy của bạn. Với XAMPP mặc định:
```php
<?php
return [
    'host'   => '127.0.0.1',
    'dbname' => 'expense_manager',
    'user'   => 'root',
    'pass'   => '',
    // 'port' => 3307,   // chỉ bỏ dấu // và sửa nếu MySQL không chạy ở cổng 3306
];
```
File `config.local.php` đã được `.gitignore` chặn nên **không** bị đưa lên GitHub.

### 5. Chạy thử
- Kiểm tra kết nối: `http://localhost/expense-manager/pages/test_db.php` phải hiện "Kết nối thành công".
- Mở ứng dụng: **`http://localhost/expense-manager/`** (gõ vào thanh địa chỉ của trình duyệt).

## Tài khoản dùng thử

| Tên đăng nhập | Mật khẩu | Ghi chú |
|---|---|---|
| `demo` | `123456` | Có 12 giao dịch (tháng 10 và 11/2026), 6 danh mục, 3 hạn mức. Tháng 11/2026 danh mục Ăn uống đang vượt hạn mức để xem cảnh báo. |
| `demo2` | `123456` | Ít dữ liệu, dùng để kiểm tra mỗi người chỉ thấy dữ liệu của mình. |

Dữ liệu mẫu nằm ở tháng 10 và 11/2026. Để xem số liệu, mở `Tổng quan` với `?m=2026-11` hoặc `Thống kê` với năm 2026.

## Mô hình dữ liệu (tóm tắt)

| Bảng | Cột chính |
|---|---|
| `users` | `id`, `username` (duy nhất), `password_hash`, `full_name`, `email`, `created_at` |
| `categories` | `id`, `user_id` (khóa ngoại), `name`, `type` (`THU` hoặc `CHI`) |
| `transactions` | `id`, `user_id`, `category_id` (khóa ngoại), `amount` (> 0), `trans_date`, `note`, `created_at` |
| `budgets` | `id`, `user_id`, `category_id`, `year`, `month`, `limit_amount` (duy nhất theo người dùng, danh mục, năm, tháng) |

Chi tiết quy ước đặt tên xem `docs/QUY_UOC_CSDL.md`.

## Kiểm thử

Bảng test case ở `docs/04_TestCases.xlsx` (87 test case cho FR01 đến FR06, quy tắc nghiệp vụ và bảo mật). Nạp lại `seed.sql` trước khi test để số liệu khớp với kết quả mong đợi.

## Làm việc nhóm với Git

- Nhánh chính: `main`. Mỗi thành viên commit bằng tài khoản GitHub của mình.
- Trước khi làm: `git pull --no-rebase`. Làm xong: `git add .`, `git commit -m "..."`, `git pull --no-rebase`, `git push`.
- Quy ước commit: `feat(FR02): ...` (chức năng mới), `fix: ...` (sửa lỗi), `docs: ...` (tài liệu).
- Không sửa `config/db.php`, `includes/*`, `assets/style.css` khi chưa báo cả nhóm (file dùng chung).

## Hạn chế hiện tại

- Chưa có chức năng quên mật khẩu qua email.
- Chưa có mã chống giả mạo biểu mẫu (CSRF token); bù lại, thao tác xóa đều dùng phương thức POST và kiểm tra quyền sở hữu dữ liệu.
- Phiên bản cơ bản, chỉ có vai trò người dùng cá nhân (chưa có quản trị viên).
