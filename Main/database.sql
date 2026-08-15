-- ============================================================
-- ASHA BANK - COMPLETE DATABASE SCHEMA (WITH NEW TABLES)
-- ============================================================

DROP DATABASE IF EXISTS asha_bank;
CREATE DATABASE asha_bank CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE asha_bank;

SET FOREIGN_KEY_CHECKS = 0;

-- ============================================================
-- 1. GEOGRAPHIC TABLES
-- ============================================================

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
    Latitude DECIMAL(10,8),
    Longitude DECIMAL(11,8),
    FOREIGN KEY (RegionID) REFERENCES REGION(RegionID)
);

-- ============================================================
-- 2. EMPLOYEE TABLES
-- ============================================================

CREATE TABLE DEPARTMENT (
    DepartmentID INT AUTO_INCREMENT PRIMARY KEY,
    DepartmentName VARCHAR(100) NOT NULL
);

CREATE TABLE DESIGNATION (
    DesignationID INT AUTO_INCREMENT PRIMARY KEY,
    DesignationName VARCHAR(100) NOT NULL
);

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

-- ============================================================
-- 3. USER TABLES
-- ============================================================

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
    last_login DATETIME,
    login_attempts INT DEFAULT 0,
    locked_until DATETIME
);

-- ============================================================
-- 4. CUSTOMER TABLES
-- ============================================================

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
    ReferralCode VARCHAR(20) UNIQUE,
    ReferredBy INT,
    FOREIGN KEY (CustomerCategoryID) REFERENCES CUSTOMERCATEGORY(CategoryID),
    FOREIGN KEY (PrimaryBranchID) REFERENCES BRANCH(BranchID),
    FOREIGN KEY (RelationshipManagerID) REFERENCES EMPLOYEE(EmployeeID)
);

CREATE TABLE NOMINEE (
    NomineeID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT NOT NULL,
    NomineeName VARCHAR(150) NOT NULL,
    NomineeRelation VARCHAR(100),
    NomineePhone VARCHAR(20),
    NomineeDOB DATE,
    NomineeAddress VARCHAR(255),
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID)
);

-- ============================================================
-- 5. ACCOUNT TABLES
-- ============================================================

CREATE TABLE ACCOUNTPRODUCT (
    ProductID INT AUTO_INCREMENT PRIMARY KEY,
    ProductName VARCHAR(150) NOT NULL,
    AccountType ENUM('Savings','Current','Fixed Deposit','Recurring Deposit') NOT NULL,
    InterestRate DECIMAL(5,2) DEFAULT 0.00,
    MinBalance DECIMAL(12,2) DEFAULT 0.00,
    MaxWithdrawalLimit DECIMAL(15,2),
    DailyTransferLimit DECIMAL(15,2),
    MonthlyFee DECIMAL(10,2) DEFAULT 0.00,
    Description TEXT,
    IsActive TINYINT(1) DEFAULT 1
);

CREATE TABLE ACCOUNT (
    AccountNumber BIGINT PRIMARY KEY,
    ProductID INT NOT NULL,
    CustomerID INT NOT NULL,
    BranchID INT NOT NULL,
    OpeningDate DATE NOT NULL,
    AvailableBalance DECIMAL(15,2) DEFAULT 0.00,
    AccountStatus ENUM('Active','Dormant','Closed','Frozen') DEFAULT 'Active',
    LastTransactionDate DATETIME,
    IsJoint TINYINT(1) DEFAULT 0,
    FOREIGN KEY (ProductID) REFERENCES ACCOUNTPRODUCT(ProductID),
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (BranchID) REFERENCES BRANCH(BranchID)
);

CREATE TABLE JOINT_ACCOUNT_HOLDERS (
    JointID INT AUTO_INCREMENT PRIMARY KEY,
    AccountNumber BIGINT NOT NULL,
    CustomerID INT NOT NULL,
    Relationship VARCHAR(50),
    IsPrimary TINYINT(1) DEFAULT 0,
    FOREIGN KEY (AccountNumber) REFERENCES ACCOUNT(AccountNumber),
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID)
);

-- ============================================================
-- 6. CARD TABLES
-- ============================================================

