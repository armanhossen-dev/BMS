-- ============================================================
-- ASHA BANK - Complete Database Schema
-- MySQL 8.0+ | Updated for compatibility
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
-- EMPLOYEE
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
    FOREIGN KEY (DepartmentID)  REFERENCES DEPARTMENT(DepartmentID),
    FOREIGN KEY (DesignationID) REFERENCES DESIGNATION(DesignationID),
    FOREIGN KEY (BranchID)      REFERENCES BRANCH(BranchID),
    FOREIGN KEY (ManagerID)     REFERENCES EMPLOYEE(EmployeeID)
);

-- ============================================================
-- ADMIN USERS (for login)
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
-- CARDS (Added for debit card functionality)
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
-- JOINT ACCOUNT HOLDER
-- ============================================================
CREATE TABLE JOINTACCOUNTHOLDER (
    JointHolderID   INT AUTO_INCREMENT PRIMARY KEY,
    AccountNumber   BIGINT NOT NULL,
    CustomerID      INT NOT NULL,
    FOREIGN KEY (AccountNumber) REFERENCES ACCOUNT(AccountNumber),
    FOREIGN KEY (CustomerID)    REFERENCES CUSTOMER(CustomerID)
);

-- ============================================================
-- ACCOUNT BALANCE HISTORY
-- ============================================================
CREATE TABLE ACCOUNTBALANCEHISTORY (
    HistoryID      INT AUTO_INCREMENT PRIMARY KEY,
    AccountNumber  BIGINT NOT NULL,
    BalanceDate    DATE NOT NULL,
    ClosingBalance DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (AccountNumber) REFERENCES ACCOUNT(AccountNumber)
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
    FOREIGN KEY (ToCustomerID)      REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (ProcessedBy)       REFERENCES EMPLOYEE(EmployeeID)
);

-- ============================================================
-- DIGITAL BANKING USER
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
-- LOAN PRODUCT
-- ============================================================
CREATE TABLE LOANPRODUCT (
    ProductID       INT AUTO_INCREMENT PRIMARY KEY,
    ProductName     VARCHAR(150) NOT NULL,
    LoanType        ENUM('Home','Personal','Vehicle','Business','Education','Agriculture') NOT NULL,
    InterestRate    DECIMAL(5,2) NOT NULL,
    MaxAmount       DECIMAL(15,2),
    MaxTenureMonths INT,
    Description     TEXT
);

-- ============================================================
-- LOAN APPLICATION
-- ============================================================
CREATE TABLE LOANAPPLICATION (
    ApplicationID     INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID        INT NOT NULL,
    ProductID         INT NOT NULL,
    LoanAmount        DECIMAL(15,2) NOT NULL,
    TenureMonths      INT NOT NULL,
    Purpose           TEXT,
    ApplicationDate   DATE DEFAULT (CURRENT_DATE),
    ApplicationStatus ENUM('Pending','Under Review','Approved','Rejected','Disbursed') DEFAULT 'Pending',
    ProcessedByID     INT,
    ProcessedDate     DATE,
    FOREIGN KEY (CustomerID)    REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (ProductID)     REFERENCES LOANPRODUCT(ProductID),
    FOREIGN KEY (ProcessedByID) REFERENCES EMPLOYEE(EmployeeID)
);

-- ============================================================
-- LOAN ACCOUNT
-- ============================================================
CREATE TABLE LOANACCOUNT (
    LoanAccountNumber    BIGINT AUTO_INCREMENT PRIMARY KEY,
    ApplicationID        INT NOT NULL UNIQUE,
    DisbursementDate     DATE,
    OutstandingPrincipal DECIMAL(15,2) NOT NULL,
    OutstandingInterest  DECIMAL(15,2) DEFAULT 0.00,
    LoanStatus           ENUM('Active','Closed','NPA','Written Off') DEFAULT 'Active',
    FOREIGN KEY (ApplicationID) REFERENCES LOANAPPLICATION(ApplicationID)
);

-- ============================================================
-- COMPLAINT CATEGORY
-- ============================================================
CREATE TABLE COMPLAINTCATEGORY (
    CategoryID   INT AUTO_INCREMENT PRIMARY KEY,
    CategoryName VARCHAR(100) NOT NULL
);

