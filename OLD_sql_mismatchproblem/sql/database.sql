-- ============================================================
-- ASHA BANK - COMPLETE ENHANCED DATABASE
-- For DBMS Course - Includes all advanced features
-- Run this entire file in phpMyAdmin or MySQL
-- ============================================================

DROP DATABASE IF EXISTS asha_bank;
CREATE DATABASE asha_bank CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE asha_bank;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. CORE TABLES
-- ============================================================

-- Zone, Region, Branch
CREATE TABLE ZONE (
    ZoneID INT AUTO_INCREMENT PRIMARY KEY,
    ZoneName VARCHAR(100) NOT NULL
);

CREATE TABLE REGION (
    RegionID INT AUTO_INCREMENT PRIMARY KEY,
    RegionName VARCHAR(100) NOT NULL,
    ZoneID INT NOT NULL,
    FOREIGN KEY (ZoneID) REFERENCES ZONE(ZoneID)
);

CREATE TABLE BRANCH (
    BranchID INT AUTO_INCREMENT PRIMARY KEY,
    BranchName VARCHAR(150) NOT NULL,
    IFSCCode VARCHAR(20) NOT NULL UNIQUE,
    Address VARCHAR(255),
    City VARCHAR(100),
    RegionID INT,
    ManagerEmployeeID INT,
    FOREIGN KEY (RegionID) REFERENCES REGION(RegionID)
);

-- Department and Designation
CREATE TABLE DEPARTMENT (
    DepartmentID INT AUTO_INCREMENT PRIMARY KEY,
    DepartmentName VARCHAR(100) NOT NULL
);

CREATE TABLE DESIGNATION (
    DesignationID INT AUTO_INCREMENT PRIMARY KEY,
    DesignationName VARCHAR(100) NOT NULL
);

-- Employee
CREATE TABLE EMPLOYEE (
    EmployeeID INT AUTO_INCREMENT PRIMARY KEY,
    FirstName VARCHAR(100) NOT NULL,
    LastName VARCHAR(100) NOT NULL,
    Email VARCHAR(150) UNIQUE,
    Phone VARCHAR(20),
    DepartmentID INT,
    DesignationID INT,
    BranchID INT,
    ManagerID INT,
    HireDate DATE,
    Salary DECIMAL(12,2),
    IsActive TINYINT(1) DEFAULT 1,
    FOREIGN KEY (DepartmentID) REFERENCES DEPARTMENT(DepartmentID),
    FOREIGN KEY (DesignationID) REFERENCES DESIGNATION(DesignationID),
    FOREIGN KEY (BranchID) REFERENCES BRANCH(BranchID),
    FOREIGN KEY (ManagerID) REFERENCES EMPLOYEE(EmployeeID)
);

ALTER TABLE BRANCH ADD FOREIGN KEY (ManagerEmployeeID) REFERENCES EMPLOYEE(EmployeeID);

-- Admin Users
CREATE TABLE ADMIN_USER (
    AdminID INT AUTO_INCREMENT PRIMARY KEY,
    Username VARCHAR(100) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    Role ENUM('superadmin','manager','teller','support') DEFAULT 'teller',
    EmployeeID INT,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    LastLogin DATETIME,
    IsActive TINYINT(1) DEFAULT 1,
    FOREIGN KEY (EmployeeID) REFERENCES EMPLOYEE(EmployeeID)
);

-- Customer
CREATE TABLE CUSTOMERCATEGORY (
    CategoryID INT AUTO_INCREMENT PRIMARY KEY,
    CategoryName VARCHAR(100) NOT NULL
);

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
    RelationshipManagerID INT,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    IsActive TINYINT(1) DEFAULT 1,
    FOREIGN KEY (CustomerCategoryID) REFERENCES CUSTOMERCATEGORY(CategoryID),
    FOREIGN KEY (PrimaryBranchID) REFERENCES BRANCH(BranchID),
    FOREIGN KEY (RelationshipManagerID) REFERENCES EMPLOYEE(EmployeeID)
);

-- Nominee
CREATE TABLE NOMINEE (
    NomineeID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT NOT NULL,
    NomineeName VARCHAR(150) NOT NULL,
    NomineeRelation VARCHAR(100),
    NomineePhone VARCHAR(20),
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID)
);

