<?php
session_start();

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database credentials
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'asha_bank');
define('DB_USER', 'root');
define('DB_PASS', '');
define('CURRENCY_SYMBOL', '৳');
define('CURRENCY_CODE', 'BDT');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";port=3306;dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch(PDOException $e) {
    try {
        $pdo = new PDO(
            "mysql:host=localhost;port=3306;dbname=" . DB_NAME . ";charset=utf8mb4",
            DB_USER,
            DB_PASS
        );
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } catch(PDOException $e2) {
        die("Connection failed: " . $e2->getMessage());
    }
}

// Set default language to English
if (!isset($_SESSION['language'])) {
    $_SESSION['language'] = 'en';
}

// Translation function
function t($key) {
    $lang = $_SESSION['language'] ?? 'en';
    
    $translations = [
        'en' => [
            // General
            'welcome' => 'Welcome',
            'dashboard' => 'Dashboard',
            'transfer' => 'Transfer Money',
            'deposit' => 'Deposit Money',
            'withdraw' => 'Withdraw Money',
            'nominee' => 'Nominee',
            'logout' => 'Logout',
            'login' => 'Login',
            'register' => 'Register',
            'balance' => 'Current Balance',
            'account_no' => 'Account Number',
            'transactions' => 'Recent Transactions',
            'amount' => 'Amount',
            'date' => 'Date',
            'status' => 'Status',
            'completed' => 'Completed',
            'pending' => 'Pending',
            'send' => 'Send Money',
            'receiver_account' => 'Receiver Account Number',
            'insufficient_balance' => 'Insufficient Balance',
            'deposit_success' => 'Deposit Successful',
            'withdraw_success' => 'Withdrawal Successful',
            'transfer_success' => 'Transfer Successful',
            'card_details' => 'Card Details',
            'card_number' => 'Card Number',
            'expiry' => 'Expiry Date',
            'cvv' => 'CVV',
            'click_for_card' => 'Click for card details',
            'no_transactions' => 'No transactions yet',
            'active' => 'Active',
            'inactive' => 'Inactive',
            'total_customers' => 'Total Customers',
            'total_balance' => 'Total Balance',
            'total_transactions' => 'Total Transactions',
            'pending_kyc' => 'Pending KYC',
            'today_transactions' => "Today's Transactions",
            'recent_customers' => 'Recent Customers',
            'all_customers' => 'All Customers',
            'all_transactions' => 'All Transactions',
            'name' => 'Name',
            'email' => 'Email',
            'phone' => 'Phone',
            'action' => 'Action',
            'verify' => 'Verify',
            'approve' => 'Approve',
            'reject' => 'Reject',
            'view' => 'View',
            'edit' => 'Edit',
            'delete' => 'Delete',
            'search' => 'Search',
            'filter' => 'Filter',
            'export' => 'Export',
            'settings' => 'Settings',
            'profile' => 'Profile',
            'help' => 'Help',
            'contact' => 'Contact',
            'about' => 'About',
            'language' => 'Language',
            'bengali' => 'Bengali',
            'english' => 'English',
            'dark_mode' => 'Dark Mode',
            'light_mode' => 'Light Mode',
            // Staff specific
            'staff_dashboard' => 'Staff Dashboard',
            'customer_management' => 'Customer Management',
            'pending_approvals' => 'Pending Approvals',
            'verify_kyc' => 'Verify KYC',
            'process_transaction' => 'Process Transaction',
            'customer_details' => 'Customer Details',
            'issue_card' => 'Issue Card',
            'freeze_account' => 'Freeze Account',
            'activate_account' => 'Activate Account',
            // Admin specific
            'admin_dashboard' => 'Admin Dashboard',
            'user_management' => 'User Management',
            'system_settings' => 'System Settings',
            'branch_management' => 'Branch Management',
            'employee_management' => 'Employee Management',
            'reports' => 'Reports',
            'audit_log' => 'Audit Log',
            'backup' => 'Backup',
            'restore' => 'Restore',
        ],
        'bn' => [
            // General
            'welcome' => 'স্বাগতম',
            'dashboard' => 'ড্যাশবোর্ড',
            'transfer' => 'টাকা স্থানান্তর',
            'deposit' => 'টাকা জমা',
            'withdraw' => 'টাকা উত্তোলন',
            'nominee' => 'নামমাত্র',
            'logout' => 'লগআউট',
            'login' => 'লগইন',
            'register' => 'রেজিস্টার',
            'balance' => 'বর্তমান ব্যালেন্স',
            'account_no' => 'একাউন্ট নম্বর',
            'transactions' => 'সাম্প্রতিক লেনদেন',
            'amount' => 'পরিমাণ',
            'date' => 'তারিখ',
            'status' => 'স্ট্যাটাস',
            'completed' => 'সম্পন্ন',
            'pending' => 'বাকি',
            'send' => 'টাকা পাঠান',
            'receiver_account' => 'প্রাপকের একাউন্ট নম্বর',
            'insufficient_balance' => 'পর্যাপ্ত ব্যালেন্স নেই',
            'deposit_success' => 'টাকা জমা সফল হয়েছে',
            'withdraw_success' => 'টাকা উত্তোলন সফল হয়েছে',
            'transfer_success' => 'টাকা স্থানান্তর সফল হয়েছে',
            'card_details' => 'কার্ডের বিবরণ',
            'card_number' => 'কার্ড নম্বর',
            'expiry' => 'মেয়াদ শেষের তারিখ',
            'cvv' => 'সিভিভি',
            'click_for_card' => 'কার্ডের বিবরণ দেখতে ক্লিক করুন',
            'no_transactions' => 'কোনো লেনদেন নেই',
            'active' => 'সক্রিয়',
            'inactive' => 'নিষ্ক্রিয়',
            'total_customers' => 'মোট গ্রাহক',
            'total_balance' => 'মোট ব্যালেন্স',
            'total_transactions' => 'মোট লেনদেন',
            'pending_kyc' => 'বাকি কেওয়াইসি',
            'today_transactions' => 'আজকের লেনদেন',
            'recent_customers' => 'সাম্প্রতিক গ্রাহক',
            'all_customers' => 'সব গ্রাহক',
            'all_transactions' => 'সব লেনদেন',
            'name' => 'নাম',
            'email' => 'ইমেইল',
            'phone' => 'ফোন',
            'action' => 'কর্ম',
            'verify' => 'যাচাই করুন',
            'approve' => 'অনুমোদন করুন',
            'reject' => 'বাতিল করুন',
            'view' => 'দেখুন',
            'edit' => 'সম্পাদনা করুন',
            'delete' => 'মুছুন',
            'language' => 'ভাষা',
            'bengali' => 'বাংলা',
            'english' => 'ইংরেজি',
        ]
    ];
    
    return $translations[$lang][$key] ?? $key;
}

function formatBDT($amount) {
    return '৳ ' . number_format($amount, 2);
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['role']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
}

function isStaff() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'staff';
}

function isClient() {
    return isset($_SESSION['role']) && $_SESSION['role'] == 'client';
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function setToast($message, $type = 'success') {
    $_SESSION['toast'] = ['message' => $message, 'type' => $type];
}

function getToast() {
    if (isset($_SESSION['toast'])) {
        $toast = $_SESSION['toast'];
        unset($_SESSION['toast']);
        return $toast;
    }
    return null;
}

function getUserAccount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT a.*, c.FirstName, c.LastName, c.Email, c.Phone 
                           FROM ACCOUNT a 
                           JOIN CUSTOMER c ON a.CustomerID = c.CustomerID 
                           WHERE c.CustomerID = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function generateAccountNumber($pdo) {
    do {
        $accNum = mt_rand(10000000000, 99999999999);
        $stmt = $pdo->prepare("SELECT AccountNumber FROM ACCOUNT WHERE AccountNumber = ?");
        $stmt->execute([$accNum]);
    } while ($stmt->fetch());
    return $accNum;
}
?>