CREATE TABLE CARDS (
    CardID INT AUTO_INCREMENT PRIMARY KEY,
    CardNumber VARCHAR(20) NOT NULL UNIQUE,
    CustomerID INT NOT NULL,
    AccountNumber BIGINT,
    ExpiryDate DATE NOT NULL,
    CVV VARCHAR(4) NOT NULL,
    CardType ENUM('Debit','Credit','Prepaid') DEFAULT 'Debit',
    CardStatus ENUM('Active','Blocked','Expired','Lost','Stolen') DEFAULT 'Active',
    DailyLimit DECIMAL(15,2) DEFAULT 500000,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (AccountNumber) REFERENCES ACCOUNT(AccountNumber)
);

-- ============================================================
-- 7. TRANSACTION TABLES
-- ============================================================

CREATE TABLE TRANSACTIONTYPE (
    TransactionTypeID INT AUTO_INCREMENT PRIMARY KEY,
    TypeName VARCHAR(100) NOT NULL,
    Description VARCHAR(255),
    Fee DECIMAL(10,2) DEFAULT 0.00
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
    TransactionStatus ENUM('Pending','Processing','Completed','Failed','Reversed','Cancelled') DEFAULT 'Completed',
    ProcessedBy INT,
    ReferenceNumber VARCHAR(50) UNIQUE,
    FailureReason TEXT,
    Remarks TEXT,
    FOREIGN KEY (TransactionTypeID) REFERENCES TRANSACTIONTYPE(TransactionTypeID),
    FOREIGN KEY (FromAccountNumber) REFERENCES ACCOUNT(AccountNumber),
    FOREIGN KEY (ToAccountNumber) REFERENCES ACCOUNT(AccountNumber),
    FOREIGN KEY (FromCustomerID) REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (ToCustomerID) REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (ProcessedBy) REFERENCES EMPLOYEE(EmployeeID)
);

-- ============================================================
-- 8. TRANSACTION LIMITS
-- ============================================================

CREATE TABLE TRANSACTION_LIMITS (
    LimitID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT NOT NULL,
    TransactionTypeID INT NOT NULL,
    DailyLimit DECIMAL(15,2),
    MonthlyLimit DECIMAL(15,2),
    PerTransactionLimit DECIMAL(15,2),
    CurrentDayUsage DECIMAL(15,2) DEFAULT 0.00,
    CurrentMonthUsage DECIMAL(15,2) DEFAULT 0.00,
    LastResetDate DATE,
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (TransactionTypeID) REFERENCES TRANSACTIONTYPE(TransactionTypeID)
);

-- ============================================================
-- 9. DIGITAL BANKING
-- ============================================================

CREATE TABLE DIGITALBANKINGUSER (
    UserID INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID INT NOT NULL UNIQUE,
    Username VARCHAR(100) NOT NULL UNIQUE,
    PasswordHash VARCHAR(255) NOT NULL,
    IsActive TINYINT(1) DEFAULT 1,
    LastLogin DATETIME,
    LoginAttempts INT DEFAULT 0,
    LockedUntil DATETIME,
    CreatedAt DATETIME DEFAULT CURRENT_TIMESTAMP,
    PasswordResetToken VARCHAR(255),
    PasswordResetExpiry DATETIME,
    SecurityQuestion1 VARCHAR(200),
    SecurityAnswer1 VARCHAR(200),
    SecurityQuestion2 VARCHAR(200),
    SecurityAnswer2 VARCHAR(200),
    FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID)
);

-- ============================================================
-- 10. NOTIFICATIONS
-- ============================================================

CREATE TABLE NOTIFICATIONS (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    read_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    link VARCHAR(255),
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
);

-- ============================================================
-- 11. FEEDBACK
-- ============================================================

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

-- ============================================================
-- 12. KYC VERIFICATION
-- ============================================================

CREATE TABLE KYC_VERIFICATIONS (
    kyc_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    nid_number VARCHAR(50),
    phone_number VARCHAR(20),
    verification_code VARCHAR(10),
    code_expiry DATETIME,
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    verified_by INT,
    verified_at DATETIME,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    document_front_path VARCHAR(255),
    document_back_path VARCHAR(255),
    selfie_path VARCHAR(255),
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
);

-- ============================================================
-- 13. REACTIVATION
-- ============================================================

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

-- ============================================================
-- 14. STAFF MESSAGES
-- ============================================================

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

-- ============================================================
-- 15. LOAN MANAGEMENT (NEW)
-- ============================================================