-- Account
CREATE TABLE ACCOUNTPRODUCT (
    ProductID INT AUTO_INCREMENT PRIMARY KEY,
    ProductName VARCHAR(150) NOT NULL,
    AccountType ENUM('Savings','Current','Fixed Deposit','Recurring Deposit') NOT NULL,
    InterestRate DECIMAL(5,2) DEFAULT 0.00,
    MinBalance DECIMAL(12,2) DEFAULT 0.00,
    Description TEXT
);

CREATE TABLE ACCOUNT (
    AccountNumber BIGINT PRIMARY KEY,
    ProductID INT NOT NULL,
    CustomerID INT NOT NULL,
    BranchID INT NOT NULL,
    OpeningDate DATE NOT NULL,
    AvailableBalance DECIMAL(15,2) DEFAULT 0.00,
    AccountStatus ENUM('Active','Dormant','Closed','Frozen') DEFAULT 'Active',
    FOREIGN KEY (ProductID) REFERENCES ACCOUNTPRODUCT(ProductID),
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (BranchID) REFERENCES BRANCH(BranchID)
);

-- Cards
CREATE TABLE CARDS (
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

-- Transactions
CREATE TABLE TRANSACTIONTYPE (
    TransactionTypeID INT AUTO_INCREMENT PRIMARY KEY,
    TypeName VARCHAR(100) NOT NULL,
    Description VARCHAR(255)
);

CREATE TABLE TRANSACTION (
    TransactionID BIGINT AUTO_INCREMENT PRIMARY KEY,
    TransactionTypeID INT NOT NULL,
    TransactionAmount DECIMAL(15,2) NOT NULL,
    TransactionDate DATETIME DEFAULT CURRENT_TIMESTAMP,
    FromAccountNumber BIGINT,
    ToAccountNumber BIGINT,
    FromCustomerID INT,
    ToCustomerID INT,
    Description VARCHAR(255),
    TransactionStatus ENUM('Pending','Completed','Failed','Reversed') DEFAULT 'Completed',
    ProcessedBy INT,
    ReferenceNumber VARCHAR(50) UNIQUE,
    FOREIGN KEY (TransactionTypeID) REFERENCES TRANSACTIONTYPE(TransactionTypeID),
    FOREIGN KEY (FromAccountNumber) REFERENCES ACCOUNT(AccountNumber),
    FOREIGN KEY (ToAccountNumber) REFERENCES ACCOUNT(AccountNumber),
    FOREIGN KEY (FromCustomerID) REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (ToCustomerID) REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (ProcessedBy) REFERENCES EMPLOYEE(EmployeeID)
);

-- Digital Banking User
CREATE TABLE DIGITALBANKINGUSER (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT NOT NULL UNIQUE,
    Username VARCHAR(100) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    IsActive TINYINT(1) DEFAULT 1,
    LastLogin DATETIME,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID)
);

-- Notifications
CREATE TABLE NOTIFICATIONS (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
);

-- Feedback
CREATE TABLE FEEDBACK (
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
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
);

-- Staff
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
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Reactivation Requests
CREATE TABLE REACTIVATION_REQUESTS (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') DEFAULT 'pending',
    admin_reply TEXT,
    estimated_timeframe VARCHAR(100),
    reviewed_by INT,
    reviewed_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
);

-- Staff Messages
CREATE TABLE STAFF_MESSAGES (
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
    FOREIGN KEY (staff_id) REFERENCES STAFF(staff_id) ON DELETE CASCADE
);