-- ============================================================
-- COMPLAINT
-- ============================================================
CREATE TABLE COMPLAINT (
    ComplaintID   INT AUTO_INCREMENT PRIMARY KEY,
    CustomerID    INT NOT NULL,
    AccountNumber BIGINT,
    CategoryID    INT NOT NULL,
    Description   TEXT NOT NULL,
    Status        ENUM('Open','In Progress','Resolved','Closed') DEFAULT 'Open',
    RaisedDate    DATETIME DEFAULT CURRENT_TIMESTAMP,
    ResolvedDate  DATETIME,
    ResolvedBy    INT,
    FOREIGN KEY (CustomerID)    REFERENCES CUSTOMER(CustomerID),
    FOREIGN KEY (AccountNumber) REFERENCES ACCOUNT(AccountNumber),
    FOREIGN KEY (CategoryID)    REFERENCES COMPLAINTCATEGORY(CategoryID),
    FOREIGN KEY (ResolvedBy)    REFERENCES EMPLOYEE(EmployeeID)
);

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================================
-- SEED DATA
-- ============================================================

-- Zones
INSERT INTO ZONE (ZoneName) VALUES ('North Zone'),('South Zone'),('East Zone'),('West Zone'),('Central Zone');

-- Regions
INSERT INTO REGION (RegionName, ZoneID) VALUES
('Delhi NCR',1), ('Mumbai Metro',2), ('Kolkata',3), ('Ahmedabad',4), ('Lucknow',5);

-- Branches
INSERT INTO BRANCH (BranchName, IFSCCode, Address, City, RegionID) VALUES
('Connaught Place Branch','ASHA0001001','12 Rajiv Chowk, CP','New Delhi',1),
('Bandra West Branch','ASHA0002001','SV Road, Bandra','Mumbai',2),
('Park Street Branch','ASHA0003001','10 Park Street','Kolkata',3),
('CG Road Branch','ASHA0004001','CG Road, Navrangpura','Ahmedabad',4),
('Hazratganj Branch','ASHA0005001','Hazratganj Market','Lucknow',5);

-- Departments
INSERT INTO DEPARTMENT (DepartmentName) VALUES
('Retail Banking'), ('Corporate Banking'), ('Operations'), ('IT & Digital'), ('Customer Service');

-- Designations
INSERT INTO DESIGNATION (DesignationName) VALUES
('Branch Manager'), ('Senior Officer'), ('Officer'), ('Executive');

-- Employees
INSERT INTO EMPLOYEE (FirstName,LastName,Email,Phone,DepartmentID,DesignationID,BranchID,HireDate,Salary,IsActive) VALUES
('Rajesh','Sharma','rajesh@ashabank.in','9810001001',1,1,1,'2015-03-01',85000.00,1),
('Priya','Mehta','priya@ashabank.in','9810001002',1,1,2,'2016-06-15',82000.00,1),
('Amit','Verma','amit@ashabank.in','9810001003',3,2,3,'2014-01-10',88000.00,1),
('Sunita','Rao','sunita@ashabank.in','9810001004',4,3,1,'2018-09-01',55000.00,1),
('Deepak','Joshi','deepak@ashabank.in','9810001005',5,3,2,'2019-04-12',58000.00,1);

-- Set branch managers
UPDATE BRANCH SET ManagerEmployeeID=1 WHERE BranchID=1;
UPDATE BRANCH SET ManagerEmployeeID=2 WHERE BranchID=2;
UPDATE BRANCH SET ManagerEmployeeID=3 WHERE BranchID=3;

-- Admin users (Password: Admin@123 hashed with bcrypt)
INSERT INTO ADMIN_USER (Username, PasswordHash, Role, EmployeeID, IsActive) VALUES
('admin','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','superadmin',1,1),
('manager1','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi','manager',2,1);

-- Customer Categories
INSERT INTO CUSTOMERCATEGORY (CategoryName) VALUES
('Regular'), ('Premium'), ('NRI'), ('Senior Citizen'), ('Student');

