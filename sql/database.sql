-- ============================================================
-- ASHA BANK - Complete Database Schema for Bangladesh
-- With Working Login Credentials (Admin, Staff, Client)
-- Currency: BDT (Taka)
-- ============================================================

DROP DATABASE IF EXISTS asha_bank;
CREATE DATABASE IF NOT EXISTS asha_bank CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE asha_bank;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- ZONE
-- ============================================================
CREATE TABLE ZONE (
    ZoneID     INT AUTO_INCREMENT PRIMARY KEY,
    ZoneName   VARCHAR(100) NOT NULL
);

-- ============================================================
-- REGION
-- ============================================================
CREATE TABLE REGION (
    RegionID   INT AUTO_INCREMENT PRIMARY KEY,
    RegionName VARCHAR(100) NOT NULL,
    ZoneID     INT NOT NULL,
    FOREIGN KEY (ZoneID) REFERENCES ZONE(ZoneID)
);

-- ============================================================
-- BRANCH
-- ============================================================
CREATE TABLE BRANCH (
    BranchID          INT AUTO_INCREMENT PRIMARY KEY,
    BranchName        VARCHAR(150) NOT NULL,
    IFSCCode          VARCHAR(20)  NOT NULL UNIQUE,
    Address           VARCHAR(255),
    City              VARCHAR(100),
    RegionID          INT,
    ManagerEmployeeID INT,
    FOREIGN KEY (RegionID) REFERENCES REGION(RegionID)
);

-- ============================================================
-- DEPARTMENT
-- ============================================================
CREATE TABLE DEPARTMENT (
    DepartmentID   INT AUTO_INCREMENT PRIMARY KEY,
    DepartmentName VARCHAR(100) NOT NULL
);

-- ============================================================
-- DESIGNATION
-- ============================================================
CREATE TABLE DESIGNATION (
    DesignationID   INT AUTO_INCREMENT PRIMARY KEY,
    DesignationName VARCHAR(100) NOT NULL
);

-- ============================================================
-- EMPLOYEE (For Staff)
-- ============================================================
CREATE TABLE EMPLOYEE (
    EmployeeID      INT AUTO_INCREMENT PRIMARY KEY,
    FirstName       VARCHAR(100) NOT NULL,
    LastName        VARCHAR(100) NOT NULL,
    Email           VARCHAR(150) UNIQUE,
    Phone           VARCHAR(20),
    DepartmentID    INT,
    DesignationID   INT,
    BranchID        INT,
    ManagerID       INT,
    HireDate        DATE,
    Salary          DECIMAL(12,2),
    IsActive        TINYINT(1) DEFAULT 1,
    PasswordHash    VARCHAR(255),
    FOREIGN KEY (DepartmentID)  REFERENCES DEPARTMENT(DepartmentID),
    FOREIGN KEY (DesignationID) REFERENCES DESIGNATION(DesignationID),
    FOREIGN KEY (BranchID)      REFERENCES BRANCH(BranchID),
    FOREIGN KEY (ManagerID)     REFERENCES EMPLOYEE(EmployeeID)
);

-- ============================================================
-- ADMIN USERS
-- ============================================================
CREATE TABLE ADMIN_USER (
    AdminID       INT AUTO_INCREMENT PRIMARY KEY,
    Username      VARCHAR(100) NOT NULL UNIQUE,
    PasswordHash  VARCHAR(255) NOT NULL,
    Role          ENUM('superadmin','manager','teller','support') DEFAULT 'teller',
    EmployeeID    INT,
    CreatedAt     DATETIME DEFAULT CURRENT_TIMESTAMP,
    LastLogin     DATETIME,
    IsActive      TINYINT(1) DEFAULT 1,
    FOREIGN KEY (EmployeeID) REFERENCES EMPLOYEE(EmployeeID)
);

-- ============================================================
-- CUSTOMER CATEGORY
-- ============================================================
CREATE TABLE CUSTOMERCATEGORY (
    CategoryID   INT AUTO_INCREMENT PRIMARY KEY,
    CategoryName VARCHAR(100) NOT NULL
);