CREATE TABLE LOAN_PRODUCTS (
    loan_product_id INT AUTO_INCREMENT PRIMARY KEY,
    product_name VARCHAR(100) NOT NULL,
    loan_type ENUM('Home','Personal','Car','Education','Business','Gold') NOT NULL,
    min_amount DECIMAL(15,2) NOT NULL,
    max_amount DECIMAL(15,2) NOT NULL,
    min_tenure_months INT NOT NULL,
    max_tenure_months INT NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    processing_fee DECIMAL(10,2) DEFAULT 0.00,
    late_fee_percentage DECIMAL(5,2) DEFAULT 2.00,
    is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE LOANS (
    loan_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    loan_product_id INT NOT NULL,
    application_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    loan_amount DECIMAL(15,2) NOT NULL,
    tenure_months INT NOT NULL,
    interest_rate DECIMAL(5,2) NOT NULL,
    emi_amount DECIMAL(15,2) NOT NULL,
    total_payable DECIMAL(15,2) NOT NULL,
    status ENUM('Pending','Approved','Rejected','Active','Closed','Defaulted') DEFAULT 'Pending',
    approved_by INT,
    approved_date DATETIME,
    disbursement_account BIGINT,
    disbursement_date DATE,
    first_emi_date DATE,
    last_emi_date DATE,
    purpose TEXT,
    collateral_type VARCHAR(50),
    collateral_value DECIMAL(15,2),
    rejection_reason TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (loan_product_id) REFERENCES LOAN_PRODUCTS(loan_product_id),
    FOREIGN KEY (approved_by) REFERENCES EMPLOYEE(EmployeeID),
    FOREIGN KEY (disbursement_account) REFERENCES ACCOUNT(AccountNumber)
);

CREATE TABLE LOAN_EMI_SCHEDULE (
    emi_id INT AUTO_INCREMENT PRIMARY KEY,
    loan_id INT NOT NULL,
    due_date DATE NOT NULL,
    emi_amount DECIMAL(15,2) NOT NULL,
    principal_amount DECIMAL(15,2) NOT NULL,
    interest_amount DECIMAL(15,2) NOT NULL,
    outstanding_balance DECIMAL(15,2) NOT NULL,
    status ENUM('Pending','Paid','Overdue','Partially Paid') DEFAULT 'Pending',
    paid_date DATETIME,
    transaction_id BIGINT,
    late_fee_charged DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (loan_id) REFERENCES LOANS(loan_id),
    FOREIGN KEY (transaction_id) REFERENCES TRANSACTION(TransactionID)
);

-- ============================================================
-- 16. BILL PAYMENT (NEW)
-- ============================================================

CREATE TABLE BILL_CATEGORIES (
    category_id INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    category_icon VARCHAR(50),
    is_active TINYINT(1) DEFAULT 1
);

CREATE TABLE BILL_PROVIDERS (
    provider_id INT AUTO_INCREMENT PRIMARY KEY,
    category_id INT NOT NULL,
    provider_name VARCHAR(100) NOT NULL,
    provider_code VARCHAR(50) NOT NULL UNIQUE,
    is_active TINYINT(1) DEFAULT 1,
    FOREIGN KEY (category_id) REFERENCES BILL_CATEGORIES(category_id)
);

CREATE TABLE BILL_PAYMENTS (
    bill_payment_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    provider_id INT NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    due_date DATE,
    payment_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    reference_number VARCHAR(50) UNIQUE,
    status ENUM('Pending','Processing','Completed','Failed','Cancelled') DEFAULT 'Pending',
    transaction_id BIGINT,
    is_recurring TINYINT(1) DEFAULT 0,
    next_payment_date DATE,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (provider_id) REFERENCES BILL_PROVIDERS(provider_id),
    FOREIGN KEY (transaction_id) REFERENCES TRANSACTION(TransactionID)
);

CREATE TABLE BILL_AUTOPAY_SCHEDULES (
    schedule_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    provider_id INT NOT NULL,
    account_number VARCHAR(50) NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    frequency ENUM('Monthly','Quarterly','Yearly') DEFAULT 'Monthly',
    next_run_date DATE NOT NULL,
    last_run_date DATE,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (provider_id) REFERENCES BILL_PROVIDERS(provider_id)
);

-- ============================================================
-- 17. CHEQUE MANAGEMENT (NEW)
-- ============================================================

CREATE TABLE CHEQUE_BOOK_REQUESTS (
    request_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    account_number BIGINT NOT NULL,
    number_of_leaves INT DEFAULT 50,
    request_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    status ENUM('Pending','Processing','Ready','Delivered','Cancelled') DEFAULT 'Pending',
    processed_by INT,
    processed_at DATETIME,
    delivery_address VARCHAR(255),
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (account_number) REFERENCES ACCOUNT(AccountNumber),
    FOREIGN KEY (processed_by) REFERENCES EMPLOYEE(EmployeeID)
);

CREATE TABLE CHEQUES (
    cheque_id INT AUTO_INCREMENT PRIMARY KEY,
    account_number BIGINT NOT NULL,
    cheque_number VARCHAR(20) NOT NULL UNIQUE,
    issue_date DATE NOT NULL,
    amount DECIMAL(15,2),
    payee_name VARCHAR(150),
    status ENUM('Issued','Presented','Cleared','Bounced','Cancelled','Stopped') DEFAULT 'Issued',
    presented_date DATETIME,
    cleared_date DATETIME,
    bounce_reason TEXT,
    stop_payment_reason TEXT,
    stop_payment_date DATETIME,
    transaction_id BIGINT,
    FOREIGN KEY (account_number) REFERENCES ACCOUNT(AccountNumber),
    FOREIGN KEY (transaction_id) REFERENCES TRANSACTION(TransactionID)
);

-- ============================================================
-- 18. REFERRAL & REWARDS (NEW)
-- ============================================================

CREATE TABLE REFERRALS (
    referral_id INT AUTO_INCREMENT PRIMARY KEY,
    referrer_id INT NOT NULL,
    referred_id INT NOT NULL,
    referral_code VARCHAR(20) NOT NULL,
    status ENUM('Pending','Completed','Rewarded') DEFAULT 'Pending',
    referral_date DATETIME DEFAULT CURRENT_TIMESTAMP,
    completion_date DATETIME,
    reward_amount DECIMAL(10,2) DEFAULT 0.00,
    FOREIGN KEY (referrer_id) REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (referred_id) REFERENCES CUSTOMER(CustomerID)
);

CREATE TABLE REWARD_POINTS (
    reward_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    points_earned INT DEFAULT 0,
    points_used INT DEFAULT 0,
    points_expiry DATE,
    last_updated DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID)
);

