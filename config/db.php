<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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