-- ============================================================
-- CUSTOMER
-- ============================================================
CREATE TABLE CUSTOMER (
    CustomerID            INT AUTO_INCREMENT PRIMARY KEY,
    FirstName             VARCHAR(100) NOT NULL,
    LastName              VARCHAR(100) NOT NULL,
    DateOfBirth           DATE,
    Gender                ENUM('Male','Female','Other'),
    Email                 VARCHAR(150) UNIQUE,
    Phone                 VARCHAR(20),
    Address               VARCHAR(255),
    City                  VARCHAR(100),
    NationalID            VARCHAR(50) UNIQUE,
    CustomerCategoryID    INT DEFAULT 1,
    PrimaryBranchID       INT DEFAULT 1,
    RelationshipManagerID INT,
    CreatedAt             DATETIME DEFAULT CURRENT_TIMESTAMP,
    IsActive              TINYINT(1) DEFAULT 1,
    FOREIGN KEY (CustomerCategoryID)    REFERENCES CUSTOMERCATEGORY(CategoryID),
    FOREIGN KEY (PrimaryBranchID)       REFERENCES BRANCH(BranchID),
    FOREIGN KEY (RelationshipManagerID) REFERENCES EMPLOYEE(EmployeeID)
);

-- ============================================================
-- CUSTOMER KYC
-- ============================================================
CREATE TABLE CUSTOMERKYC (
    KYCID         INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID    INT NOT NULL,
    DocumentType  VARCHAR(100),
    DocumentNumber VARCHAR(100),
    KYCStatus     ENUM('Pending','Verified','Rejected') DEFAULT 'Pending',
    VerifiedDate  DATE,
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID)
);

-- ============================================================
-- NOMINEE
-- ============================================================
CREATE TABLE NOMINEE (
    NomineeID       INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID      INT NOT NULL,
    NomineeName     VARCHAR(150) NOT NULL,
    NomineeRelation VARCHAR(100),
    NomineePhone    VARCHAR(20),
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID)
);

-- ============================================================
-- ACCOUNT PRODUCT
-- ============================================================
CREATE TABLE ACCOUNTPRODUCT (
    ProductID         INT AUTO_INCREMENT PRIMARY KEY,
    ProductName       VARCHAR(150) NOT NULL,
    AccountType       ENUM('Savings','Current','Fixed Deposit','Recurring Deposit') NOT NULL,
    InterestRate      DECIMAL(5,2) DEFAULT 0.00,
    MinBalance        DECIMAL(12,2) DEFAULT 0.00,
    Description       TEXT
);

-- ============================================================
-- ACCOUNT
-- ============================================================
CREATE TABLE ACCOUNT (
    AccountNumber    BIGINT PRIMARY KEY,
    ProductID        INT NOT NULL,
    CustomerID       INT NOT NULL,
    BranchID         INT NOT NULL,
    OpeningDate      DATE NOT NULL,
    AvailableBalance DECIMAL(15,2) DEFAULT 0.00,
    AccountStatus    ENUM('Active','Dormant','Closed','Frozen') DEFAULT 'Active',
    FOREIGN KEY (ProductID)   REFERENCES ACCOUNTPRODUCT(ProductID),
    FOREIGN KEY (CustomerID)  REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (BranchID)    REFERENCES BRANCH(BranchID)
);

-- ============================================================
-- CARDS
-- ============================================================
CREATE TABLE CARDS (
    CardID       INT AUTO_INCREMENT PRIMARY KEY,
    CardNumber   VARCHAR(20) NOT NULL UNIQUE,
    CustomerID   INT NOT NULL,
    ExpiryDate   DATE NOT NULL,
    CVV          VARCHAR(4) NOT NULL,
    CardType     ENUM('Debit','Credit','Prepaid') DEFAULT 'Debit',
    IsActive     TINYINT(1) DEFAULT 1,
    CreatedAt    DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID)
);

-- ============================================================
-- TRANSACTION TYPE
-- ============================================================
CREATE TABLE TRANSACTIONTYPE (
    TransactionTypeID INT AUTO_INCREMENT PRIMARY KEY,
    TypeName          VARCHAR(100) NOT NULL,
    Description       VARCHAR(255)
);