CREATE TABLE REWARD_HISTORY (
    history_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    transaction_type ENUM('Earned','Used','Expired','Adjusted') NOT NULL,
    points INT NOT NULL,
    description VARCHAR(255),
    reference_id INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID)
);

-- ============================================================
-- 19. AUDIT LOGS
-- ============================================================

CREATE TABLE AUDIT_LOGS (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    user_type ENUM('admin','staff','customer') NOT NULL,
    action VARCHAR(100) NOT NULL,
    table_name VARCHAR(50),
    record_id INT,
    old_data JSON,
    new_data JSON,
    ip_address VARCHAR(45),
    user_agent VARCHAR(255),
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE TRANSACTION_AUDIT (
    audit_id INT AUTO_INCREMENT PRIMARY KEY,
    transaction_id BIGINT,
    action_type VARCHAR(20),
    old_amount DECIMAL(15,2),
    new_amount DECIMAL(15,2),
    changed_by VARCHAR(100),
    changed_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 20. INDEXES
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
CREATE INDEX idx_transaction_customer_date ON TRANSACTION(FromCustomerID, TransactionDate);
CREATE INDEX idx_notification_customer ON NOTIFICATIONS(customer_id, is_read);
CREATE INDEX idx_notification_created ON NOTIFICATIONS(created_at);
CREATE INDEX idx_feedback_customer ON FEEDBACK(customer_id, status);
CREATE INDEX idx_feedback_status ON FEEDBACK(status);
CREATE INDEX idx_loan_customer ON LOANS(customer_id, status);
CREATE INDEX idx_emi_loan ON LOAN_EMI_SCHEDULE(loan_id, status);
CREATE INDEX idx_cheque_account ON CHEQUES(account_number, status);
CREATE INDEX idx_bill_customer ON BILL_PAYMENTS(customer_id, status);

CREATE FULLTEXT INDEX idx_customer_search ON CUSTOMER(FirstName, LastName, Email, Phone);
CREATE FULLTEXT INDEX idx_transaction_description ON TRANSACTION(Description);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- 21. STORED PROCEDURES
-- ============================================================

DELIMITER $$

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
    DECLARE v_daily_limit DECIMAL(15,2);
    DECLARE v_daily_usage DECIMAL(15,2);
    
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
        WHERE ap.AccountType = 'Savings' AND a.AccountStatus = 'Active';
    
    DECLARE CONTINUE HANDLER FOR NOT FOUND SET done = TRUE;
    
    OPEN cur;
    read_loop: LOOP
        FETCH cur INTO v_account, v_balance, v_rate, v_customer;
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
        
        INSERT INTO NOTIFICATIONS (customer_id, title, message, type)
        VALUES (v_customer, 'Interest Credited', 
                CONCAT('Monthly interest of ', fn_format_currency(v_interest), ' has been credited to your account.'),
                'success');
    END LOOP;
    CLOSE cur;
END$$

CREATE PROCEDURE sp_calculate_loan_emi(
    IN p_loan_amount DECIMAL(15,2),
    IN p_interest_rate DECIMAL(5,2),
    IN p_tenure_months INT,
    OUT p_emi DECIMAL(15,2),
    OUT p_total_payable DECIMAL(15,2)
)
BEGIN
    DECLARE v_monthly_rate DECIMAL(10,6);
    DECLARE v_temp DECIMAL(15,6);
    
    SET v_monthly_rate = p_interest_rate / 12 / 100;
    SET v_temp = POW(1 + v_monthly_rate, p_tenure_months);
    SET p_emi = p_loan_amount * v_monthly_rate * v_temp / (v_temp - 1);
    SET p_total_payable = p_emi * p_tenure_months;
END$$

CREATE PROCEDURE sp_generate_emi_schedule(
    IN p_loan_id INT
)
BEGIN
    DECLARE v_loan_amount DECIMAL(15,2);
    DECLARE v_interest_rate DECIMAL(5,2);
    DECLARE v_tenure_months INT;
    DECLARE v_emi DECIMAL(15,2);
    DECLARE v_total_payable DECIMAL(15,2);
    DECLARE v_balance DECIMAL(15,2);
    DECLARE v_interest DECIMAL(15,2);
    DECLARE v_principal DECIMAL(15,2);
    DECLARE v_monthly_rate DECIMAL(10,6);
    DECLARE v_i INT DEFAULT 1;
    DECLARE v_emi_date DATE;
    
    SELECT loan_amount, interest_rate, tenure_months, emi_amount
    INTO v_loan_amount, v_interest_rate, v_tenure_months, v_emi
    FROM LOANS WHERE loan_id = p_loan_id;
    
    SET v_balance = v_loan_amount;
    SET v_monthly_rate = v_interest_rate / 12 / 100;
    SET v_emi_date = CURDATE() + INTERVAL 1 MONTH;
    
    DELETE FROM LOAN_EMI_SCHEDULE WHERE loan_id = p_loan_id;
    
    WHILE v_i <= v_tenure_months DO
        SET v_interest = v_balance * v_monthly_rate;
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
            p_loan_id, v_emi_date, v_emi, v_principal, v_interest, v_balance, 'Pending'
        );
        
        SET v_emi_date = v_emi_date + INTERVAL 1 MONTH;
        SET v_i = v_i + 1;
    END WHILE;
END$$

-- ============================================================
-- 22. FUNCTIONS
-- ============================================================

CREATE FUNCTION fn_calculate_age(p_dob DATE)
RETURNS INT
DETERMINISTIC
BEGIN
    RETURN TIMESTAMPDIFF(YEAR, p_dob, CURDATE());
END$$

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

CREATE FUNCTION fn_format_currency(p_amount DECIMAL(15,2))
RETURNS VARCHAR(50)
DETERMINISTIC
BEGIN
    RETURN CONCAT('৳ ', FORMAT(p_amount, 2));
END$$

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
    
    SET v_monthly_rate = p_rate / 12 / 100;
    SET v_temp = POW(1 + v_monthly_rate, p_tenure);
    SET v_emi = p_amount * v_monthly_rate * v_temp / (v_temp - 1);
    
    RETURN v_emi;
END$$

CREATE FUNCTION fn_get_balance(p_account BIGINT)
RETURNS DECIMAL(15,2)
READS SQL DATA
BEGIN
    DECLARE v_balance DECIMAL(15,2);
    SELECT AvailableBalance INTO v_balance
    FROM ACCOUNT WHERE AccountNumber = p_account;
    RETURN v_balance;
END$$

-- ============================================================
-- 23. TRIGGERS
-- ============================================================

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

CREATE TRIGGER trg_transaction_audit
AFTER UPDATE ON ACCOUNT
FOR EACH ROW
BEGIN
    IF OLD.AvailableBalance != NEW.AvailableBalance THEN
        INSERT INTO TRANSACTION_AUDIT (transaction_id, action_type, old_amount, new_amount, changed_by)
        VALUES (NULL, 'BALANCE_UPDATE', OLD.AvailableBalance, NEW.AvailableBalance, USER());
    END IF;
END$$

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

CREATE TRIGGER trg_prevent_negative_balance
BEFORE UPDATE ON ACCOUNT
FOR EACH ROW
BEGIN
    IF NEW.AvailableBalance < 0 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Cannot update: Balance cannot be negative';
    END IF;
END$$

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

-- ============================================================
-- 24. VIEWS
-- ============================================================

CREATE VIEW v_customer_balance_tier AS
SELECT 
    c.CustomerID,
    c.FirstName,
    c.LastName,
    a.AccountNumber,
    a.AvailableBalance,
    fn_get_customer_tier(a.AvailableBalance) AS CustomerTier,
    fn_calculate_age(c.DateOfBirth) AS Age,
    fn_total_customer_transactions(c.CustomerID) AS TotalTransactions,
    k.status AS KYCStatus
FROM CUSTOMER c
JOIN ACCOUNT a ON c.CustomerID = a.CustomerID
LEFT JOIN KYC_VERIFICATIONS k ON c.CustomerID = k.customer_id
WHERE c.IsActive = 1;

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
    fn_get_customer_tier(a.AvailableBalance) AS Tier
FROM ACCOUNT a
JOIN CUSTOMER c ON a.CustomerID = c.CustomerID
JOIN ACCOUNTPRODUCT ap ON a.ProductID = ap.ProductID
JOIN BRANCH b ON a.BranchID = b.BranchID
WHERE c.IsActive = 1;

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
    SUM(CASE WHEN e.status = 'Paid' THEN e.emi_amount ELSE 0 END) AS TotalPaidAmount,
    (l.loan_amount - SUM(CASE WHEN e.status = 'Paid' THEN e.principal_amount ELSE 0 END)) AS OutstandingPrincipal
FROM LOANS l
JOIN CUSTOMER c ON l.customer_id = c.CustomerID
JOIN LOAN_PRODUCTS lp ON l.loan_product_id = lp.loan_product_id
LEFT JOIN LOAN_EMI_SCHEDULE e ON l.loan_id = e.loan_id
GROUP BY l.loan_id;

CREATE VIEW v_bill_payment_summary AS
SELECT 
    bp.bill_payment_id,
    CONCAT(c.FirstName, ' ', c.LastName) AS CustomerName,
    bc.category_name AS BillCategory,
    bp.provider_id,
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
-- 25. SAMPLE DATA
-- ============================================================

INSERT INTO ZONE (ZoneName) VALUES ('Dhaka Division'), ('Chittagong Division'), ('Khulna Division');
INSERT INTO REGION (RegionName, ZoneID) VALUES ('Dhaka Metro',1), ('Chittagong Metro',2), ('Khulna Metro',3);

INSERT INTO BRANCH (BranchName, IFSCCode, Address, City, RegionID) VALUES
('Dhaka Main Branch','ASHA0001001','Motijheel C/A','Dhaka',1),
('Chittagong Branch','ASHA0002001','Agrabad C/A','Chittagong',2),
('Khulna Branch','ASHA0003001','KDA Avenue','Khulna',3);

INSERT INTO DEPARTMENT (DepartmentName) VALUES 
('Retail Banking'), ('Operations'), ('Customer Service'), ('Loan Department'), ('IT');

INSERT INTO DESIGNATION (DesignationName) VALUES 
('Branch Manager'), ('Senior Officer'), ('Officer'), ('Teller'), ('IT Specialist');

INSERT INTO EMPLOYEE (FirstName, LastName, Email, Phone, DepartmentID, DesignationID, BranchID, HireDate, Salary, IsActive) VALUES
('Rajesh', 'Sharma', 'rajesh@ashabank.bd', '01710000001', 1, 1, 1, '2015-03-01', 85000.00, 1),
('Priya', 'Mehta', 'priya@ashabank.bd', '01710000002', 2, 2, 2, '2016-06-15', 55000.00, 1),
('Amit', 'Verma', 'amit@ashabank.bd', '01710000003', 3, 3, 3, '2018-01-10', 45000.00, 1);

UPDATE BRANCH SET ManagerEmployeeID=1 WHERE BranchID=1;
UPDATE BRANCH SET ManagerEmployeeID=2 WHERE BranchID=2;
UPDATE BRANCH SET ManagerEmployeeID=3 WHERE BranchID=3;

INSERT INTO ADMIN_USER (Username, PasswordHash, Role, EmployeeID, IsActive) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'superadmin', 1, 1);

