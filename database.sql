-- 1. Tạo và chọn sử dụng Cơ sở dữ liệu
CREATE DATABASE IF NOT EXISTS personal_finance_manager
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE personal_finance_manager;

-- 2. Bảng 1: Users (Quản lý thông tin người dùng)
CREATE TABLE Users (
    user_id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 3. Bảng 2: Categories (Quản lý danh mục thu/chi)
CREATE TABLE Categories (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('INCOME', 'EXPENSE') NOT NULL,
    is_default BOOLEAN DEFAULT FALSE,
    CONSTRAINT fk_categories_user 
        FOREIGN KEY (user_id) REFERENCES Users(user_id) 
        ON DELETE CASCADE
);

-- 4. Bảng 3: Transactions (Quản lý giao dịch thu/chi)
CREATE TABLE Transactions (
    transaction_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    amount DECIMAL(15, 2) NOT NULL,
    transaction_date DATE NOT NULL,
    note TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_transaction_amount CHECK (amount > 0),
    CONSTRAINT fk_transactions_user 
        FOREIGN KEY (user_id) REFERENCES Users(user_id) 
        ON DELETE CASCADE,
    CONSTRAINT fk_transactions_category 
        FOREIGN KEY (category_id) REFERENCES Categories(category_id) 
        ON DELETE RESTRICT
);

-- 5. Bảng 4: Budgets (Quản lý hạn mức ngân sách)
CREATE TABLE Budgets (
    budget_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    category_id INT NOT NULL,
    limit_amount DECIMAL(15, 2) NOT NULL,
    month_year VARCHAR(7) NOT NULL,
    CONSTRAINT chk_budget_limit CHECK (limit_amount > 0),
    CONSTRAINT uq_user_category_month UNIQUE (user_id, category_id, month_year),
    CONSTRAINT fk_budgets_user 
        FOREIGN KEY (user_id) REFERENCES Users(user_id) 
        ON DELETE CASCADE,
    CONSTRAINT fk_budgets_category 
        FOREIGN KEY (category_id) REFERENCES Categories(category_id) 
        ON DELETE CASCADE
);
