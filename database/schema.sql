-- ============================================================
-- schema.sql: tạo cơ sở dữ liệu expense_manager và 4 bảng
-- Dự án 05: Quản lý thu chi cá nhân
--
-- CẢNH BÁO: chạy lại file này sẽ XÓA SẠCH dữ liệu cũ của 4 bảng
-- (dùng khi muốn làm lại từ đầu trong lúc phát triển).
-- ============================================================

SET NAMES utf8mb4;

CREATE DATABASE IF NOT EXISTS expense_manager
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE expense_manager;

-- Xóa bảng theo thứ tự ngược lại với quan hệ khóa ngoại
DROP TABLE IF EXISTS budgets;
DROP TABLE IF EXISTS transactions;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

-- Bảng người dùng (FR01)
CREATE TABLE users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,      -- mật khẩu đã băm, không lưu mật khẩu thường
  full_name VARCHAR(100),
  email VARCHAR(100),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Bảng danh mục thu/chi (FR03): mỗi danh mục thuộc đúng một loại THU hoặc CHI (BR04)
CREATE TABLE categories (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  name VARCHAR(100) NOT NULL,
  type ENUM('THU','CHI') NOT NULL,
  UNIQUE (user_id, name),                   -- không trùng tên trong cùng một người dùng
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Bảng giao dịch (FR02): số tiền phải lớn hơn 0 (BR01)
CREATE TABLE transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  category_id INT NOT NULL,
  amount BIGINT NOT NULL CHECK (amount > 0),
  trans_date DATE NOT NULL,
  note VARCHAR(255),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

-- Bảng hạn mức chi (FR05): mỗi người dùng chỉ có một hạn mức cho mỗi danh mục trong mỗi tháng (BR05)
CREATE TABLE budgets (
  id INT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  category_id INT NOT NULL,
  year INT NOT NULL,
  month INT NOT NULL CHECK (month BETWEEN 1 AND 12),
  limit_amount BIGINT NOT NULL CHECK (limit_amount > 0),
  UNIQUE (user_id, category_id, year, month),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;