INSERT INTO CUSTOMERCATEGORY (CategoryName) VALUES ('Regular'), ('Premium'), ('Senior Citizen'), ('Student');

INSERT INTO CUSTOMER (FirstName, LastName, DateOfBirth, Gender, Email, Phone, Address, City, NationalID, CustomerCategoryID, PrimaryBranchID, CreatedAt, IsActive) VALUES
('Arjun', 'Kapoor', '1990-05-15', 'Male', 'arjun.kapoor@gmail.com', '01710000101', '42 Gulshan Avenue', 'Dhaka', 'BD123456789', 1, 1, NOW(), 1),
('Sanya', 'Malhotra', '1988-11-22', 'Female', 'sanya.malhotra@gmail.com', '01710000102', '8 GEC Circle', 'Chittagong', 'BD987654321', 2, 2, NOW(), 1),
('Kiran', 'Bose', '1975-03-08', 'Female', 'kiran.bose@gmail.com', '01710000103', '15 Sonadanga', 'Khulna', 'BD456789123', 1, 3, NOW(), 1);

INSERT INTO ACCOUNTPRODUCT (ProductName, AccountType, InterestRate, MinBalance, MaxWithdrawalLimit, DailyTransferLimit, Description) VALUES
('Asha Savings Classic', 'Savings', 3.50, 1000.00, 200000.00, 500000.00, 'Standard savings account'),
('Asha Savings Premium', 'Savings', 4.00, 10000.00, 500000.00, 1000000.00, 'Premium savings account'),
('Asha Current Pro', 'Current', 0.00, 5000.00, 1000000.00, 2000000.00, 'Business current account');