-- Customers
INSERT INTO CUSTOMER (FirstName, LastName, DateOfBirth, Gender, Email, Phone, Address, City, NationalID, CustomerCategoryID, PrimaryBranchID, RelationshipManagerID, CreatedAt, IsActive) VALUES
('Arjun', 'Kapoor', '1990-05-15', 'Male', 'arjun.kapoor@gmail.com', '9900001001', '42 Green Park', 'New Delhi', 'ABCPK1234R', 1, 1, 4, NOW(), 1),
('Sanya', 'Malhotra', '1988-11-22', 'Female', 'sanya.malhotra@gmail.com', '9900001002', '8 Juhu Scheme', 'Mumbai', 'MNOPY5678S', 2, 2, 5, NOW(), 1),
('Kiran', 'Bose', '1975-03-08', 'Female', 'kiran.bose@gmail.com', '9900001003', '15 Lake Gardens', 'Kolkata', 'QRSTU9012T', 1, 3, 4, NOW(), 1),
('Ravi', 'Shankar', '1968-09-09', 'Male', 'ravi.shankar@gmail.com', '9900001004', '101 Hazratganj', 'Lucknow', 'ABCDE2345W', 4, 5, NULL, NOW(), 1),
('Pooja', 'Sharma', '1995-04-25', 'Female', 'pooja.sharma@gmail.com', '9900001005', '5 Karol Bagh', 'New Delhi', 'FGHIJ6789X', 5, 1, NULL, NOW(), 1);

-- Account Products
INSERT INTO ACCOUNTPRODUCT (ProductName, AccountType, InterestRate, MinBalance, Description) VALUES
('Asha Savings Classic', 'Savings', 3.50, 1000.00, 'Standard savings account'),
('Asha Savings Premium', 'Savings', 4.00, 10000.00, 'Premium savings account'),
('Asha Current Pro', 'Current', 0.00, 5000.00, 'Business current account');

-- Accounts (11-digit account numbers)
INSERT INTO ACCOUNT (AccountNumber, ProductID, CustomerID, BranchID, OpeningDate, AvailableBalance, AccountStatus) VALUES
(10000000001, 1, 1, 1, '2023-01-15', 45250.75, 'Active'),
(10000000002, 1, 2, 2, '2023-02-20', 128900.00, 'Active'),
(10000000003, 2, 3, 3, '2022-11-01', 350000.00, 'Active'),
(10000000004, 1, 4, 5, '2022-08-15', 8750.25, 'Active'),
(10000000005, 1, 5, 1, '2023-06-01', 12300.00, 'Active');

-- Cards
INSERT INTO CARDS (CardNumber, CustomerID, ExpiryDate, CVV, CardType, IsActive) VALUES
('4532123456789012', 1, DATE_ADD(CURDATE(), INTERVAL 5 YEAR), '123', 'Debit', 1),
('4532987654321098', 2, DATE_ADD(CURDATE(), INTERVAL 5 YEAR), '456', 'Debit', 1),
('4532111122223333', 3, DATE_ADD(CURDATE(), INTERVAL 5 YEAR), '789', 'Debit', 1);

-- Nominees
INSERT INTO NOMINEE (CustomerID, NomineeName, NomineeRelation, NomineePhone) VALUES
(1, 'Sita Kapoor', 'Spouse', '9800000001'),
(2, 'Raj Malhotra', 'Father', '9800000002');

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