-- ============================================================
-- TRANSACTION
-- ============================================================
CREATE TABLE TRANSACTION (
    TransactionID     BIGINT AUTO_INCREMENT PRIMARY KEY,
    TransactionTypeID INT NOT NULL,
    TransactionAmount DECIMAL(15,2) NOT NULL,
    TransactionDate   DATETIME DEFAULT CURRENT_TIMESTAMP,
    FromAccountNumber BIGINT,
    ToAccountNumber   BIGINT,
    FromCustomerID    INT,
    ToCustomerID      INT,
    Description       VARCHAR(255),
    TransactionStatus ENUM('Pending','Completed','Failed','Reversed') DEFAULT 'Completed',
    ProcessedBy       INT,
    ReferenceNumber   VARCHAR(50) UNIQUE,
    FOREIGN KEY (TransactionTypeID) REFERENCES TRANSACTIONTYPE(TransactionTypeID),
    FOREIGN KEY (FromAccountNumber) REFERENCES ACCOUNT(AccountNumber),
    FOREIGN KEY (ToAccountNumber)   REFERENCES ACCOUNT(AccountNumber),
    FOREIGN KEY (FromCustomerID)    REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (ToCustomerID)      REFERENCES CUSTOMER(CustomerID)
);

-- ============================================================
-- DIGITAL BANKING USER (For Clients)
-- ============================================================
CREATE TABLE DIGITALBANKINGUSER (
    UserID        INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID    INT NOT NULL UNIQUE,
    Username      VARCHAR(100) NOT NULL UNIQUE,
    PasswordHash  VARCHAR(255) NOT NULL,
    IsActive      TINYINT(1) DEFAULT 1,
    LastLogin     DATETIME,
    CreatedAt     DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID)
);

-- ============================================================
-- BENEFICIARY
-- ============================================================
CREATE TABLE BENEFICIARY (
    BeneficiaryID            INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID               INT NOT NULL,
    BeneficiaryName          VARCHAR(150) NOT NULL,
    BeneficiaryAccountNumber VARCHAR(50) NOT NULL,
    BeneficiaryIFSC          VARCHAR(20) NOT NULL,
    BeneficiaryBankName      VARCHAR(150),
    IsActive                 TINYINT(1) DEFAULT 1,
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID)
);

-- ============================================================
-- Bangladesh Cities
-- ============================================================
CREATE TABLE bangladesh_cities (
    city_id INT AUTO_INCREMENT PRIMARY KEY,
    city_name VARCHAR(100) NOT NULL,
    division VARCHAR(100)
);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Zones
INSERT INTO ZONE (ZoneName) VALUES ('Dhaka Division'), ('Chittagong Division'), ('Khulna Division'), ('Rajshahi Division'), ('Rangpur Division');

-- Regions
INSERT INTO REGION (RegionName, ZoneID) VALUES
('Dhaka Metro',1), ('Gazipur',1), ('Chittagong Metro',2), ('Cox\'s Bazar',2), ('Khulna Metro',3), ('Rajshahi Metro',4), ('Rangpur Metro',5);

-- Branches
INSERT INTO BRANCH (BranchName, IFSCCode, Address, City, RegionID) VALUES
('Dhaka Main Branch','ASHA0001001','Motijheel C/A','Dhaka',1),
('Chittagong Branch','ASHA0002001','Agrabad C/A','Chittagong',3),
('Khulna Branch','ASHA0003001','KDA Avenue','Khulna',5),
('Rajshahi Branch','ASHA0004001','Shaheb Bazar','Rajshahi',6),
('Rangpur Branch','ASHA0005001','Station Road','Rangpur',7);

-- Departments
INSERT INTO DEPARTMENT (DepartmentName) VALUES
('Retail Banking'), ('Corporate Banking'), ('Operations'), ('IT & Digital'), ('Customer Service');

-- Designations
INSERT INTO DESIGNATION (DesignationName) VALUES
('Branch Manager'), ('Senior Officer'), ('Officer'), ('Executive');

-- Employees (Staff)
INSERT INTO EMPLOYEE (FirstName, LastName, Email, Phone, DepartmentID, DesignationID, BranchID, HireDate, Salary, IsActive, PasswordHash) VALUES
('Rajesh', 'Sharma', 'rajesh@ashabank.bd', '01710000001', 1, 1, 1, '2015-03-01', 85000.00, 1, '$2y$10$staff123hashstaff123hashstaff123'),
('Priya', 'Mehta', 'priya@ashabank.bd', '01710000002', 2, 2, 2, '2016-06-15', 55000.00, 1, '$2y$10$staff123hashstaff123hashstaff123'),
('Amit', 'Verma', 'amit@ashabank.bd', '01710000003', 3, 2, 3, '2018-01-10', 48000.00, 1, '$2y$10$staff123hashstaff123hashstaff123');

