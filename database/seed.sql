-- ============================================================
-- seed.sql: dữ liệu mẫu để demo
-- Chạy SAU schema.sql. Chạy lại file này nhiều lần sẽ báo lỗi trùng dữ liệu;
-- muốn làm lại thì chạy schema.sql trước (nó xóa sạch rồi tạo lại bảng).
--
-- Tài khoản mẫu:  demo  /  123456     (có dữ liệu thu chi tháng 10 và 11/2026)
--                 demo2 /  123456     (dùng để kiểm tra mỗi người chỉ thấy dữ liệu của mình)
-- Mật khẩu 123456 đã được băm bằng password_hash() của PHP (không lưu dạng văn bản).
-- ============================================================

SET NAMES utf8mb4;
USE expense_manager;

INSERT INTO users (id, username, password_hash, full_name, email) VALUES
(1, 'demo',  '$2y$10$94tUpgtuHv70Q5I4Hi.sB.kD37S3ioO0OmfbFf5yaZi4ykPz6fTNa', 'Nguyễn Văn A', 'demo@example.com'),
(2, 'demo2', '$2y$10$94tUpgtuHv70Q5I4Hi.sB.kD37S3ioO0OmfbFf5yaZi4ykPz6fTNa', 'Trần Thị B',   'demo2@example.com');

INSERT INTO categories (id, user_id, name, type) VALUES
(1, 1, 'Lương',      'THU'),
(2, 1, 'Thưởng',     'THU'),
(3, 1, 'Ăn uống',    'CHI'),
(4, 1, 'Di chuyển',  'CHI'),
(5, 1, 'Giải trí',   'CHI'),
(6, 1, 'Hóa đơn',    'CHI'),
(7, 2, 'Lương',      'THU'),
(8, 2, 'Ăn uống',    'CHI');

INSERT INTO transactions (user_id, category_id, amount, trans_date, note) VALUES
-- Tháng 10/2026 của demo
(1, 1, 8000000, '2026-10-05', 'Lương tháng 10'),
(1, 3,   45000, '2026-10-08', 'Cơm trưa'),
(1, 4,   80000, '2026-10-12', 'Đổ xăng'),
(1, 5,  120000, '2026-10-20', 'Xem phim'),
(1, 6,  350000, '2026-10-25', 'Tiền điện'),
-- Tháng 11/2026 của demo (Ăn uống chi 870.000, vượt hạn mức 800.000 để thử cảnh báo)
(1, 1, 8000000, '2026-11-01', 'Lương tháng 11'),
(1, 2, 1000000, '2026-11-02', 'Thưởng dự án'),
(1, 3,   55000, '2026-11-03', 'Ăn sáng'),
(1, 3,  420000, '2026-11-05', 'Ăn tối cùng bạn bè'),
(1, 4,   90000, '2026-11-08', 'Đổ xăng'),
(1, 3,  395000, '2026-11-10', 'Đi siêu thị'),
(1, 5,  150000, '2026-11-12', 'Cà phê và xem phim'),
-- Dữ liệu của demo2
(2, 7, 6000000, '2026-11-01', 'Lương'),
(2, 8,   60000, '2026-11-04', 'Ăn trưa');

-- Hạn mức: Ăn uống tháng 10 chưa vượt, tháng 11 đã vượt; Giải trí chưa đặt hạn mức
INSERT INTO budgets (user_id, category_id, year, month, limit_amount) VALUES
(1, 3, 2026, 10,  500000),
(1, 3, 2026, 11,  800000),
(1, 4, 2026, 11,  600000);