INSERT INTO ACCOUNT (AccountNumber, ProductID, CustomerID, BranchID, OpeningDate, AvailableBalance, AccountStatus) VALUES
(10000000001, 1, 1, 1, '2023-01-15', 45250.75, 'Active'),
(10000000002, 1, 2, 2, '2023-02-20', 128900.00, 'Active'),
(10000000003, 2, 3, 3, '2022-11-01', 350000.00, 'Active');

INSERT INTO CARDS (CardNumber, CustomerID, AccountNumber, ExpiryDate, CVV, CardType, CardStatus, DailyLimit) VALUES
('4532123456789012', 1, 10000000001, DATE_ADD(CURDATE(), INTERVAL 5 YEAR), '123', 'Debit', 'Active', 500000),
('4532987654321098', 2, 10000000002, DATE_ADD(CURDATE(), INTERVAL 5 YEAR), '456', 'Debit', 'Active', 500000),
('4532111122223333', 3, 10000000003, DATE_ADD(CURDATE(), INTERVAL 5 YEAR), '789', 'Debit', 'Active', 500000);

INSERT INTO NOMINEE (CustomerID, NomineeName, NomineeRelation, NomineePhone) VALUES
(1, 'Sita Kapoor', 'Spouse', '01710000201'),
(2, 'Raj Malhotra', 'Father', '01710000202');