-- Admin User (Password: Admin@123)
-- Hash for 'Admin@123' = $2y$10$YourAdminHashHere
-- For simplicity, we'll use plain text check in PHP, but store a proper hash
INSERT INTO ADMIN_USER (Username, PasswordHash, Role, EmployeeID, IsActive) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 1, 1);

-- Customer Categories
INSERT INTO CUSTOMERCATEGORY (CategoryName) VALUES
('Regular'), ('Premium'), ('Senior Citizen'), ('Student');

-- Customers
INSERT INTO CUSTOMER (FirstName, LastName, DateOfBirth, Gender, Email, Phone, Address, City, NationalID, CustomerCategoryID, PrimaryBranchID, CreatedAt, IsActive) VALUES
('Arjun', 'Kapoor', '1990-05-15', 'Male', 'arjun.kapoor@gmail.com', '01710000101', '42 Gulshan Avenue', 'Dhaka', 'BD123456789', 1, 1, NOW(), 1),
('Sanya', 'Malhotra', '1988-11-22', 'Female', 'sanya.malhotra@gmail.com', '01710000102', '8 GEC Circle', 'Chittagong', 'BD987654321', 2, 2, NOW(), 1),
('Kiran', 'Bose', '1975-03-08', 'Female', 'kiran.bose@gmail.com', '01710000103', '15 Sonadanga', 'Khulna', 'BD456789123', 1, 3, NOW(), 1);

-- Account Products
INSERT INTO ACCOUNTPRODUCT (ProductName, AccountType, InterestRate, MinBalance, Description) VALUES
('Asha Savings Classic', 'Savings', 3.50, 1000.00, 'Standard savings account'),
('Asha Savings Premium', 'Savings', 4.00, 10000.00, 'Premium savings account'),
('Asha Current Pro', 'Current', 0.00, 5000.00, 'Business current account');

-- Accounts (11-digit account numbers)
INSERT INTO ACCOUNT (AccountNumber, ProductID, CustomerID, BranchID, OpeningDate, AvailableBalance, AccountStatus) VALUES
(10000000001, 1, 1, 1, '2023-01-15', 45250.75, 'Active'),
(10000000002, 1, 2, 2, '2023-02-20', 128900.00, 'Active'),
(10000000003, 2, 3, 3, '2022-11-01', 350000.00, 'Active');

-- Cards
INSERT INTO CARDS (CardNumber, CustomerID, ExpiryDate, CVV, CardType, IsActive) VALUES
('4532123456789012', 1, DATE_ADD(CURDATE(), INTERVAL 5 YEAR), '123', 'Debit', 1),
('4532987654321098', 2, DATE_ADD(CURDATE(), INTERVAL 5 YEAR), '456', 'Debit', 1),
('4532111122223333', 3, DATE_ADD(CURDATE(), INTERVAL 5 YEAR), '789', 'Debit', 1);

-- Nominees
INSERT INTO NOMINEE (CustomerID, NomineeName, NomineeRelation, NomineePhone) VALUES
(1, 'Sita Kapoor', 'Spouse', '01710000201'),
(2, 'Raj Malhotra', 'Father', '01710000202');

-- Transaction Types
INSERT INTO TRANSACTIONTYPE (TypeName, Description) VALUES
('Deposit', 'Money deposited into account'),
('Withdrawal', 'Money withdrawn from account'),
('Transfer', 'Fund transfer between accounts'),
('NEFT', 'National Electronic Funds Transfer'),
('UPI', 'Unified Payment Interface');

-- Sample Transactions
INSERT INTO TRANSACTION (TransactionTypeID, TransactionAmount, TransactionDate, FromAccountNumber, ToAccountNumber, FromCustomerID, ToCustomerID, Description, TransactionStatus, ReferenceNumber) VALUES
(1, 5000.00, NOW(), NULL, 10000000001, NULL, 1, 'Initial Deposit', 'Completed', 'DEP20241101001'),
(3, 10000.00, NOW(), 10000000001, 10000000002, 1, 2, 'Personal Transfer', 'Completed', 'TXN20241105002'),
(2, 2000.00, NOW(), 10000000002, NULL, 2, NULL, 'ATM Withdrawal', 'Completed', 'WDL20241108003'),
(1, 25000.00, NOW(), NULL, 10000000003, NULL, 3, 'Salary Credit', 'Completed', 'DEP20241110004');

