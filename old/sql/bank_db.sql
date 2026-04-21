-- ================================================
-- BANK MANAGEMENT SYSTEM - DATABASE SCHEMA v2
-- ================================================

CREATE DATABASE IF NOT EXISTS bank_management;
USE bank_management;

-- USERS TABLE (Admin, Staff & Customer portal logins)
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'staff', 'customer') DEFAULT 'customer',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- CUSTOMERS TABLE
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id VARCHAR(20) UNIQUE NOT NULL,
    user_id INT DEFAULT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20) NOT NULL,
    address TEXT,
    dob DATE,
    gender ENUM('male', 'female', 'other'),
    id_type VARCHAR(50),
    id_number VARCHAR(50),
    status ENUM('active', 'inactive', 'blocked') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- ACCOUNTS TABLE
CREATE TABLE IF NOT EXISTS accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    account_number VARCHAR(20) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    account_type ENUM('savings', 'current', 'fixed_deposit', 'loan') NOT NULL,
    balance DECIMAL(15,2) DEFAULT 0.00,
    interest_rate DECIMAL(5,2) DEFAULT 0.00,
    status ENUM('active', 'inactive', 'frozen', 'closed') DEFAULT 'active',
    opened_date DATE NOT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id) ON DELETE CASCADE
);

-- TRANSACTIONS TABLE
CREATE TABLE IF NOT EXISTS transactions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id VARCHAR(50) UNIQUE NOT NULL,
    account_id INT NOT NULL,
    type ENUM('deposit', 'withdrawal', 'transfer_in', 'transfer_out', 'loan_disbursement', 'loan_repayment') NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    balance_after DECIMAL(15,2) NOT NULL,
    description TEXT,
    reference_account VARCHAR(20),
    performed_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (account_id) REFERENCES accounts(id),
    FOREIGN KEY (performed_by) REFERENCES users(id)
);

-- LOANS TABLE
CREATE TABLE IF NOT EXISTS loans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id VARCHAR(20) UNIQUE NOT NULL,
    customer_id INT NOT NULL,
    account_id INT NOT NULL,
    loan_type ENUM('personal', 'home', 'car', 'business', 'education') NOT NULL,
    principal_amount DECIMAL(15,2) NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    tenure_months INT NOT NULL,
    monthly_payment DECIMAL(15,2),
    amount_paid DECIMAL(15,2) DEFAULT 0.00,
    amount_remaining DECIMAL(15,2),
    status ENUM('pending', 'approved', 'active', 'closed', 'rejected') DEFAULT 'pending',
    applied_date DATE,
    approved_date DATE,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (account_id) REFERENCES accounts(id)
);

-- ================================================
-- SEED DATA  (password for all: password)
-- ================================================
INSERT INTO users (full_name, email, password, role) VALUES
('Super Admin',  'admin@apexbank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
('John Staff',   'staff@apexbank.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'staff'),
('Alice Johnson','alice@email.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer'),
('Bob Rahman',   'bob@email.com',      '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer'),
('Carol Islam',  'carol@email.com',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'customer');

INSERT INTO customers (customer_id, user_id, full_name, email, phone, address, dob, gender, id_type, id_number) VALUES
('CUS-001', 3, 'Alice Johnson', 'alice@email.com', '01711-123456', 'Dhaka, Bangladesh',     '1990-05-15', 'female', 'NID',      'NID-111222333'),
('CUS-002', 4, 'Bob Rahman',    'bob@email.com',   '01812-654321', 'Chittagong, Bangladesh', '1985-08-20', 'male',   'Passport', 'BD-456789'),
('CUS-003', 5, 'Carol Islam',   'carol@email.com', '01913-987654', 'Sylhet, Bangladesh',     '1992-12-10', 'female', 'NID',      'NID-444555666');

INSERT INTO accounts (account_number, customer_id, account_type, balance, interest_rate, opened_date) VALUES
('ACC-0001001', 1, 'savings',       150000.00, 5.50, '2023-01-10'),
('ACC-0001002', 1, 'current',        75000.00, 0.00, '2023-03-15'),
('ACC-0002001', 2, 'savings',       320000.00, 5.50, '2022-06-01'),
('ACC-0003001', 3, 'fixed_deposit', 500000.00, 9.00, '2024-01-01');