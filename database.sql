-- ============================================================
-- ASHA BANK - PRODUCTION-READY COMPLETE DATABASE SCHEMA
-- Compatible with MySQL 5.7+ / MySQL 8.0+ / MariaDB 10.3+
-- Character Set: utf8mb4 / Collation: utf8mb4_unicode_ci
-- ============================================================

DROP DATABASE IF EXISTS asha_bank;
CREATE DATABASE asha_bank CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE asha_bank;

SET FOREIGN_KEY_CHECKS = 0;
SET SQL_MODE = 'NO_AUTO_VALUE_ON_ZERO';

-- ============================================================
-- 1. GEOGRAPHIC & ORGANIZATIONAL TABLES
-- ============================================================

DROP TABLE IF EXISTS ZONE;
CREATE TABLE ZONE (
    ZoneID INT AUTO_INCREMENT PRIMARY KEY,
    ZoneName VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS REGION;
CREATE TABLE REGION (
    RegionID INT AUTO_INCREMENT PRIMARY KEY,
    RegionName VARCHAR(100) NOT NULL,
    ZoneID INT NOT NULL,
    FOREIGN KEY (ZoneID) REFERENCES ZONE(ZoneID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS BRANCH;
CREATE TABLE BRANCH (
    BranchID INT AUTO_INCREMENT PRIMARY KEY,
    BranchName VARCHAR(150) NOT NULL,
    IFSCCode VARCHAR(20) NOT NULL UNIQUE,
    Address VARCHAR(255),
    City VARCHAR(100),
    RegionID INT,
    ManagerEmployeeID INT NULL,
    Latitude DECIMAL(10,8) NULL,
    Longitude DECIMAL(11,8) NULL,
    Phone VARCHAR(30) NULL,
    Email VARCHAR(150) NULL,
    IsActive TINYINT(1) DEFAULT 1,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (RegionID) REFERENCES REGION(RegionID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS DEPARTMENT;
CREATE TABLE DEPARTMENT (
    DepartmentID INT AUTO_INCREMENT PRIMARY KEY,
    DepartmentName VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS DESIGNATION;
CREATE TABLE DESIGNATION (
    DesignationID INT AUTO_INCREMENT PRIMARY KEY,
    DesignationName VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS EMPLOYEE;
CREATE TABLE EMPLOYEE (
    EmployeeID INT AUTO_INCREMENT PRIMARY KEY,
    FirstName VARCHAR(100) NOT NULL,
    LastName VARCHAR(100) NOT NULL,
    Email VARCHAR(150) UNIQUE,
    Phone VARCHAR(20),
    DepartmentID INT NULL,
    DesignationID INT NULL,
    BranchID INT NULL,
    ManagerID INT NULL,
    HireDate DATE,
    Salary DECIMAL(12,2) DEFAULT 0.00,
    IsActive TINYINT(1) DEFAULT 1,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (DepartmentID) REFERENCES DEPARTMENT(DepartmentID) ON DELETE SET NULL,
    FOREIGN KEY (DesignationID) REFERENCES DESIGNATION(DesignationID) ON DELETE SET NULL,
    FOREIGN KEY (BranchID) REFERENCES BRANCH(BranchID) ON DELETE SET NULL,
    FOREIGN KEY (ManagerID) REFERENCES EMPLOYEE(EmployeeID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE BRANCH ADD CONSTRAINT fk_branch_manager FOREIGN KEY (ManagerEmployeeID) REFERENCES EMPLOYEE(EmployeeID) ON DELETE SET NULL;

-- ============================================================
-- 2. USER & AUTHENTICATION TABLES
-- ============================================================

DROP TABLE IF EXISTS ADMIN_USER;
CREATE TABLE ADMIN_USER (
    AdminID INT AUTO_INCREMENT PRIMARY KEY,
    Username VARCHAR(100) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    Role ENUM('superadmin','manager','teller','support') DEFAULT 'superadmin',
    EmployeeID INT NULL,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    LastLogin DATETIME NULL,
    IsActive TINYINT(1) DEFAULT 1,
    FOREIGN KEY (EmployeeID) REFERENCES EMPLOYEE(EmployeeID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS STAFF;
CREATE TABLE STAFF (
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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_login DATETIME NULL,
    login_attempts INT DEFAULT 0,
    locked_until DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS CUSTOMERCATEGORY;
CREATE TABLE CUSTOMERCATEGORY (
    CategoryID INT AUTO_INCREMENT PRIMARY KEY,
    CategoryName VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS CUSTOMER;
CREATE TABLE CUSTOMER (
    CustomerID INT AUTO_INCREMENT PRIMARY KEY,
    FirstName VARCHAR(100) NOT NULL,
    LastName VARCHAR(100) NOT NULL,
    DateOfBirth DATE,
    Gender ENUM('Male','Female','Other'),
    Email VARCHAR(150) UNIQUE,
    Phone VARCHAR(20),
    Address VARCHAR(255),
    City VARCHAR(100),
    NationalID VARCHAR(50) UNIQUE,
    CustomerCategoryID INT DEFAULT 1,
    PrimaryBranchID INT DEFAULT 1,
    RelationshipManagerID INT NULL,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    IsActive TINYINT(1) DEFAULT 1,
    ReferralCode VARCHAR(20) UNIQUE,
    ReferredBy INT NULL,
    FOREIGN KEY (CustomerCategoryID) REFERENCES CUSTOMERCATEGORY(CategoryID) ON DELETE SET NULL,
    FOREIGN KEY (PrimaryBranchID) REFERENCES BRANCH(BranchID) ON DELETE SET NULL,
    FOREIGN KEY (RelationshipManagerID) REFERENCES EMPLOYEE(EmployeeID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS DIGITALBANKINGUSER;
CREATE TABLE DIGITALBANKINGUSER (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT NOT NULL UNIQUE,
    Username VARCHAR(100) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    TransactionPIN VARCHAR(255) NULL,
    IsActive TINYINT(1) DEFAULT 1,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    LastLogin DATETIME NULL,
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS NOMINEE;
CREATE TABLE NOMINEE (
    NomineeID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT NOT NULL,
    NomineeName VARCHAR(150) NOT NULL,
    NomineeRelation VARCHAR(100),
    NomineePhone VARCHAR(20),
    NomineeDOB DATE NULL,
    NomineeAddress VARCHAR(255),
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 3. CORE BANKING ACCOUNTS & CARDS
-- ============================================================

DROP TABLE IF EXISTS ACCOUNTPRODUCT;
CREATE TABLE ACCOUNTPRODUCT (
    ProductID INT AUTO_INCREMENT PRIMARY KEY,
    ProductName VARCHAR(100) NOT NULL,
    AccountType ENUM('Savings','Current','Fixed Deposit','Student','Salary') DEFAULT 'Savings',
    InterestRate DECIMAL(5,2) DEFAULT 0.00,
    MinBalance DECIMAL(15,2) DEFAULT 500.00,
    MaxWithdrawalLimit DECIMAL(15,2) DEFAULT 200000.00,
    DailyTransferLimit DECIMAL(15,2) DEFAULT 500000.00,
    Description TEXT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS ACCOUNT;
CREATE TABLE ACCOUNT (
    AccountNumber BIGINT PRIMARY KEY,
    ProductID INT NOT NULL,
    CustomerID INT NOT NULL,
    BranchID INT NOT NULL,
    OpeningDate DATE NOT NULL,
    AvailableBalance DECIMAL(15,2) DEFAULT 0.00,
    AccountStatus ENUM('Active','Dormant','Frozen','Closed') DEFAULT 'Active',
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ProductID) REFERENCES ACCOUNTPRODUCT(ProductID),
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (BranchID) REFERENCES BRANCH(BranchID)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS JOINT_ACCOUNT_HOLDERS;
CREATE TABLE JOINT_ACCOUNT_HOLDERS (
    JointID INT AUTO_INCREMENT PRIMARY KEY,
    AccountNumber BIGINT NOT NULL,
    CustomerID INT NOT NULL,
    Relation VARCHAR(50),
    AddedDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (AccountNumber) REFERENCES ACCOUNT(AccountNumber) ON DELETE CASCADE,
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS CARDS;
CREATE TABLE CARDS (
    CardID INT AUTO_INCREMENT PRIMARY KEY,
    CardNumber VARCHAR(20) NOT NULL UNIQUE,
    CustomerID INT NOT NULL,
    AccountNumber BIGINT NOT NULL,
    CardHolderName VARCHAR(150),
    ExpiryDate DATE NOT NULL,
    CVV VARCHAR(4) NOT NULL,
    CardType ENUM('Debit','Credit','Prepaid') DEFAULT 'Debit',
    CardStatus ENUM('Active','Blocked','Expired') DEFAULT 'Active',
    DailyLimit DECIMAL(15,2) DEFAULT 100000.00,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (AccountNumber) REFERENCES ACCOUNT(AccountNumber) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 4. TRANSACTIONS & AUDITING
-- ============================================================

DROP TABLE IF EXISTS TRANSACTIONTYPE;
CREATE TABLE TRANSACTIONTYPE (
    TransactionTypeID INT AUTO_INCREMENT PRIMARY KEY,
    TypeName VARCHAR(50) NOT NULL,
    Description VARCHAR(255),
    Fee DECIMAL(10,2) DEFAULT 0.00
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS TRANSACTION;
CREATE TABLE TRANSACTION (
    TransactionID BIGINT AUTO_INCREMENT PRIMARY KEY,
    TransactionTypeID INT NOT NULL,
    TransactionAmount DECIMAL(15,2) NOT NULL,
    TransactionDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    FromAccountNumber BIGINT NULL,
    ToAccountNumber BIGINT NULL,
    FromCustomerID INT NULL,
    ToCustomerID INT NULL,
    Description VARCHAR(255),
    TransactionStatus ENUM('Pending','Completed','Processing','Failed','Cancelled') DEFAULT 'Completed',
    ReferenceNumber VARCHAR(100) UNIQUE,
    FOREIGN KEY (TransactionTypeID) REFERENCES TRANSACTIONTYPE(TransactionTypeID),
    FOREIGN KEY (FromAccountNumber) REFERENCES ACCOUNT(AccountNumber) ON DELETE SET NULL,
    FOREIGN KEY (ToAccountNumber) REFERENCES ACCOUNT(AccountNumber) ON DELETE SET NULL,
    FOREIGN KEY (FromCustomerID) REFERENCES CUSTOMER(CustomerID) ON DELETE SET NULL,
    FOREIGN KEY (ToCustomerID) REFERENCES CUSTOMER(CustomerID) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS TRANSACTION_LIMITS;
CREATE TABLE TRANSACTION_LIMITS (
    LimitID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT NOT NULL UNIQUE,
    DailyTransferLimit DECIMAL(15,2) DEFAULT 500000.00,
    DailyWithdrawalLimit DECIMAL(15,2) DEFAULT 200000.00,
    PerTransactionLimit DECIMAL(15,2) DEFAULT 300000.00,
    UpdatedAt DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS TRANSACTION_AUDIT;
CREATE TABLE TRANSACTION_AUDIT (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id BIGINT NULL,
    action_type VARCHAR(50),
    old_amount DECIMAL(15,2),
    new_amount DECIMAL(15,2),
    changed_by VARCHAR(100),
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS AUDIT_LOGS;
CREATE TABLE AUDIT_LOGS (
    log_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    user_type VARCHAR(50),
    action VARCHAR(50),
    table_name VARCHAR(100),
    record_id INT NULL,
    old_data JSON NULL,
    new_data JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 5. NOTIFICATIONS & CUSTOMER CARE
-- ============================================================

DROP TABLE IF EXISTS NOTIFICATIONS;
CREATE TABLE NOTIFICATIONS (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info','success','warning','danger') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS FEEDBACK;
CREATE TABLE FEEDBACK (
    feedback_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('feedback','complaint','inquiry') DEFAULT 'feedback',
    status ENUM('pending','in_progress','resolved','closed') DEFAULT 'pending',
    admin_reply TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    resolved_at DATETIME NULL,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS KYC_VERIFICATIONS;
CREATE TABLE KYC_VERIFICATIONS (
    kyc_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    nid_number VARCHAR(50) NOT NULL,
    id_type VARCHAR(50) DEFAULT 'National ID',
    id_image_front VARCHAR(255) NULL,
    id_image_back VARCHAR(255) NULL,
    selfie_image VARCHAR(255) NULL,
    status ENUM('pending','verified','rejected') DEFAULT 'pending',
    rejection_reason TEXT NULL,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    verified_at DATETIME NULL,
    verified_by INT NULL,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS REACTIVATION_REQUESTS;
CREATE TABLE REACTIVATION_REQUESTS (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    requested_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    reviewed_at DATETIME NULL,
    reviewed_by INT NULL,
    remarks TEXT NULL,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS STAFF_MESSAGES;
CREATE TABLE STAFF_MESSAGES (
    message_id INT AUTO_INCREMENT PRIMARY KEY,
    staff_id INT NOT NULL,
    sender_id INT NULL,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (staff_id) REFERENCES STAFF(staff_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 6. LOANS & FINANCING
-- ============================================================

DROP TABLE IF EXISTS LOAN_PRODUCTS;
CREATE TABLE LOAN_PRODUCTS (
    loan_product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(100) NOT NULL,
    loan_type ENUM('Home','Personal','Car','Education','SME') DEFAULT 'Personal',
    min_amount DECIMAL(15,2) DEFAULT 50000.00,
    max_amount DECIMAL(15,2) DEFAULT 5000000.00,
    min_tenure_months INT DEFAULT 12,
    max_tenure_months INT DEFAULT 60,
    interest_rate DECIMAL(5,2) NOT NULL,
    processing_fee DECIMAL(10,2) DEFAULT 1000.00,
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS LOANS;
CREATE TABLE LOANS (
    loan_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    loan_product_id INT NOT NULL,
    loan_amount DECIMAL(15,2) NOT NULL,
    tenure_months INT NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    emi_amount DECIMAL(15,2) NOT NULL,
    status ENUM('Pending','Approved','Rejected','Active','Closed') DEFAULT 'Pending',
    application_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    approval_date DATETIME NULL,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (loan_product_id) REFERENCES LOAN_PRODUCTS(loan_product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS LOAN_EMI_SCHEDULE;
CREATE TABLE LOAN_EMI_SCHEDULE (
    emi_id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    due_date DATE NOT NULL,
    emi_amount DECIMAL(15,2) NOT NULL,
    principal_amount DECIMAL(15,2) NOT NULL,
    interest_amount DECIMAL(15,2) NOT NULL,
    outstanding_balance DECIMAL(15,2) NOT NULL,
    status ENUM('Pending','Paid','Overdue') DEFAULT 'Pending',
    paid_date DATETIME NULL,
    FOREIGN KEY (loan_id) REFERENCES LOANS(loan_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 7. UTILITY BILLS & CHEQUES
-- ============================================================

DROP TABLE IF EXISTS BILL_CATEGORIES;
CREATE TABLE BILL_CATEGORIES (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    category_icon VARCHAR(50) DEFAULT 'fa-bolt',
    is_active TINYINT(1) DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS BILL_PROVIDERS;
CREATE TABLE BILL_PROVIDERS (
    provider_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    provider_name VARCHAR(100) NOT NULL,
    provider_code VARCHAR(50) NOT NULL UNIQUE,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES BILL_CATEGORIES(category_id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS BILL_PAYMENTS;
CREATE TABLE BILL_PAYMENTS (
    bill_payment_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    from_account BIGINT NULL,
    provider_id INT NOT NULL,
    account_number VARCHAR(100) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    fee DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(15,2) NOT NULL,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Completed','Pending','Failed','Cancelled') DEFAULT 'Completed',
    reference_number VARCHAR(100) UNIQUE,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (from_account) REFERENCES ACCOUNT(AccountNumber) ON DELETE SET NULL,
    FOREIGN KEY (provider_id) REFERENCES BILL_PROVIDERS(provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS BILL_AUTOPAY_SCHEDULES;
CREATE TABLE BILL_AUTOPAY_SCHEDULES (
    autopay_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    from_account BIGINT NOT NULL,
    provider_id INT NOT NULL,
    consumer_number VARCHAR(100) NOT NULL,
    max_amount DECIMAL(15,2) NOT NULL,
    next_due_date DATE NULL,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (from_account) REFERENCES ACCOUNT(AccountNumber) ON DELETE CASCADE,
    FOREIGN KEY (provider_id) REFERENCES BILL_PROVIDERS(provider_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS CHEQUE_BOOK_REQUESTS;
CREATE TABLE CHEQUE_BOOK_REQUESTS (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    account_number BIGINT NOT NULL,
    leaves_count INT DEFAULT 25,
    status ENUM('Pending','Approved','Dispatched','Delivered','Rejected') DEFAULT 'Pending',
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    processed_date DATETIME NULL,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (account_number) REFERENCES ACCOUNT(AccountNumber) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS CHEQUES;
CREATE TABLE CHEQUES (
    cheque_id INT AUTO_INCREMENT PRIMARY KEY,
    account_number BIGINT NOT NULL,
    cheque_number VARCHAR(50) NOT NULL,
    payee_name VARCHAR(150) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    issue_date DATE NOT NULL,
    status ENUM('Issued','Presented','Cleared','Bounced','Stopped','Cancelled') DEFAULT 'Issued',
    clearance_date DATETIME NULL,
    FOREIGN KEY (account_number) REFERENCES ACCOUNT(AccountNumber) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 8. REWARDS & REFERRALS
-- ============================================================

DROP TABLE IF EXISTS REFERRALS;
CREATE TABLE REFERRALS (
    referral_id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL,
    referee_id INT NOT NULL,
    referral_code VARCHAR(50) NOT NULL,
    status ENUM('pending','completed') DEFAULT 'completed',
    bonus_awarded DECIMAL(10,2) DEFAULT 100.00,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE,
    FOREIGN KEY (referee_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS REWARD_POINTS;
CREATE TABLE REWARD_POINTS (
    reward_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL UNIQUE,
    points_balance INT DEFAULT 0,
    lifetime_earned INT DEFAULT 0,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

DROP TABLE IF EXISTS REWARD_HISTORY;
CREATE TABLE REWARD_HISTORY (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    points INT NOT NULL,
    type ENUM('earn','redeem') NOT NULL,
    description VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================
-- 9. INDEXES
-- ============================================================

CREATE INDEX idx_customer_email ON CUSTOMER(Email);
CREATE INDEX idx_customer_phone ON CUSTOMER(Phone);
CREATE INDEX idx_customer_nid ON CUSTOMER(NationalID);
CREATE INDEX idx_customer_name ON CUSTOMER(FirstName, LastName);
CREATE INDEX idx_account_balance ON ACCOUNT(AvailableBalance);
CREATE INDEX idx_account_status ON ACCOUNT(AccountStatus);
CREATE INDEX idx_transaction_date ON TRANSACTION(TransactionDate);
CREATE INDEX idx_transaction_status ON TRANSACTION(TransactionStatus);
CREATE INDEX idx_transaction_reference ON TRANSACTION(ReferenceNumber);
CREATE INDEX idx_transaction_from_cust ON TRANSACTION(FromCustomerID, TransactionDate);
CREATE INDEX idx_transaction_to_cust ON TRANSACTION(ToCustomerID, TransactionDate);
CREATE INDEX idx_notification_customer ON NOTIFICATIONS(customer_id, is_read);
CREATE INDEX idx_notification_created ON NOTIFICATIONS(created_at);
CREATE INDEX idx_feedback_customer ON FEEDBACK(customer_id, status);
CREATE INDEX idx_feedback_status ON FEEDBACK(status);
CREATE INDEX idx_loan_customer ON LOANS(customer_id, status);
CREATE INDEX idx_emi_loan ON LOAN_EMI_SCHEDULE(loan_id, status);
CREATE INDEX idx_cheque_account ON CHEQUES(account_number, status);
CREATE INDEX idx_bill_customer ON BILL_PAYMENTS(customer_id, status);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 10. FUNCTIONS
-- ============================================================

DELIMITER $$

DROP FUNCTION IF EXISTS fn_calculate_age$$
CREATE FUNCTION fn_calculate_age(p_dob DATE)
RETURNS INT
DETERMINISTIC
BEGIN
    IF p_dob IS NULL THEN RETURN 0; END IF;
    RETURN TIMESTAMPDIFF(YEAR, p_dob, CURDATE());
END$$

DROP FUNCTION IF EXISTS fn_get_customer_tier$$
CREATE FUNCTION fn_get_customer_tier(p_balance DECIMAL(15,2))
RETURNS VARCHAR(20)
DETERMINISTIC
BEGIN
    DECLARE v_tier VARCHAR(20);
    IF p_balance >= 1000000 THEN
        SET v_tier = 'Black Edition';
    ELSEIF p_balance >= 500000 THEN
        SET v_tier = 'Platinum';
    ELSEIF p_balance >= 100000 THEN
        SET v_tier = 'Gold';
    ELSEIF p_balance >= 10000 THEN
        SET v_tier = 'Silver';
    ELSE
        SET v_tier = 'Classic';
    END IF;
    RETURN v_tier;
END$$

DROP FUNCTION IF EXISTS fn_format_currency$$
CREATE FUNCTION fn_format_currency(p_amount DECIMAL(15,2))
RETURNS VARCHAR(50)
DETERMINISTIC
BEGIN
    RETURN CONCAT('৳ ', FORMAT(COALESCE(p_amount, 0), 2));
END$$

DROP FUNCTION IF EXISTS fn_total_customer_transactions$$
CREATE FUNCTION fn_total_customer_transactions(p_customer_id INT)
RETURNS INT
READS SQL DATA
BEGIN
    DECLARE v_total INT DEFAULT 0;
    SELECT COUNT(*) INTO v_total
    FROM TRANSACTION
    WHERE FromCustomerID = p_customer_id OR ToCustomerID = p_customer_id;
    RETURN v_total;
END$$

DROP FUNCTION IF EXISTS fn_calculate_emi$$
CREATE FUNCTION fn_calculate_emi(
    p_amount DECIMAL(15,2),
    p_rate DECIMAL(5,2),
    p_tenure INT
)
RETURNS DECIMAL(15,2)
DETERMINISTIC
BEGIN
    DECLARE v_monthly_rate DECIMAL(10,6);
    DECLARE v_temp DECIMAL(15,6);
    DECLARE v_emi DECIMAL(15,2);
    
    IF p_amount <= 0 OR p_tenure <= 0 THEN RETURN 0.00; END IF;
    IF p_rate <= 0 THEN RETURN ROUND(p_amount / p_tenure, 2); END IF;
    
    SET v_monthly_rate = p_rate / 12 / 100;
    SET v_temp = POW(1 + v_monthly_rate, p_tenure);
    SET v_emi = p_amount * v_monthly_rate * v_temp / (v_temp - 1);
    
    RETURN ROUND(v_emi, 2);
END$$

DROP FUNCTION IF EXISTS fn_get_balance$$
CREATE FUNCTION fn_get_balance(p_account BIGINT)
RETURNS DECIMAL(15,2)
READS SQL DATA
BEGIN
    DECLARE v_balance DECIMAL(15,2) DEFAULT 0.00;
    SELECT COALESCE(AvailableBalance, 0.00) INTO v_balance
    FROM ACCOUNT WHERE AccountNumber = p_account;
    RETURN v_balance;
END$$

-- ============================================================
-- 11. STORED PROCEDURES
-- ============================================================

DROP PROCEDURE IF EXISTS sp_get_customer_statement$$
CREATE PROCEDURE sp_get_customer_statement(
    IN p_customer_id INT,
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    SELECT t.TransactionID, tt.TypeName, t.TransactionAmount, 
           t.TransactionDate, t.Description, t.TransactionStatus,
           t.FromAccountNumber, t.ToAccountNumber,
           CASE 
               WHEN t.FromCustomerID = p_customer_id THEN 'Debit'
               WHEN t.ToCustomerID = p_customer_id THEN 'Credit'
               ELSE 'Other'
           END AS TransactionDirection
    FROM TRANSACTION t
    JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
    WHERE (t.FromCustomerID = p_customer_id OR t.ToCustomerID = p_customer_id)
    AND DATE(t.TransactionDate) BETWEEN p_start_date AND p_end_date
    AND t.TransactionStatus IN ('Completed', 'Processing')
    ORDER BY t.TransactionDate DESC;
END$$

DROP PROCEDURE IF EXISTS sp_transfer_money$$
CREATE PROCEDURE sp_transfer_money(
    IN p_from_account BIGINT,
    IN p_to_account BIGINT,
    IN p_amount DECIMAL(15,2),
    IN p_description VARCHAR(255),
    OUT p_result VARCHAR(100),
    OUT p_transaction_id BIGINT
)
BEGIN
    DECLARE v_from_balance DECIMAL(15,2);
    DECLARE v_from_customer INT;
    DECLARE v_to_customer INT;
    DECLARE v_from_status VARCHAR(20);
    DECLARE v_to_status VARCHAR(20);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'ERROR: Transaction failed';
        SET p_transaction_id = 0;
    END;
    
    START TRANSACTION;
    
    SELECT AvailableBalance, CustomerID, AccountStatus 
    INTO v_from_balance, v_from_customer, v_from_status
    FROM ACCOUNT WHERE AccountNumber = p_from_account FOR UPDATE;
    
    SELECT CustomerID, AccountStatus 
    INTO v_to_customer, v_to_status
    FROM ACCOUNT WHERE AccountNumber = p_to_account FOR UPDATE;
    
    IF v_from_customer IS NULL THEN
        SET p_result = 'ERROR: Source account not found';
        SET p_transaction_id = 0;
        ROLLBACK;
    ELSEIF v_to_customer IS NULL THEN
        SET p_result = 'ERROR: Destination account not found';
        SET p_transaction_id = 0;
        ROLLBACK;
    ELSEIF v_from_status != 'Active' THEN
        SET p_result = 'ERROR: Source account is not active';
        SET p_transaction_id = 0;
        ROLLBACK;
    ELSEIF v_to_status != 'Active' THEN
        SET p_result = 'ERROR: Destination account is not active';
        SET p_transaction_id = 0;
        ROLLBACK;
    ELSEIF p_amount <= 0 THEN
        SET p_result = 'ERROR: Invalid amount';
        SET p_transaction_id = 0;
        ROLLBACK;
    ELSEIF v_from_balance < p_amount THEN
        SET p_result = 'ERROR: Insufficient balance';
        SET p_transaction_id = 0;
        ROLLBACK;
    ELSE
        UPDATE ACCOUNT SET AvailableBalance = AvailableBalance - p_amount 
        WHERE AccountNumber = p_from_account;
        
        UPDATE ACCOUNT SET AvailableBalance = AvailableBalance + p_amount 
        WHERE AccountNumber = p_to_account;
        
        INSERT INTO TRANSACTION (
            TransactionTypeID, TransactionAmount, 
            FromAccountNumber, ToAccountNumber, FromCustomerID, ToCustomerID, 
            Description, TransactionStatus, ReferenceNumber, TransactionDate
        ) VALUES (
            3, p_amount, p_from_account, p_to_account, 
            v_from_customer, v_to_customer, p_description, 'Completed', 
            CONCAT('TXN', UNIX_TIMESTAMP(), FLOOR(RAND()*1000)), NOW()
        );
        
        SET p_transaction_id = LAST_INSERT_ID();
        SET p_result = 'SUCCESS: Transfer completed';
        COMMIT;
    END IF;
END$$

DROP PROCEDURE IF EXISTS sp_apply_monthly_interest$$
CREATE PROCEDURE sp_apply_monthly_interest()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_account BIGINT;
    DECLARE v_balance DECIMAL(15,2);
    DECLARE v_rate DECIMAL(5,2);
    DECLARE v_interest DECIMAL(15,2);
    DECLARE v_customer INT;
    
    DECLARE cur CURSOR FOR 
        SELECT a.AccountNumber, a.AvailableBalance, ap.InterestRate, a.CustomerID
        FROM ACCOUNT a
        JOIN ACCOUNTPRODUCT ap ON a.ProductID = ap.ProductID
        WHERE ap.AccountType = 'Savings' AND a.AccountStatus = 'Active' AND a.AvailableBalance > 0;
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_account, v_balance, v_rate, v_customer;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SET v_interest = ROUND(v_balance * (v_rate / 100) / 12, 2);
        
        IF v_interest > 0 THEN
            UPDATE ACCOUNT SET AvailableBalance = AvailableBalance + v_interest
            WHERE AccountNumber = v_account;
            
            INSERT INTO TRANSACTION (
                TransactionTypeID, TransactionAmount, 
                ToAccountNumber, ToCustomerID, Description, TransactionStatus, ReferenceNumber
            ) VALUES (
                8, v_interest, v_account, v_customer, 
                CONCAT('Monthly Interest @ ', v_rate, '%'), 'Completed',
                CONCAT('INT', DATE_FORMAT(NOW(), '%Y%m'), FLOOR(RAND()*1000))
            );
            
            INSERT INTO NOTIFICATIONS (customer_id, title, message, type)
            VALUES (
                v_customer, 'Interest Credited', 
                CONCAT('Monthly interest of ৳', FORMAT(v_interest, 2), ' has been credited to your account.'),
                'success'
            );
        END IF;
    END LOOP;
    CLOSE cur;
END$$

DROP PROCEDURE IF EXISTS sp_calculate_loan_emi$$
CREATE PROCEDURE sp_calculate_loan_emi(
    IN p_loan_amount DECIMAL(15,2),
    IN p_interest_rate DECIMAL(5,2),
    IN p_tenure_months INT,
    OUT p_emi DECIMAL(15,2),
    OUT p_total_payable DECIMAL(15,2)
)
BEGIN
    SET p_emi = fn_calculate_emi(p_loan_amount, p_interest_rate, p_tenure_months);
    SET p_total_payable = ROUND(p_emi * p_tenure_months, 2);
END$$

DROP PROCEDURE IF EXISTS sp_generate_emi_schedule$$
CREATE PROCEDURE sp_generate_emi_schedule(
    IN p_loan_id INT
)
BEGIN
    DECLARE v_loan_amount DECIMAL(15,2);
    DECLARE v_interest_rate DECIMAL(5,2);
    DECLARE v_tenure_months INT;
    DECLARE v_emi DECIMAL(15,2);
    DECLARE v_balance DECIMAL(15,2);
    DECLARE v_interest DECIMAL(15,2);
    DECLARE v_principal DECIMAL(15,2);
    DECLARE v_monthly_rate DECIMAL(10,6);
    DECLARE v_i INT DEFAULT 1;
    DECLARE v_emi_date DATE;
    
    SELECT loan_amount, interest_rate, tenure_months, emi_amount
    INTO v_loan_amount, v_interest_rate, v_tenure_months, v_emi
    FROM LOANS WHERE loan_id = p_loan_id;
    
    IF v_loan_amount IS NOT NULL THEN
        SET v_balance = v_loan_amount;
        SET v_monthly_rate = v_interest_rate / 12 / 100;
        SET v_emi_date = DATE_ADD(CURDATE(), INTERVAL 1 MONTH);
        
        DELETE FROM LOAN_EMI_SCHEDULE WHERE loan_id = p_loan_id;
        
        WHILE v_i <= v_tenure_months DO
            SET v_interest = ROUND(v_balance * v_monthly_rate, 2);
            SET v_principal = v_emi - v_interest;
            SET v_balance = v_balance - v_principal;
            
            IF v_i = v_tenure_months THEN
                SET v_principal = v_principal + v_balance;
                SET v_balance = 0;
            END IF;
            
            INSERT INTO LOAN_EMI_SCHEDULE (
                loan_id, due_date, emi_amount, principal_amount, 
                interest_amount, outstanding_balance, status
            ) VALUES (
                p_loan_id, v_emi_date, v_emi, v_principal, v_interest, GREATEST(0, v_balance), 'Pending'
            );
            
            SET v_emi_date = DATE_ADD(v_emi_date, INTERVAL 1 MONTH);
            SET v_i = v_i + 1;
        END WHILE;
    END IF;
END$$

-- ============================================================
-- 12. TRIGGERS
-- ============================================================

DROP TRIGGER IF EXISTS trg_prevent_negative_balance$$
CREATE TRIGGER trg_prevent_negative_balance
BEFORE UPDATE ON ACCOUNT
FOR EACH ROW
BEGIN
    IF NEW.AvailableBalance < 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot update: Available balance cannot be negative';
    END IF;
    -- Auto-mark dormant if balance drops to zero
    IF NEW.AvailableBalance <= 0 AND OLD.AvailableBalance > 0 THEN
        SET NEW.AccountStatus = 'Dormant';
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_transaction_audit$$
CREATE TRIGGER trg_transaction_audit
AFTER UPDATE ON ACCOUNT
FOR EACH ROW
BEGIN
    IF OLD.AvailableBalance != NEW.AvailableBalance THEN
        INSERT INTO TRANSACTION_AUDIT (transaction_id, action_type, old_amount, new_amount, changed_by)
        VALUES (NULL, 'BALANCE_UPDATE', OLD.AvailableBalance, NEW.AvailableBalance, USER());
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_large_transaction_notification$$
CREATE TRIGGER trg_large_transaction_notification
AFTER INSERT ON TRANSACTION
FOR EACH ROW
BEGIN
    IF NEW.TransactionAmount >= 100000 THEN
        IF NEW.FromCustomerID IS NOT NULL THEN
            INSERT INTO NOTIFICATIONS (customer_id, title, message, type)
            VALUES (
                NEW.FromCustomerID, 
                'Large Transaction Alert',
                CONCAT('A large debit transaction of ৳', FORMAT(NEW.TransactionAmount, 2), ' was processed (Ref: ', NEW.ReferenceNumber, ').'),
                'warning'
            );
        END IF;
    END IF;
END$$

DROP TRIGGER IF EXISTS trg_audit_customer_update$$
CREATE TRIGGER trg_audit_customer_update
AFTER UPDATE ON CUSTOMER
FOR EACH ROW
BEGIN
    INSERT INTO AUDIT_LOGS (user_id, user_type, action, table_name, record_id, old_data, new_data, ip_address)
    SELECT OLD.CustomerID, 'customer', 'UPDATE', 'CUSTOMER', OLD.CustomerID,
           JSON_OBJECT('FirstName', OLD.FirstName, 'LastName', OLD.LastName, 'Email', OLD.Email, 'Phone', OLD.Phone),
           JSON_OBJECT('FirstName', NEW.FirstName, 'LastName', NEW.LastName, 'Email', NEW.Email, 'Phone', NEW.Phone),
           SUBSTRING_INDEX(USER(), '@', 1);
END$$

DELIMITER ;

-- ============================================================
-- 13. VIEWS
-- ============================================================

DROP VIEW IF EXISTS v_customer_balance_tier;
CREATE VIEW v_customer_balance_tier AS
SELECT 
    c.CustomerID,
    c.FirstName,
    c.LastName,
    a.AccountNumber,
    a.AvailableBalance,
    CASE 
        WHEN a.AvailableBalance >= 500000 THEN 'Platinum'
        WHEN a.AvailableBalance >= 100000 THEN 'Gold'
        WHEN a.AvailableBalance >= 25000 THEN 'Silver'
        ELSE 'Bronze'
    END AS CustomerTier,
    CASE 
        WHEN c.DateOfBirth IS NOT NULL THEN TIMESTAMPDIFF(YEAR, c.DateOfBirth, CURDATE())
        ELSE 0
    END AS Age,
    (SELECT COUNT(*) FROM TRANSACTION t WHERE t.FromCustomerID = c.CustomerID OR t.ToCustomerID = c.CustomerID) AS TotalTransactions,
    COALESCE(k.status, 'unverified') AS KYCStatus
FROM CUSTOMER c
JOIN ACCOUNT a ON c.CustomerID = a.CustomerID
LEFT JOIN KYC_VERIFICATIONS k ON c.CustomerID = k.customer_id
WHERE c.IsActive = 1;

DROP VIEW IF EXISTS v_daily_transaction_summary;
CREATE VIEW v_daily_transaction_summary AS
SELECT 
    DATE(TransactionDate) AS TransactionDate,
    COUNT(*) AS TotalTransactions,
    SUM(CASE WHEN TransactionTypeID = 1 THEN TransactionAmount ELSE 0 END) AS TotalDeposits,
    SUM(CASE WHEN TransactionTypeID = 2 THEN TransactionAmount ELSE 0 END) AS TotalWithdrawals,
    SUM(CASE WHEN TransactionTypeID = 3 THEN TransactionAmount ELSE 0 END) AS TotalTransfers,
    COUNT(CASE WHEN TransactionStatus = 'Failed' THEN 1 END) AS FailedTransactions,
    COUNT(CASE WHEN TransactionStatus = 'Pending' THEN 1 END) AS PendingTransactions
FROM TRANSACTION
GROUP BY DATE(TransactionDate)
ORDER BY TransactionDate DESC;

DROP VIEW IF EXISTS v_account_summary;
CREATE VIEW v_account_summary AS
SELECT 
    a.AccountNumber,
    CONCAT(c.FirstName, ' ', c.LastName) AS CustomerName,
    c.Email,
    c.Phone,
    ap.ProductName,
    ap.AccountType,
    a.AvailableBalance,
    a.AccountStatus,
    b.BranchName,
    CASE 
        WHEN a.AvailableBalance >= 500000 THEN 'Platinum'
        WHEN a.AvailableBalance >= 100000 THEN 'Gold'
        WHEN a.AvailableBalance >= 25000 THEN 'Silver'
        ELSE 'Bronze'
    END AS Tier
FROM ACCOUNT a
JOIN CUSTOMER c ON a.CustomerID = c.CustomerID
JOIN ACCOUNTPRODUCT ap ON a.ProductID = ap.ProductID
JOIN BRANCH b ON a.BranchID = b.BranchID
WHERE c.IsActive = 1;

DROP VIEW IF EXISTS v_loan_summary;
CREATE VIEW v_loan_summary AS
SELECT 
    l.loan_id,
    CONCAT(c.FirstName, ' ', c.LastName) AS CustomerName,
    lp.product_name AS LoanProduct,
    l.loan_amount,
    l.emi_amount,
    l.tenure_months,
    l.status,
    l.application_date,
    COUNT(e.emi_id) AS TotalPaidEMIs,
    COALESCE(SUM(CASE WHEN e.status = 'Paid' THEN e.emi_amount ELSE 0 END), 0) AS TotalPaidAmount,
    (l.loan_amount - COALESCE(SUM(CASE WHEN e.status = 'Paid' THEN e.principal_amount ELSE 0 END), 0)) AS OutstandingPrincipal
FROM LOANS l
JOIN CUSTOMER c ON l.customer_id = c.CustomerID
JOIN LOAN_PRODUCTS lp ON l.loan_product_id = lp.loan_product_id
LEFT JOIN LOAN_EMI_SCHEDULE e ON l.loan_id = e.loan_id
GROUP BY l.loan_id, c.FirstName, c.LastName, lp.product_name, l.loan_amount, l.emi_amount, l.tenure_months, l.status, l.application_date;

DROP VIEW IF EXISTS v_bill_payment_summary;
CREATE VIEW v_bill_payment_summary AS
SELECT 
    bp.bill_payment_id,
    CONCAT(c.FirstName, ' ', c.LastName) AS CustomerName,
    bc.category_name AS BillCategory,
    bpr.provider_name AS ProviderName,
    bp.account_number,
    bp.amount,
    bp.payment_date,
    bp.status,
    bp.reference_number
FROM BILL_PAYMENTS bp
JOIN CUSTOMER c ON bp.customer_id = c.CustomerID
JOIN BILL_PROVIDERS bpr ON bp.provider_id = bpr.provider_id
JOIN BILL_CATEGORIES bc ON bpr.category_id = bc.category_id
WHERE bp.status != 'Cancelled';

-- ============================================================
-- 14. SEED DATA
-- Default password for all demo users is: password
-- Bcrypt Hash: $2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi
-- ============================================================

INSERT INTO ZONE (ZoneName) VALUES 
('Dhaka Division'), 
('Chittagong Division'), 
('Sylhet Division'), 
('Khulna Division'),
('Rajshahi Division');

INSERT INTO REGION (RegionName, ZoneID) VALUES 
('Dhaka Central Metro', 1), 
('Chittagong Metro', 2), 
('Sylhet Metro', 3), 
('Khulna Metro', 4),
('Rajshahi Metro', 5);

INSERT INTO BRANCH (BranchName, IFSCCode, Address, City, RegionID, Phone, Email) VALUES
('Motijheel Corporate Head Branch', 'ASHA0001001', '71 Motijheel C/A', 'Dhaka', 1, '+880 2 9560001', 'motijheel@ashabank.com.bd'),
('Gulshan Premium Branch',          'ASHA0001002', 'Road 11, Block D, Banani', 'Dhaka', 1, '+880 2 9880002', 'gulshan@ashabank.com.bd'),
('Agrabad Commercial Branch',        'ASHA0002001', 'Agrabad Commercial Area', 'Chittagong', 2, '+880 31 712001', 'agrabad@ashabank.com.bd'),
('Zindabazar Metro Branch',         'ASHA0003001', 'East Zindabazar', 'Sylhet', 3, '+880 821 721001', 'sylhet@ashabank.com.bd'),
('KDA Avenue Branch',               'ASHA0004001', '12 KDA Avenue', 'Khulna', 4, '+880 41 720001', 'khulna@ashabank.com.bd');

INSERT INTO DEPARTMENT (DepartmentName) VALUES 
('Retail Banking'), 
('Corporate Banking'), 
('Customer Experience'), 
('Credit & Loans'), 
('Information Technology & Security'),
('Compliance & Audit');

INSERT INTO DESIGNATION (DesignationName) VALUES 
('Branch Manager'), 
('Senior Relationship Manager'), 
('Customer Service Officer'), 
('Senior Teller'), 
('IT System Administrator');

INSERT INTO EMPLOYEE (FirstName, LastName, Email, Phone, DepartmentID, DesignationID, BranchID, HireDate, Salary, IsActive) VALUES
('Tariqul', 'Islam',  'tariqul@ashabank.com.bd', '01710000001', 1, 1, 1, '2016-03-01', 95000.00, 1),
('Fatema',  'Zohra',  'fatema@ashabank.com.bd',  '01710000002', 2, 2, 2, '2018-05-15', 75000.00, 1),
('Kamrul',  'Hasan',  'kamrul@ashabank.com.bd',  '01710000003', 3, 3, 3, '2020-01-10', 50000.00, 1),
('Shakil',  'Ahmed',  'shakil@ashabank.com.bd',  '01710000004', 4, 2, 1, '2019-07-20', 65000.00, 1),
('Nusrat',  'Jahan',  'nusrat@ashabank.com.bd',  '01710000005', 5, 5, 1, '2021-02-01', 70000.00, 1);

UPDATE BRANCH SET ManagerEmployeeID = 1 WHERE BranchID = 1;
UPDATE BRANCH SET ManagerEmployeeID = 2 WHERE BranchID = 2;
UPDATE BRANCH SET ManagerEmployeeID = 3 WHERE BranchID = 3;

-- Admin Users (Password: password)
INSERT INTO ADMIN_USER (Username, PasswordHash, Role, EmployeeID, IsActive) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 1, 1),
('manager_tariq', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 1, 1);

-- Staff Users (Password: password)
INSERT INTO STAFF (first_name, last_name, email, phone, username, password_hash, role, department, join_date, is_active) VALUES
('Rajesh', 'Sharma', 'rajesh@ashabank.com.bd', '01710000011', 'rajesh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'Operations', '2018-01-01', 1),
('Fatema', 'Zohra',  'fatema_staff@ashabank.com.bd', '01710000012', 'fatema', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer', 'Customer Support', '2020-06-01', 1);

-- Customer Categories
INSERT INTO CUSTOMERCATEGORY (CategoryName) VALUES 
('Regular Individual'), 
('Priority Banking'), 
('Senior Citizen'), 
('Student Account'),
('Corporate Business');

-- Sample Customers
INSERT INTO CUSTOMER (FirstName, LastName, DateOfBirth, Gender, Email, Phone, Address, City, NationalID, CustomerCategoryID, PrimaryBranchID, CreatedAt, IsActive, ReferralCode) VALUES
('Arjun', 'Kapoor', '1992-05-15', 'Male', 'arjun.kapoor@gmail.com', '01710000101', '42 Gulshan Avenue, Road 71', 'Dhaka', '1992269548100001', 2, 1, NOW(), 1, 'REF1001'),
('Sanya', 'Malhotra', '1995-11-22', 'Female', 'sanya.malhotra@gmail.com', '01710000102', '8 GEC Circle, Nasirabad', 'Chittagong', '1995269548100002', 1, 3, NOW(), 1, 'REF1002'),
('Kiran', 'Bose', '1980-03-08', 'Female', 'kiran.bose@gmail.com', '01710000103', '15 Sonadanga R/A', 'Khulna', '1980269548100003', 2, 5, NOW(), 1, 'REF1003'),
('Tanvir', 'Ahmed', '1998-08-14', 'Male', 'tanvir.ahmed@gmail.com', '01710000104', 'House 22, Road 4, Dhanmondi', 'Dhaka', '1998269548100004', 1, 1, NOW(), 1, 'REF1004'),
('Nadia', 'Islam', '1994-04-18', 'Female', 'nadia.islam@gmail.com', '01710000105', 'Shahjalal Upashohor, Sector B', 'Sylhet', '1994269548100005', 4, 4, NOW(), 1, 'REF1005');

-- Digital Banking Logins (Password: password)
INSERT INTO DIGITALBANKINGUSER (CustomerID, Username, PasswordHash, IsActive, CreatedAt) VALUES
(1, 'arjun.kapoor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(2, 'sanya.malhotra', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(3, 'kiran.bose', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(4, 'tanvir.ahmed', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(5, 'nadia.islam', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW());

-- Account Products
INSERT INTO ACCOUNTPRODUCT (ProductName, AccountType, InterestRate, MinBalance, MaxWithdrawalLimit, DailyTransferLimit, Description) VALUES
('Asha Classic Savings', 'Savings', 4.50, 1000.00, 200000.00, 500000.00, 'Everyday digital savings account with free debit card and 24/7 internet banking.'),
('Asha Platinum High-Yield', 'Savings', 6.00, 10000.00, 500000.00, 1000000.00, 'Premium account for high balances with tiered interest and airport lounge perks.'),
('Asha Business Current Pro', 'Current', 0.00, 5000.00, 2000000.00, 5000000.00, 'Seamless corporate transactions with zero charges on digital bulk salary payouts.'),
('Asha Student Smart Account', 'Student', 5.00, 100.00, 50000.00, 100000.00, 'Zero balance maintenance and subsidized fee for students aged 18 to 25.'),
('Asha Term Deposit (FDR)', 'Fixed Deposit', 8.50, 50000.00, 0.00, 0.00, 'Guaranteed return investment scheme with monthly or maturity profit payout.');

-- Accounts
INSERT INTO ACCOUNT (AccountNumber, ProductID, CustomerID, BranchID, OpeningDate, AvailableBalance, AccountStatus) VALUES
(10000000001, 1, 1, 1, '2023-01-15', 78450.50, 'Active'),
(10000000002, 1, 2, 3, '2023-02-20', 145200.00, 'Active'),
(10000000003, 2, 3, 5, '2022-11-01', 485000.00, 'Active'),
(10000000004, 1, 4, 1, '2023-05-10', 32000.00, 'Active'),
(10000000005, 4, 5, 4, '2023-09-01', 18500.00, 'Active');

-- Cards
INSERT INTO CARDS (CardNumber, CustomerID, AccountNumber, CardHolderName, ExpiryDate, CVV, CardType, CardStatus, DailyLimit) VALUES
('4532123456789012', 1, 10000000001, 'ARJUN KAPOOR', DATE_ADD(CURDATE(), INTERVAL 5 YEAR), '123', 'Debit', 'Active', 200000.00),
('4532987654321098', 2, 10000000002, 'SANYA MALHOTRA', DATE_ADD(CURDATE(), INTERVAL 5 YEAR), '456', 'Debit', 'Active', 200000.00),
('4532111122223333', 3, 10000000003, 'KIRAN BOSE', DATE_ADD(CURDATE(), INTERVAL 5 YEAR), '789', 'Credit', 'Active', 500000.00),
('4532444455556666', 4, 10000000004, 'TANVIR AHMED', DATE_ADD(CURDATE(), INTERVAL 5 YEAR), '321', 'Debit', 'Active', 100000.00),
('4532777788889999', 5, 10000000005, 'NADIA ISLAM', DATE_ADD(CURDATE(), INTERVAL 5 YEAR), '654', 'Debit', 'Active', 50000.00);

-- Nominees
INSERT INTO NOMINEE (CustomerID, NomineeName, NomineeRelation, NomineePhone, NomineeDOB, NomineeAddress) VALUES
(1, 'Sita Kapoor', 'Spouse', '01710000201', '1994-02-10', '42 Gulshan Avenue, Dhaka'),
(2, 'Raj Malhotra', 'Father', '01710000202', '1965-08-25', '8 GEC Circle, Chittagong'),
(3, 'Debabrata Bose', 'Spouse', '01710000203', '1976-12-04', '15 Sonadanga, Khulna');

-- Transaction Types
INSERT INTO TRANSACTIONTYPE (TypeName, Description, Fee) VALUES
('Deposit', 'Cash or digital fund deposit into bank account', 0.00),
('Withdrawal', 'ATM or branch counter cash withdrawal', 0.00),
('Transfer', 'Inter-bank or intra-bank instant money transfer', 0.00),
('BEFTN / NPSB', 'Electronic funds clearance transfer', 0.00),
('Mobile Wallet Link', 'Transfer to bKash / Nagad / Upay wallet', 5.00),
('Bill Payment', 'Utility, education, or municipal bill payment', 0.00),
('Loan EMI Payment', 'Scheduled monthly loan installment payment', 0.00),
('Interest Credit', 'Quarterly or monthly savings interest credit', 0.00);

-- Sample Transactions
INSERT INTO TRANSACTION (TransactionTypeID, TransactionAmount, TransactionDate, FromAccountNumber, ToAccountNumber, FromCustomerID, ToCustomerID, Description, TransactionStatus, ReferenceNumber) VALUES
(1, 50000.00, DATE_SUB(NOW(), INTERVAL 14 DAY), NULL, 10000000001, NULL, 1, 'Initial Account Opening Deposit', 'Completed', 'DEP-10001'),
(3, 12000.00, DATE_SUB(NOW(), INTERVAL 10 DAY), 10000000001, 10000000002, 1, 2, 'Funds Transfer to Sanya', 'Completed', 'TXN-20001'),
(2, 5000.00, DATE_SUB(NOW(), INTERVAL 7 DAY), 10000000002, NULL, 2, NULL, 'ATM Cash Withdrawal (Motijheel)', 'Completed', 'WDL-30001'),
(1, 150000.00, DATE_SUB(NOW(), INTERVAL 5 DAY), NULL, 10000000003, NULL, 3, 'Business Dividend Deposit', 'Completed', 'DEP-10002'),
(3, 15000.00, DATE_SUB(NOW(), INTERVAL 3 DAY), 10000000003, 10000000004, 3, 4, 'Consulting Payment to Tanvir', 'Completed', 'TXN-20002'),
(6, 3250.00, DATE_SUB(NOW(), INTERVAL 2 DAY), 10000000001, NULL, 1, NULL, 'Electricity Bill Payment - DESCO', 'Completed', 'BIL-40001'),
(8, 450.50, DATE_SUB(NOW(), INTERVAL 1 DAY), NULL, 10000000001, NULL, 1, 'Monthly Interest Credited @ 4.5%', 'Completed', 'INT-50001');

-- Bill Categories & Providers
INSERT INTO BILL_CATEGORIES (category_name, category_icon) VALUES
('Electricity', 'fa-bolt'),
('Water & Sanitation', 'fa-tint'),
('Gas Utility', 'fa-fire'),
('Mobile Recharge & Postpaid', 'fa-mobile-alt'),
('Internet & Broadband', 'fa-wifi'),
('Insurance & DPS', 'fa-shield-alt');

INSERT INTO BILL_PROVIDERS (category_id, provider_name, provider_code) VALUES
(1, 'DESCO (Dhaka Electric Supply Company)', 'DESCO'),
(1, 'DPDC (Dhaka Power Distribution Co)', 'DPDC'),
(1, 'NESCO (Northern Electricity Supply)', 'NESCO'),
(2, 'DWASA (Dhaka Water Supply)', 'DWASA'),
(2, 'CWASA (Chittagong WASA)', 'CWASA'),
(3, 'Titas Gas Transmission', 'TITAS'),
(3, 'Karnaphuli Gas Distribution', 'KGDCL'),
(4, 'Grameenphone Postpaid', 'GP'),
(4, 'Robi Axiata', 'ROBI'),
(4, 'Banglalink', 'BL'),
(5, 'AmberIT High-Speed Fiber', 'AMBER'),
(6, 'MetLife Bangladesh', 'METLIFE');

-- Sample Bill Payment
INSERT INTO BILL_PAYMENTS (customer_id, from_account, provider_id, account_number, amount, fee, total_amount, payment_date, status, reference_number) VALUES
(1, 10000000001, 1, 'DESCO-99882211', 3250.00, 0.00, 3250.00, DATE_SUB(NOW(), INTERVAL 2 DAY), 'Completed', 'BP-887701');

-- Loan Products
INSERT INTO LOAN_PRODUCTS (product_name, loan_type, min_amount, max_amount, min_tenure_months, max_tenure_months, interest_rate, processing_fee, is_active) VALUES
('Asha Dream Home Loan', 'Home', 500000.00, 20000000.00, 60, 300, 8.50, 5000.00, 1),
('Asha Express Personal Loan', 'Personal', 50000.00, 1500000.00, 12, 60, 10.50, 1000.00, 1),
('Asha Auto & EV Loan', 'Car', 300000.00, 5000000.00, 24, 72, 9.25, 3000.00, 1),
('Asha Higher Education Loan', 'Education', 100000.00, 3000000.00, 12, 84, 7.50, 1500.00, 1),
('Asha SME Shujog Loan', 'SME', 200000.00, 10000000.00, 12, 84, 8.75, 2500.00, 1);

-- Sample Loan
INSERT INTO LOANS (customer_id, loan_product_id, loan_amount, tenure_months, interest_rate, emi_amount, status, application_date, approval_date) VALUES
(1, 2, 200000.00, 24, 10.50, 9270.00, 'Active', DATE_SUB(NOW(), INTERVAL 3 MONTH), DATE_SUB(NOW(), INTERVAL 80 DAY));

-- Generate sample EMI records
INSERT INTO LOAN_EMI_SCHEDULE (loan_id, due_date, emi_amount, principal_amount, interest_amount, outstanding_balance, status, paid_date) VALUES
(1, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 9270.00, 7520.00, 1750.00, 192480.00, 'Paid', DATE_SUB(CURDATE(), INTERVAL 2 MONTH)),
(1, DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 9270.00, 7585.00, 1685.00, 184895.00, 'Paid', DATE_SUB(CURDATE(), INTERVAL 1 MONTH)),
(1, CURDATE(), 9270.00, 7652.00, 1618.00, 177243.00, 'Pending', NULL);

-- KYC
INSERT INTO KYC_VERIFICATIONS (customer_id, nid_number, id_type, status, submitted_at, verified_at, verified_by) VALUES
(1, '1992269548100001', 'National ID Card', 'verified', DATE_SUB(NOW(), INTERVAL 20 DAY), DATE_SUB(NOW(), INTERVAL 19 DAY), 1),
(2, '1995269548100002', 'Smart NID', 'verified', DATE_SUB(NOW(), INTERVAL 15 DAY), DATE_SUB(NOW(), INTERVAL 14 DAY), 1),
(3, '1980269548100003', 'Passport', 'verified', DATE_SUB(NOW(), INTERVAL 30 DAY), DATE_SUB(NOW(), INTERVAL 29 DAY), 1),
(4, '1998269548100004', 'National ID Card', 'pending', DATE_SUB(NOW(), INTERVAL 2 DAY), NULL, NULL);

-- Notifications
INSERT INTO NOTIFICATIONS (customer_id, title, message, type, is_read, created_at) VALUES
(1, 'Welcome to Asha Bank Digital!', 'Your internet banking and debit card have been activated. Enjoy seamless banking.', 'success', 0, NOW()),
(1, 'Security Notice', 'Never share your OTP, PIN, or password with anyone, including bank representatives.', 'info', 0, NOW()),
(2, 'Statement Available', 'Your monthly account e-statement is now ready to download from your dashboard.', 'info', 1, DATE_SUB(NOW(), INTERVAL 3 DAY));

-- Feedback
INSERT INTO FEEDBACK (customer_id, subject, message, type, status, created_at) VALUES
(1, 'Impressive Mobile Banking Speed', 'Fund transfers to other banks via NPSB reflect within seconds. Fantastic experience!', 'feedback', 'resolved', DATE_SUB(NOW(), INTERVAL 5 DAY)),
(4, 'Branch Card Collection Query', 'Can I pick up my new physical contactless debit card from the Dhanmondi sub-branch?', 'inquiry', 'pending', NOW());

-- Cheque Book Request
INSERT INTO CHEQUE_BOOK_REQUESTS (customer_id, account_number, leaves_count, status, request_date) VALUES
(1, 10000000001, 25, 'Approved', DATE_SUB(NOW(), INTERVAL 4 DAY));

-- Reward Points
INSERT INTO REWARD_POINTS (customer_id, points_balance, lifetime_earned) VALUES
(1, 1450, 2000),
(2, 620, 800),
(3, 3890, 4500);

SELECT '✅ ASHA BANK DATABASE V2 REWRITTEN AND INITIALIZED SUCCESSFULLY' AS SetupStatus;