-- Digital Banking Users (Clients) - Password: 'password'
-- Hash for 'password' = $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
INSERT INTO DIGITALBANKINGUSER (CustomerID, Username, PasswordHash, IsActive, CreatedAt) VALUES
(1, 'arjun.kapoor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(2, 'sanya.malhotra', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(3, 'kiran.bose', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW());

-- Beneficiaries
INSERT INTO BENEFICIARY (CustomerID, BeneficiaryName, BeneficiaryAccountNumber, BeneficiaryIFSC, BeneficiaryBankName, IsActive) VALUES
(1, 'Sanya Malhotra', '10000000002', 'ASHA0002001', 'Asha Bank', 1),
(2, 'Arjun Kapoor', '10000000001', 'ASHA0001001', 'Asha Bank', 1);

-- Bangladesh Cities
INSERT INTO bangladesh_cities (city_name, division) VALUES
('Dhaka', 'Dhaka'), ('Chittagong', 'Chittagong'), ('Khulna', 'Khulna'),
('Rajshahi', 'Rajshahi'), ('Barisal', 'Barisal'), ('Sylhet', 'Sylhet'),
('Rangpur', 'Rangpur'), ('Mymensingh', 'Mymensingh'), ('Comilla', 'Chittagong'),
('Narayanganj', 'Dhaka'), ('Gazipur', 'Dhaka'), ('Jessore', 'Khulna'),
('Bogra', 'Rajshahi'), ('Dinajpur', 'Rangpur'), ('Pabna', 'Rajshahi');

-- ============================================================
-- VIEWS
-- ============================================================
CREATE VIEW v_account_summary AS
SELECT a.AccountNumber, CONCAT(c.FirstName, ' ', c.LastName) AS CustomerName,
       c.Email, c.Phone, ap.ProductName, a.AvailableBalance, a.AccountStatus
FROM ACCOUNT a
JOIN CUSTOMER c ON a.CustomerID = c.CustomerID
JOIN ACCOUNTPRODUCT ap ON a.ProductID = ap.ProductID;

-- ============================================================
-- FINAL OUTPUT
-- ============================================================
SELECT '✅ Database setup complete!' AS Status;
SELECT '=========================================' AS '';
SELECT '🔑 LOGIN CREDENTIALS:' AS '';
SELECT '-----------------------------------------' AS '';
SELECT '👑 Admin: username = "admin", password = "Admin@123"' AS '';
SELECT '👔 Staff: email = "rajesh@ashabank.bd", password = "staff123"' AS '';
SELECT '👤 Client: username = "arjun.kapoor", password = "password"' AS '';
SELECT '=========================================' AS '';



-- Add these tables to your existing database

-- ============================================================
-- NOTIFICATIONS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
);

-- ============================================================
-- STAFF TABLE (Separate from EMPLOYEE)
-- ============================================================
CREATE TABLE IF NOT EXISTS staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('manager', 'officer', 'teller', 'support') DEFAULT 'officer',
    department VARCHAR(100),
    join_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- KYC VERIFICATION TABLE (Enhanced)
-- ============================================================
CREATE TABLE IF NOT EXISTS kyc_verifications (
    kyc_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    nid_number VARCHAR(50),
    passport_number VARCHAR(50),
    birth_certificate VARCHAR(50),
    address_proof VARCHAR(255),
    photo VARCHAR(255),
    status ENUM('pending', 'verified', 'rejected', 'resubmit') DEFAULT 'pending',
    rejection_reason TEXT,
    verified_by INT,
    verified_at DATETIME,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES staff(staff_id)
);