INSERT INTO TRANSACTIONTYPE (TypeName, Description, Fee) VALUES
('Deposit', 'Money deposited into account', 0.00),
('Withdrawal', 'Money withdrawn from account', 0.00),
('Transfer', 'Fund transfer between accounts', 0.00),
('NEFT', 'National Electronic Funds Transfer', 5.00),
('UPI', 'Unified Payment Interface', 0.00),
('Bill Payment', 'Payment of utility bills', 0.00),
('Loan EMI', 'Loan EMI payment', 0.00),
('Interest', 'Interest credited', 0.00);

INSERT INTO TRANSACTION (TransactionTypeID, TransactionAmount, TransactionDate, FromAccountNumber, ToAccountNumber, FromCustomerID, ToCustomerID, Description, TransactionStatus, ReferenceNumber) VALUES
(1, 5000.00, NOW() - INTERVAL 10 DAY, NULL, 10000000001, NULL, 1, 'Initial Deposit', 'Completed', 'DEP001'),
(3, 10000.00, NOW() - INTERVAL 8 DAY, 10000000001, 10000000002, 1, 2, 'Personal Transfer', 'Completed', 'TXN001'),
(2, 2000.00, NOW() - INTERVAL 5 DAY, 10000000002, NULL, 2, NULL, 'ATM Withdrawal', 'Completed', 'WDL001'),
(1, 25000.00, NOW() - INTERVAL 3 DAY, NULL, 10000000003, NULL, 3, 'Salary Credit', 'Completed', 'DEP002'),
(3, 5000.00, NOW() - INTERVAL 2 DAY, 10000000003, 10000000001, 3, 1, 'Transfer to Friend', 'Completed', 'TXN002');

