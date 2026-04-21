<?php
// ================================================
// DATABASE CONFIGURATION
// ================================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'bank_management');

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("<div style='color:red;font-family:sans-serif;padding:20px;'>
        ❌ Database Connection Failed: " . $conn->connect_error . "
        <br>Please ensure XAMPP MySQL is running and database exists.
    </div>");
}

$conn->set_charset("utf8");

// Session start
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Auth check function
function requireLogin() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit();
    }
}

// Generate unique IDs
function generateTransactionId() {
    return 'TXN-' . strtoupper(uniqid()) . '-' . rand(100, 999);
}

function generateCustomerId($conn) {
    $result = $conn->query("SELECT COUNT(*) as cnt FROM customers");
    $row = $result->fetch_assoc();
    return 'CUS-' . str_pad($row['cnt'] + 1, 3, '0', STR_PAD_LEFT);
}

function generateAccountNumber($conn) {
    $result = $conn->query("SELECT COUNT(*) as cnt FROM accounts");
    $row = $result->fetch_assoc();
    return 'ACC-' . str_pad($row['cnt'] + 1001, 7, '0', STR_PAD_LEFT);
}

function generateLoanId($conn) {
    $result = $conn->query("SELECT COUNT(*) as cnt FROM loans");
    $row = $result->fetch_assoc();
    return 'LOAN-' . str_pad($row['cnt'] + 1, 4, '0', STR_PAD_LEFT);
}

// Format currency
function formatCurrency($amount) {
    return '৳ ' . number_format($amount, 2);
}

// Role helpers
function isAdmin() {
    return isset($_SESSION['role']) && in_array($_SESSION['role'], ['admin', 'staff']);
}

function requireAdmin() {
    if (!isAdmin()) {
        header("Location: dashboard.php?error=unauthorized");
        exit();
    }
}
?>