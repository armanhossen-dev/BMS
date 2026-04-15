<?php
// ============================================
// ASHA BANK - Database Configuration
// ============================================

session_start();

// Database credentials
define('DB_HOST', 'localhost');
define('DB_NAME', 'asha_bank');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch(PDOException $e) {
    die("Database Connection Failed: " . $e->getMessage());
}

// ========== AUTHENTICATION HELPERS ==========
function isLoggedIn() {
    return isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 0;
}

function isAdmin() {
    return isset($_SESSION['user_id']) && isset($_SESSION['is_admin']) && $_SESSION['is_admin'] == 1;
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

// ========== BANKING HELPERS ==========
function getUserAccount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT a.*, c.FirstName, c.LastName, c.Email, c.Phone 
                           FROM ACCOUNT a 
                           JOIN CUSTOMER c ON a.CustomerID = c.CustomerID 
                           WHERE c.CustomerID = ?");
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function getUserBalance($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT AvailableBalance FROM ACCOUNT WHERE CustomerID = ?");
    $stmt->execute([$userId]);
    $result = $stmt->fetch();
    return $result ? $result['AvailableBalance'] : 0;
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