INSERT INTO DIGITALBANKINGUSER (CustomerID, Username, PasswordHash, IsActive, CreatedAt) VALUES
(1, 'arjun.kapoor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(2, 'sanya.malhotra', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(3, 'kiran.bose', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW());

INSERT INTO STAFF (first_name, last_name, email, phone, username, password_hash, role, department, join_date, is_active) VALUES
('Rajesh', 'Sharma', 'rajesh@ashabank.bd', '01710000001', 'rajesh', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'manager', 'Operations', CURDATE(), 1);

INSERT INTO NOTIFICATIONS (customer_id, title, message, type, created_at) VALUES
(1, 'Welcome to Asha Bank!', 'Thank you for joining Asha Bank. We are delighted to have you.', 'success', NOW()),
(2, 'KYC Verification Required', 'Please complete your KYC verification to activate all features.', 'warning', NOW());

INSERT INTO FEEDBACK (customer_id, subject, message, type, status, created_at) VALUES
(1, 'Great Service', 'The mobile banking app is very user friendly!', 'feedback', 'pending', NOW());

INSERT INTO KYC_VERIFICATIONS (customer_id, nid_number, status, submitted_at) VALUES
(1, '12345678901234567', 'pending', NOW()),
(2, '98765432109876543', 'verified', NOW());

INSERT INTO BILL_CATEGORIES (category_name, category_icon) VALUES
('Electricity', 'fa-bolt'),
('Water', 'fa-tint'),
('Gas', 'fa-fire'),
('Mobile Recharge', 'fa-mobile-alt'),
('DTH/Cable', 'fa-tv'),
('Insurance', 'fa-shield-alt');

INSERT INTO BILL_PROVIDERS (category_id, provider_name, provider_code) VALUES
(1, 'DESCO', 'DESCO'),
(1, 'DPDC', 'DPDC'),
(2, 'DWASA', 'DWASA'),
(3, 'Titas Gas', 'TITAS'),
(4, 'Grameenphone', 'GP'),
(4, 'Robi', 'ROBI'),
(5, 'Dish TV', 'DISHTV');

INSERT INTO LOAN_PRODUCTS (product_name, loan_type, min_amount, max_amount, min_tenure_months, max_tenure_months, interest_rate, processing_fee) VALUES
('Home Loan', 'Home', 500000, 5000000, 60, 240, 8.50, 5000),
('Personal Loan', 'Personal', 50000, 500000, 12, 60, 12.00, 1000),
('Car Loan', 'Car', 300000, 3000000, 24, 84, 9.50, 3000),
('Education Loan', 'Education', 100000, 2000000, 12, 120, 7.00, 2000);

UPDATE CUSTOMER SET ReferralCode = CONCAT('REF', CustomerID, SUBSTRING(MD5(RAND()), 1, 6)) WHERE CustomerID > 0;

SELECT '✅ ASHA BANK DATABASE READY' AS Status;