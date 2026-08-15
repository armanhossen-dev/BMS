<?php
/**
 * Asha Bank — Shared helper functions
 */

function clean($str) {
    return htmlspecialchars(trim($str ?? ''), ENT_QUOTES, 'UTF-8');
}

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field() {
    return '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

function verify_csrf() {
    if (!isset($_POST['csrf_token']) || !isset($_SESSION['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        die('Invalid request. Please refresh the page and try again.');
    }
}

function flash($key, $msg = null, $type = 'info') {
    if ($msg === null) {
        if (isset($_SESSION['flash'][$key])) {
            $f = $_SESSION['flash'][$key];
            unset($_SESSION['flash'][$key]);
            return $f;
        }
        return null;
    }
    $_SESSION['flash'][$key] = ['msg' => $msg, 'type' => $type];
}

function currency($amount) {
    return APP_CURRENCY . ' ' . number_format((float)$amount, 2);
}

function tier_for_balance($balance) {
    if ($balance >= 1000000) return 'Black Edition';
    if ($balance >= 500000)  return 'Platinum';
    if ($balance >= 100000)  return 'Gold';
    if ($balance >= 10000)   return 'Silver';
    return 'Classic';
}

function tier_stars($tier) {
    $map = ['Classic' => 1, 'Silver' => 2, 'Gold' => 3, 'Platinum' => 4, 'Black Edition' => 5];
    return $map[$tier] ?? 1;
}

function tier_next($balance) {
    $thresholds = [10000 => 'Silver', 100000 => 'Gold', 500000 => 'Platinum', 1000000 => 'Black Edition'];
    foreach ($thresholds as $amt => $name) {
        if ($balance < $amt) return ['name' => $name, 'need' => $amt - $balance, 'target' => $amt];
    }
    return null; // already top tier
}

function tier_class($tier) {
    return 'tier-' . strtolower(str_replace(' ', '-', $tier));
}

function gen_reference($prefix = 'TXN') {
    return strtoupper($prefix) . time() . rand(100, 999);
}

function gen_account_number() {
    global $pdo;
    do {
        $num = '1' . str_pad((string)mt_rand(0, 9999999999), 10, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT 1 FROM ACCOUNT WHERE AccountNumber = ?");
        $stmt->execute([$num]);
    } while ($stmt->fetch());
    return $num;
}

function gen_card_number() {
    global $pdo;
    do {
        $num = '4' . str_pad((string)mt_rand(0, 999999999999999), 15, '0', STR_PAD_LEFT);
        $stmt = $pdo->prepare("SELECT 1 FROM CARDS WHERE CardNumber = ?");
        $stmt->execute([$num]);
    } while ($stmt->fetch());
    return $num;
}

function mask_account($num) {
    $num = (string)$num;
    return '•••• ' . substr($num, -4);
}

function mask_card($num) {
    $num = (string)$num;
    $chunks = str_split($num, 4);
    foreach ($chunks as $i => $c) {
        if ($i < count($chunks) - 1) $chunks[$i] = '••••';
    }
    return implode(' ', $chunks);
}

function time_ago($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('M j, Y', strtotime($datetime));
}

/* ---------------- Auth guards ---------------- */

function require_customer() {
    if (empty($_SESSION['customer_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function require_staff() {
    if (empty($_SESSION['staff_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

function require_admin() {
    if (empty($_SESSION['admin_id'])) {
        header('Location: ' . BASE_URL . '/login.php');
        exit;
    }
}

/* ---------------- Notifications ---------------- */

function notify($customer_id, $title, $message, $type = 'info', $link = null) {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO NOTIFICATIONS (customer_id, title, message, type, link) VALUES (?,?,?,?,?)");
    $stmt->execute([$customer_id, $title, $message, $type, $link]);
}

function unread_notification_count($customer_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM NOTIFICATIONS WHERE customer_id = ? AND is_read = 0");
    $stmt->execute([$customer_id]);
    return (int)$stmt->fetchColumn();
}

/* ---------------- Data fetch helpers ---------------- */

function get_customer($id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM CUSTOMER WHERE CustomerID = ?");
    $stmt->execute([$id]);
    return $stmt->fetch();
}

function get_primary_account($customer_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT a.*, ap.ProductName, ap.AccountType, ap.InterestRate
                            FROM ACCOUNT a JOIN ACCOUNTPRODUCT ap ON a.ProductID = ap.ProductID
                            WHERE a.CustomerID = ? ORDER BY a.AccountNumber LIMIT 1");
    $stmt->execute([$customer_id]);
    return $stmt->fetch();
}

function get_accounts($customer_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT a.*, ap.ProductName, ap.AccountType
                            FROM ACCOUNT a JOIN ACCOUNTPRODUCT ap ON a.ProductID = ap.ProductID
                            WHERE a.CustomerID = ? ORDER BY a.AccountNumber");
    $stmt->execute([$customer_id]);
    return $stmt->fetchAll();
}

function icon_for_type($type) {
    $icons = [
        'Deposit' => 'arrow-down-circle', 'Withdrawal' => 'arrow-up-circle',
        'Transfer' => 'send', 'NEFT' => 'repeat', 'UPI' => 'smartphone',
        'Bill Payment' => 'file-text', 'Loan EMI' => 'landmark', 'Interest' => 'percent',
    ];
    return $icons[$type] ?? 'circle';
}