-- Digital Banking Users (Password: 'password' for all seeded users)
INSERT INTO DIGITALBANKINGUSER (CustomerID, Username, PasswordHash, IsActive, CreatedAt) VALUES
(1, 'arjun.kapoor', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(2, 'sanya.malhotra', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(3, 'kiran.bose', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(4, 'ravi.shankar', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW()),
(5, 'pooja.sharma', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, NOW());

-- Beneficiaries
INSERT INTO BENEFICIARY (CustomerID, BeneficiaryName, BeneficiaryAccountNumber, BeneficiaryIFSC, BeneficiaryBankName, IsActive) VALUES
(1, 'Sanya Malhotra', '10000000002', 'ASHA0002001', 'Asha Bank', 1),
(2, 'Arjun Kapoor', '10000000001', 'ASHA0001001', 'Asha Bank', 1);

-- Complaint Categories
INSERT INTO COMPLAINTCATEGORY (CategoryName) VALUES
('Transaction Issue'), ('Account Access'), ('Card Related'), ('Service Quality'), ('Digital Banking');

-- ============================================================
-- HELPER VIEWS
-- ============================================================

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

CREATE VIEW v_transaction_summary AS
SELECT 
    t.TransactionID,
    t.ReferenceNumber,
    tt.TypeName,
    t.TransactionAmount,
    t.TransactionDate,
    t.TransactionStatus,
    CONCAT(fc.FirstName, ' ', fc.LastName) AS FromCustomer,
    t.FromAccountNumber,
    CONCAT(tc.FirstName, ' ', tc.LastName) AS ToCustomer,
    t.ToAccountNumber
FROM TRANSACTION t
JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
LEFT JOIN CUSTOMER fc ON t.FromCustomerID = fc.CustomerID
LEFT JOIN CUSTOMER tc ON t.ToCustomerID = tc.CustomerID;

-- ============================================================
-- STORED PROCEDURE: Fund Transfer (Atomic)
-- ============================================================

DELIMITER $$

CREATE PROCEDURE sp_fund_transfer(
    IN p_from_acc BIGINT,
    IN p_to_acc BIGINT,
    IN p_amount DECIMAL(15,2),
    IN p_from_cust INT,
    IN p_to_cust INT,
    OUT p_status VARCHAR(50)
)
BEGIN
    DECLARE v_balance DECIMAL(15,2);
    DECLARE v_ref VARCHAR(50);
    
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_status = 'FAILED';
    END;
    
    START TRANSACTION;
    
    -- Check balance
    SELECT AvailableBalance INTO v_balance FROM ACCOUNT WHERE AccountNumber = p_from_acc FOR UPDATE;
    
    IF v_balance < p_amount THEN
        SET p_status = 'INSUFFICIENT_FUNDS';
        ROLLBACK;
    ELSE
        -- Deduct from sender
        UPDATE ACCOUNT SET AvailableBalance = AvailableBalance - p_amount WHERE AccountNumber = p_from_acc;
        -- Add to receiver
        UPDATE ACCOUNT SET AvailableBalance = AvailableBalance + p_amount WHERE AccountNumber = p_to_acc;
        
        -- Create transaction record
        SET v_ref = CONCAT('TXN', DATE_FORMAT(NOW(), '%Y%m%d%H%i%s'), FLOOR(RAND() * 1000));
        
        INSERT INTO TRANSACTION (TransactionTypeID, TransactionAmount, FromAccountNumber, ToAccountNumber, FromCustomerID, ToCustomerID, Description, TransactionStatus, ReferenceNumber, TransactionDate)
        VALUES (3, p_amount, p_from_acc, p_to_acc, p_from_cust, p_to_cust, 'Fund Transfer', 'Completed', v_ref, NOW());
        
        SET p_status = 'SUCCESS';
        COMMIT;
    END IF;
END$$

DELIMITER ;

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================

CREATE INDEX idx_account_customer ON ACCOUNT(CustomerID);
CREATE INDEX idx_transaction_date ON TRANSACTION(TransactionDate);
CREATE INDEX idx_transaction_customer ON TRANSACTION(FromCustomerID, ToCustomerID);
CREATE INDEX idx_digital_user ON DIGITALBANKINGUSER(Username);

-- ============================================================
-- END OF SCRIPT
-- ============================================================

SELECT '✅ Database setup complete!' AS Status;




-- Add after existing tables
CREATE TABLE IF NOT EXISTS bangladesh_cities (
    city_id INT AUTO_INCREMENT PRIMARY KEY,
    city_name VARCHAR(100) NOT NULL,
    division VARCHAR(100)
);

INSERT INTO bangladesh_cities (city_name, division) VALUES
('Dhaka', 'Dhaka'), ('Chittagong', 'Chittagong'), ('Khulna', 'Khulna'),
('Rajshahi', 'Rajshahi'), ('Barisal', 'Barisal'), ('Sylhet', 'Sylhet'),
('Rangpur', 'Rangpur'), ('Mymensingh', 'Mymensingh'), ('Comilla', 'Chittagong'),
('Narayanganj', 'Dhaka'), ('Gazipur', 'Dhaka'), ('Jessore', 'Khulna'),
('Bogra', 'Rajshahi'), ('Dinajpur', 'Rangpur'), ('Pabna', 'Rajshahi');