-- KYC Verifications
CREATE TABLE KYC_VERIFICATIONS (
    kyc_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    nid_number VARCHAR(50),
    phone_number VARCHAR(20),
    verification_code VARCHAR(10),
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    verified_at DATETIME,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 2. INDEXES (For Performance Optimization)
-- ============================================================

-- Single Column Indexes
CREATE INDEX idx_customer_email ON CUSTOMER(Email);
CREATE INDEX idx_customer_phone ON CUSTOMER(Phone);
CREATE INDEX idx_account_balance ON ACCOUNT(AvailableBalance);
CREATE INDEX idx_transaction_date ON TRANSACTION(TransactionDate);
CREATE INDEX idx_transaction_amount ON TRANSACTION(TransactionAmount);
CREATE INDEX idx_notification_created ON NOTIFICATIONS(created_at);
CREATE INDEX idx_feedback_status ON FEEDBACK(status);

-- Composite Indexes
CREATE INDEX idx_transaction_customer_date ON TRANSACTION(FromCustomerID, TransactionDate);
CREATE INDEX idx_account_customer_status ON ACCOUNT(CustomerID, AccountStatus);
CREATE INDEX idx_customer_name ON CUSTOMER(FirstName, LastName);

-- Full-Text Index for search
CREATE FULLTEXT INDEX idx_customer_search ON CUSTOMER(FirstName, LastName, Email, Phone);

-- ============================================================
-- 3. STORED PROCEDURES
-- ============================================================

DELIMITER $$

-- Procedure 1: Get Customer Statement (Date Range)
CREATE PROCEDURE sp_get_customer_statement(
    IN p_customer_id INT,
    IN p_start_date DATE,
    IN p_end_date DATE
)
BEGIN
    SELECT t.TransactionID, tt.TypeName, t.TransactionAmount, 
           t.TransactionDate, t.Description, t.TransactionStatus
    FROM TRANSACTION t
    JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
    WHERE (t.FromCustomerID = p_customer_id OR t.ToCustomerID = p_customer_id)
    AND DATE(t.TransactionDate) BETWEEN p_start_date AND p_end_date
    ORDER BY t.TransactionDate DESC;
END$$

-- Procedure 2: Transfer Money with Validation
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
    END;
    
    START TRANSACTION;
    
    SELECT AvailableBalance, CustomerID, AccountStatus 
    INTO v_from_balance, v_from_customer, v_from_status
    FROM ACCOUNT WHERE AccountNumber = p_from_account FOR UPDATE;
    
    SELECT CustomerID, AccountStatus INTO v_to_customer, v_to_status
    FROM ACCOUNT WHERE AccountNumber = p_to_account FOR UPDATE;
    
    IF v_from_status != 'Active' THEN
        SET p_result = 'ERROR: Source account is not active';
        ROLLBACK;
    ELSEIF v_to_status != 'Active' THEN
        SET p_result = 'ERROR: Destination account is not active';
        ROLLBACK;
    ELSEIF v_from_balance < p_amount THEN
        SET p_result = 'ERROR: Insufficient balance';
        ROLLBACK;
    ELSEIF p_amount <= 0 THEN
        SET p_result = 'ERROR: Invalid amount';
        ROLLBACK;
    ELSE
        UPDATE ACCOUNT SET AvailableBalance = AvailableBalance - p_amount 
        WHERE AccountNumber = p_from_account;
        
        UPDATE ACCOUNT SET AvailableBalance = AvailableBalance + p_amount 
        WHERE AccountNumber = p_to_account;
        
        INSERT INTO TRANSACTION (TransactionTypeID, TransactionAmount, 
            FromAccountNumber, ToAccountNumber, FromCustomerID, ToCustomerID, 
            Description, TransactionStatus, ReferenceNumber, TransactionDate)
        VALUES (3, p_amount, p_from_account, p_to_account, 
                v_from_customer, v_to_customer, p_description, 'Completed', 
                CONCAT('TXN', UNIX_TIMESTAMP(), FLOOR(RAND()*1000)), NOW());
        
        SET p_transaction_id = LAST_INSERT_ID();
        SET p_result = 'SUCCESS: Transfer completed';
        COMMIT;
    END IF;
END$$

-- Procedure 3: Apply Monthly Interest to Savings Accounts
CREATE PROCEDURE sp_apply_monthly_interest()
BEGIN
    DECLARE done INT DEFAULT FALSE;
    DECLARE v_account BIGINT;
    DECLARE v_balance DECIMAL(15,2);
    DECLARE v_rate DECIMAL(5,2);
    DECLARE v_interest DECIMAL(15,2);
    DECLARE cur CURSOR FOR 
        SELECT a.AccountNumber, a.AvailableBalance, ap.InterestRate
        FROM ACCOUNT a
        JOIN ACCOUNTPRODUCT ap ON a.ProductID = ap.ProductID
        WHERE ap.AccountType = 'Savings' AND a.AccountStatus = 'Active';
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_account, v_balance, v_rate;
        IF done THEN
            LEAVE read_loop;
        END IF;
        
        SET v_interest = v_balance * (v_rate / 100) / 12;
        
        UPDATE ACCOUNT SET AvailableBalance = AvailableBalance + v_interest
        WHERE AccountNumber = v_account;
        
        INSERT INTO TRANSACTION (TransactionTypeID, TransactionAmount, 
            ToAccountNumber, ToCustomerID, Description, TransactionStatus, ReferenceNumber)
        SELECT 8, v_interest, v_account, CustomerID, 
               CONCAT('Monthly Interest @ ', v_rate, '%'), 'Completed', 
               CONCAT('INT', DATE_FORMAT(NOW(), '%Y%m'), FLOOR(RAND()*1000))
        FROM ACCOUNT WHERE AccountNumber = v_account;
    END LOOP;
    CLOSE cur;
END$$

-- ============================================================
-- 4. FUNCTIONS
-- ============================================================

-- Function 1: Calculate Age from Date of Birth
CREATE FUNCTION fn_calculate_age(p_dob DATE)
RETURNS INT
DETERMINISTIC
BEGIN
    RETURN TIMESTAMPDIFF(YEAR, p_dob, CURDATE());
END$$

-- Function 2: Get Customer Tier Based on Balance
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

-- Function 3: Format Currency
CREATE FUNCTION fn_format_currency(p_amount DECIMAL(15,2))
RETURNS VARCHAR(50)
DETERMINISTIC
BEGIN
    RETURN CONCAT('৳ ', FORMAT(p_amount, 2));
END$$

-- Function 4: Get Total Customer Transactions
CREATE FUNCTION fn_total_customer_transactions(p_customer_id INT)
RETURNS INT
DETERMINISTIC
BEGIN
    DECLARE v_total INT;
    SELECT COUNT(*) INTO v_total
    FROM TRANSACTION
    WHERE FromCustomerID = p_customer_id OR ToCustomerID = p_customer_id;
    RETURN v_total;
END$$

-- ============================================================
-- 5. TRIGGERS
-- ============================================================

-- Audit table for triggers
CREATE TABLE TRANSACTION_AUDIT (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id BIGINT,
    action_type VARCHAR(20),
    old_amount DECIMAL(15,2),
    new_amount DECIMAL(15,2),
    changed_by VARCHAR(100),
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- Trigger 1: Auto-update Account Status when balance becomes zero
CREATE TRIGGER trg_check_account_status
AFTER UPDATE ON ACCOUNT
FOR EACH ROW
BEGIN
    IF NEW.AvailableBalance <= 0 AND OLD.AvailableBalance > 0 THEN
        UPDATE ACCOUNT SET AccountStatus = 'Dormant' 
        WHERE AccountNumber = NEW.AccountNumber;
        
        INSERT INTO NOTIFICATIONS (customer_id, title, message, type)
        SELECT CustomerID, 'Low Balance Alert', 
               'Your account balance has reached zero. Please deposit to keep account active.',
               'warning'
        FROM ACCOUNT WHERE AccountNumber = NEW.AccountNumber;
    END IF;
END$$

-- Trigger 2: Log all balance changes to audit table
CREATE TRIGGER trg_transaction_audit
AFTER UPDATE ON ACCOUNT
FOR EACH ROW
BEGIN
    IF OLD.AvailableBalance != NEW.AvailableBalance THEN
        INSERT INTO TRANSACTION_AUDIT (transaction_id, action_type, old_amount, new_amount, changed_by)
        VALUES (NULL, 'BALANCE_UPDATE', OLD.AvailableBalance, NEW.AvailableBalance, USER());
    END IF;
END$$

-- Trigger 3: Auto-create notification for large transactions
CREATE TRIGGER trg_large_transaction_notification
AFTER INSERT ON TRANSACTION
FOR EACH ROW
BEGIN
    IF NEW.TransactionAmount > 100000 THEN
        INSERT INTO NOTIFICATIONS (customer_id, title, message, type)
        SELECT NEW.FromCustomerID, 'Large Transaction Alert',
               CONCAT('A large transaction of ', fn_format_currency(NEW.TransactionAmount), 
                      ' was processed from your account.'),
               'warning'
        WHERE NEW.FromCustomerID IS NOT NULL;
    END IF;
END$$

-- Trigger 4: Prevent negative balance
CREATE TRIGGER trg_prevent_negative_balance
BEFORE UPDATE ON ACCOUNT
FOR EACH ROW
BEGIN
    IF NEW.AvailableBalance < 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot update: Balance cannot be negative';
    END IF;
END$$

-- ============================================================
-- 6. VIEWS FOR REPORTING
-- ============================================================

-- View 1: Customer Balance Summary with Tier
CREATE VIEW v_customer_balance_tier AS
SELECT 
    c.CustomerID,
    c.FirstName,
    c.LastName,
    a.AccountNumber,
    a.AvailableBalance,
    fn_get_customer_tier(a.AvailableBalance) AS CustomerTier,
    fn_calculate_age(c.DateOfBirth) AS Age,
    fn_total_customer_transactions(c.CustomerID) AS TotalTransactions
FROM CUSTOMER c
JOIN ACCOUNT a ON c.CustomerID = a.CustomerID
WHERE c.IsActive = 1;

-- View 2: Daily Transaction Summary
CREATE VIEW v_daily_transaction_summary AS
SELECT 
    DATE(TransactionDate) AS TransactionDate,
    COUNT(*) AS TotalTransactions,
    SUM(CASE WHEN TransactionTypeID = 1 THEN TransactionAmount ELSE 0 END) AS TotalDeposits,
    SUM(CASE WHEN TransactionTypeID = 2 THEN TransactionAmount ELSE 0 END) AS TotalWithdrawals,
    SUM(CASE WHEN TransactionTypeID = 3 THEN TransactionAmount ELSE 0 END) AS TotalTransfers,
    COUNT(CASE WHEN TransactionStatus = 'Failed' THEN 1 END) AS FailedTransactions
FROM TRANSACTION
GROUP BY DATE(TransactionDate)
ORDER BY TransactionDate DESC;

-- View 3: Account Summary
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
    b.BranchName
FROM ACCOUNT a
JOIN CUSTOMER c ON a.CustomerID = c.CustomerID
JOIN ACCOUNTPRODUCT ap ON a.ProductID = ap.ProductID
JOIN BRANCH b ON a.BranchID = b.BranchID;

-- ============================================================
-- 7. SAMPLE DATA (Seed Data)
-- ============================================================

-- Zones
INSERT INTO ZONE (ZoneName) VALUES ('Dhaka Division'), ('Chittagong Division'), ('Khulna Division');

-- Regions
INSERT INTO REGION (RegionName, ZoneID) VALUES ('Dhaka Metro',1), ('Chittagong Metro',2), ('Khulna Metro',3);

-- Branches
INSERT INTO BRANCH (BranchName, IFSCCode, Address, City, RegionID) VALUES
('Dhaka Main Branch','ASHA0001001','Motijheel C/A','Dhaka',1),
('Chittagong Branch','ASHA0002001','Agrabad C/A','Chittagong',2),
('Khulna Branch','ASHA0003001','KDA Avenue','Khulna',3);

-- Departments
INSERT INTO DEPARTMENT (DepartmentName) VALUES ('Retail Banking'), ('Operations'), ('Customer Service');

-- Designations
INSERT INTO DESIGNATION (DesignationName) VALUES ('Branch Manager'), ('Senior Officer'), ('Officer');

-- Employees
INSERT INTO EMPLOYEE (FirstName, LastName, Email, Phone, DepartmentID, DesignationID, BranchID, HireDate, Salary, IsActive) VALUES
('Rajesh', 'Sharma', 'rajesh@ashabank.bd', '01710000001', 1, 1, 1, '2015-03-01', 85000.00, 1),
('Priya', 'Mehta', 'priya@ashabank.bd', '01710000002', 2, 2, 2, '2016-06-15', 55000.00, 1),
('Amit', 'Verma', 'amit@ashabank.bd', '01710000003', 3, 3, 3, '2018-01-10', 45000.00, 1);

-- Set branch managers
UPDATE BRANCH SET ManagerEmployeeID=1 WHERE BranchID=1;
UPDATE BRANCH SET ManagerEmployeeID=2 WHERE BranchID=2;
UPDATE BRANCH SET ManagerEmployeeID=3 WHERE BranchID=3;

-- Admin User (Password: Admin@123)
INSERT INTO ADMIN_USER (Username, PasswordHash, Role, EmployeeID, IsActive) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 1, 1);

-- Customer Categories
INSERT INTO CUSTOMERCATEGORY (CategoryName) VALUES ('Regular'), ('Premium'), ('Senior Citizen'), ('Student');

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

-- Accounts
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
(1, 5000.00, NOW(), NULL, 10000000001, NULL, 1, 'Initial Deposit', 'Completed', 'DEP001'),
(3, 10000.00, NOW(), 10000000001, 10000000002, 1, 2, 'Personal Transfer', 'Completed', 'TXN001'),
(2, 2000.00, NOW(), 10000000002, NULL, 2, NULL, 'ATM Withdrawal', 'Completed', 'WDL001'),
(1, 25000.00, NOW(), NULL, 10000000003, NULL, 3, 'Salary Credit', 'Completed', 'DEP002');

-- Digital Banking Users (Password: 'password')
INSERT INTO DIGITALBANKINGUSER (CustomerID, Username, PasswordHash, IsActive, CreatedAt) VALUES
(1, 'arjun.kapoor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(2, 'sanya.malhotra', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(3, 'kiran.bose', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW());

-- Staff
INSERT INTO STAFF (first_name, last_name, email, phone, username, password_hash, role, department, join_date, is_active) VALUES
('Rajesh', 'Sharma', 'rajesh@ashabank.bd', '01710000001', 'rajesh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'Operations', CURDATE(), 1),
('Priya', 'Mehta', 'priya@ashabank.bd', '01710000002', 'priya', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'officer', 'Customer Service', CURDATE(), 1);

-- Sample Notifications
INSERT INTO NOTIFICATIONS (customer_id, title, message, type, created_at) VALUES
(1, 'Welcome to Asha Bank!', 'Thank you for joining Asha Bank. We are delighted to have you.', 'success', NOW()),
(2, 'KYC Verification Required', 'Please complete your KYC verification to activate all features.', 'warning', NOW());

-- Sample Feedback
INSERT INTO FEEDBACK (customer_id, subject, message, type, status, created_at) VALUES
(1, 'Great Service', 'The mobile banking app is very user friendly!', 'feedback', 'pending', NOW()),
(2, 'Transaction Issue', 'My transaction is pending for 2 hours.', 'complaint', 'pending', NOW());

-- KYC Verifications
INSERT INTO KYC_VERIFICATIONS (customer_id, nid_number, status, submitted_at) VALUES
(1, '12345678901234567', 'pending', NOW()),
(2, '98765432109876543', 'verified', NOW());

-- ============================================================
-- 8. DEMONSTRATION QUERIES (For testing)
-- ============================================================

-- Test Stored Procedure
CALL sp_get_customer_statement(1, '2024-01-01', '2024-12-31');

-- Test Functions
SELECT fn_format_currency(50000) AS FormattedAmount;
SELECT fn_get_customer_tier(750000) AS CustomerTier;
SELECT fn_calculate_age('1990-05-15') AS Age;
SELECT fn_total_customer_transactions(1) AS TotalTransactions;

-- Test Views
SELECT * FROM v_customer_balance_tier;
SELECT * FROM v_daily_transaction_summary;
SELECT * FROM v_account_summary;

-- ============================================================
-- FINAL OUTPUT
-- ============================================================
SELECT '=========================================' AS '';
SELECT '✅ ASHA BANK COMPLETE DATABASE SETUP!' AS '';
SELECT '=========================================' AS '';
SELECT '📊 DBMS Course Features Included:' AS '';
SELECT '-----------------------------------------' AS '';
SELECT '✓ 20+ Tables with Proper Relationships' AS '';
SELECT '✓ Primary Keys & Foreign Keys' AS '';
SELECT '✓ Indexes (Single, Composite, Full-text)' AS '';
SELECT '✓ Stored Procedures (3 procedures)' AS '';
SELECT '✓ Functions (4 functions)' AS '';
SELECT '✓ Triggers (4 triggers with audit table)' AS '';
SELECT '✓ Views (3 views for reporting)' AS '';
SELECT '✓ Transactions with ACID properties' AS '';
SELECT '✓ Sample Data for Testing' AS '';
SELECT '=========================================' AS '';
SELECT '🔑 Login Credentials:' AS '';
SELECT '-----------------------------------------' AS '';
SELECT 'Admin: username = "admin", password = "Admin@123"' AS '';
SELECT 'Staff: username = "rajesh", password = "password"' AS '';
SELECT 'Client: username = "arjun.kapoor", password = "password"' AS '';
SELECT '=========================================' AS '';