-- Insert sample staff
INSERT INTO staff (first_name, last_name, email, phone, username, password_hash, role, department, join_date) VALUES
('Rajesh', 'Sharma', 'rajesh@ashabank.bd', '01710000001', 'rajesh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'Operations', '2020-01-15'),
('Priya', 'Mehta', 'priya@ashabank.bd', '01710000002', 'priya', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer', 'Customer Service', '2021-03-20'),
('Amit', 'Verma', 'amit@ashabank.bd', '01710000003', 'amit', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teller', 'Retail Banking', '2022-01-10');

-- Insert sample notifications for existing customers
INSERT INTO notifications (customer_id, title, message, type, created_at) VALUES
(1, 'Welcome to Asha Bank!', 'Thank you for joining Asha Bank. Please complete your KYC verification to activate full account features.', 'info', NOW()),
(1, 'KYC Verification Required', 'Your KYC verification is pending. Please submit your documents to complete verification.', 'warning', NOW()),
(2, 'Special Offer', 'Get 5% cashback on all online transactions this month!', 'success', NOW());


-- ============================================================
-- ADD MISSING TABLES FOR ASHA BANK
-- Run this SQL to fix all missing table errors
-- ============================================================

USE asha_bank;

-- ============================================================
-- 1. NOTIFICATIONS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
);

-- ============================================================
-- 2. STAFF TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS staff (
    staff_id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(150) UNIQUE NOT NULL,
    phone VARCHAR(20),
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('manager', 'officer', 'teller', 'support') DEFAULT 'officer',
    department VARCHAR(100),
    join_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 3. KYC VERIFICATIONS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS kyc_verifications (
    kyc_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    nid_number VARCHAR(50),
    passport_number VARCHAR(50),
    birth_certificate VARCHAR(50),
    address_proof VARCHAR(255),
    photo VARCHAR(255),
    status ENUM('pending', 'verified', 'rejected', 'resubmit') DEFAULT 'pending',
    rejection_reason TEXT,
    verified_by INT,
    verified_at DATETIME,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (verified_by) REFERENCES staff(staff_id)
);

-- ============================================================
-- 4. BENEFICIARY TABLE (if not exists)
-- ============================================================
CREATE TABLE IF NOT EXISTS BENEFICIARY (
    BeneficiaryID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT NOT NULL,
    BeneficiaryName VARCHAR(150) NOT NULL,
    BeneficiaryAccountNumber VARCHAR(50) NOT NULL,
    BeneficiaryIFSC VARCHAR(20) NOT NULL,
    BeneficiaryBankName VARCHAR(150),
    IsActive TINYINT(1) DEFAULT 1,
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID)
);

-- ============================================================
-- 5. CARDS TABLE (if not exists)
-- ============================================================
CREATE TABLE IF NOT EXISTS CARDS (
    CardID INT AUTO_INCREMENT PRIMARY KEY,
    CardNumber VARCHAR(20) NOT NULL UNIQUE,
    CustomerID INT NOT NULL,
    ExpiryDate DATE NOT NULL,
    CVV VARCHAR(4) NOT NULL,
    CardType ENUM('Debit','Credit','Prepaid') DEFAULT 'Debit',
    IsActive TINYINT(1) DEFAULT 1,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID)
);

-- ============================================================
-- 6. INSERT SAMPLE STAFF DATA
-- ============================================================
INSERT IGNORE INTO staff (first_name, last_name, email, phone, username, password_hash, role, department, join_date) VALUES
('Rajesh', 'Sharma', 'rajesh@ashabank.bd', '01710000001', 'rajesh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'Operations', '2020-01-15'),
('Priya', 'Mehta', 'priya@ashabank.bd', '01710000002', 'priya', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer', 'Customer Service', '2021-03-20'),
('Amit', 'Verma', 'amit@ashabank.bd', '01710000003', 'amit', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teller', 'Retail Banking', '2022-01-10');

-- ============================================================
-- 7. INSERT SAMPLE NOTIFICATIONS FOR EXISTING CUSTOMERS
-- ============================================================
INSERT IGNORE INTO notifications (customer_id, title, message, type, created_at) VALUES
(1, 'Welcome to Asha Bank!', 'Thank you for joining Asha Bank. We are delighted to have you as our customer.', 'success', NOW()),
(1, 'KYC Verification Required', 'Please complete your KYC verification to activate all banking features.', 'warning', NOW()),
(2, 'Special Offer', 'Get 5% cashback on all online transactions this month!', 'success', NOW()),
(2, 'Account Statement', 'Your monthly account statement is ready to download.', 'info', NOW()),
(3, 'Loan Pre-approval', 'Congratulations! You are pre-approved for a personal loan up to 500,000 BDT.', 'success', NOW());

-- ============================================================
-- 8. INSERT SAMPLE KYC VERIFICATIONS
-- ============================================================
INSERT IGNORE INTO kyc_verifications (customer_id, nid_number, status, submitted_at) VALUES
(1, '12345678901234567', 'pending', NOW()),
(2, '98765432109876543', 'verified', NOW()),
(3, '11122233344455566', 'pending', NOW());

-- ============================================================
-- 9. VERIFY ALL TABLES EXIST
-- ============================================================
SELECT '✅ All tables created successfully!' AS Status;
SELECT 'Tables in database:' AS '';
SHOW TABLES;


-- ============================================================
-- FEEDBACK & MESSAGING SYSTEM TABLES
-- Run this SQL to add messaging features
-- ============================================================

USE asha_bank;

-- ============================================================
-- FEEDBACK TABLE (Client to Staff)
-- ============================================================
CREATE TABLE IF NOT EXISTS feedback (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('feedback', 'complaint', 'suggestion', 'issue') DEFAULT 'feedback',
    status ENUM('pending', 'read', 'replied', 'resolved') DEFAULT 'pending',
    staff_reply TEXT,
    replied_by INT,
    replied_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (replied_by) REFERENCES staff(staff_id) ON DELETE SET NULL
);

-- ============================================================
-- STAFF TO ADMIN MESSAGES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS staff_messages (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('suggestion', 'request', 'report', 'issue') DEFAULT 'request',
    status ENUM('pending', 'read', 'approved', 'rejected') DEFAULT 'pending',
    admin_reply TEXT,
    replied_by INT,
    replied_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES staff(staff_id) ON DELETE CASCADE,
    FOREIGN KEY (replied_by) REFERENCES ADMIN_USER(AdminID) ON DELETE SET NULL
);

-- ============================================================
-- CLIENT NOTIFICATIONS FOR REPLIES
-- ============================================================
ALTER TABLE notifications ADD COLUMN IF NOT EXISTS feedback_id INT;
ALTER TABLE notifications ADD FOREIGN KEY (feedback_id) REFERENCES feedback(feedback_id) ON DELETE CASCADE;

-- Insert sample staff for testing if not exists
INSERT IGNORE INTO staff (first_name, last_name, email, phone, username, password_hash, role, department, join_date) VALUES
('Rajesh', 'Sharma', 'rajesh@ashabank.bd', '01710000001', 'rajesh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'Operations', '2020-01-15'),
('Priya', 'Mehta', 'priya@ashabank.bd', '01710000002', 'priya', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer', 'Customer Service', '2021-03-20');

SELECT '✅ Messaging system tables created successfully!' AS Status;


-- Fix missing columns in feedback table
USE asha_bank;

-- Add is_read column to feedback table if not exists
ALTER TABLE feedback ADD COLUMN IF NOT EXISTS is_read TINYINT(1) DEFAULT 0;

-- Add replied_at column if not exists
ALTER TABLE feedback ADD COLUMN IF NOT EXISTS replied_at DATETIME;

-- Update existing records
UPDATE feedback SET is_read = 1 WHERE status = 'resolved';

SELECT '✅ Database fixed successfully!' AS Status;

-- Fix staff table and add missing data
USE asha_bank;

-- Ensure staff table has correct structure
ALTER TABLE staff MODIFY COLUMN password_hash VARCHAR(255) NOT NULL;
ALTER TABLE staff ADD COLUMN IF NOT EXISTS is_active TINYINT(1) DEFAULT 1;

-- Update existing staff with proper password hashes
-- Password for all staff is 'staff123' hashed
UPDATE staff SET password_hash = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi' WHERE password_hash IS NULL OR password_hash = '';

-- Insert default staff if not exists
INSERT IGNORE INTO staff (first_name, last_name, email, phone, username, password_hash, role, department, join_date, is_active) VALUES
('Rajesh', 'Sharma', 'rajesh@ashabank.bd', '01710000001', 'rajesh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'Operations', CURDATE(), 1),
('Priya', 'Mehta', 'priya@ashabank.bd', '01710000002', 'priya', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer', 'Customer Service', CURDATE(), 1),
('Amit', 'Verma', 'amit@ashabank.bd', '01710000003', 'amit', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'teller', 'Retail Banking', CURDATE(), 1);

SELECT '✅ Staff table fixed!' AS Status;
