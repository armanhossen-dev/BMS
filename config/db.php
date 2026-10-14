<?php
/**
 * Asha Bank — Database Configuration & Core Utilities
 * Secure, production-ready database layer
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode'  => true,
    ]);
}

// ── Environment ──────────────────────────────────────────────
define('APP_ENV',   'development'); // switch to 'production' in prod
define('APP_NAME',  'Asha Bank');
if (!defined('BASE_URL')) {
    $docRoot     = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $projectRoot = realpath(__DIR__ . '/..');
    $baseUrl     = '';
    if ($docRoot && $projectRoot && str_starts_with($projectRoot, $docRoot)) {
        $baseUrl = str_replace(DIRECTORY_SEPARATOR, '/', substr($projectRoot, strlen($docRoot)));
    }
    define('BASE_URL', rtrim($baseUrl, '/'));
}
if (!defined('APP_URL')) {
    define('APP_URL', BASE_URL);
}

// Prevent infinite loop if browser visits /BMS/* when root is already BMS
if (BASE_URL === '' && isset($_SERVER['REQUEST_URI']) && str_starts_with($_SERVER['REQUEST_URI'], '/BMS/')) {
    $cleanUri = substr($_SERVER['REQUEST_URI'], 4);
    header("Location: " . ($cleanUri ?: '/'), true, 301);
    exit;
}

// ── Database credentials (never expose in frontend) ──────────
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'asha_bank');
define('DB_USER', 'root');
define('DB_PASS', '');

// ── Currency ─────────────────────────────────────────────────
define('CURRENCY_SYMBOL', '৳');
define('CURRENCY_CODE',   'BDT');
define('APP_CURRENCY',    CURRENCY_SYMBOL);

// ── Error handling ───────────────────────────────────────────
if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ── PDO Connection ───────────────────────────────────────────
$dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (PDOException $e) {
    // Try localhost fallback
    try {
        $dsn2 = "mysql:host=localhost;port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $pdo  = new PDO($dsn2, DB_USER, DB_PASS, $options);
    } catch (PDOException $e2) {
        if (APP_ENV === 'development') {
            die('<div style="font-family:monospace;padding:20px;background:#fee;border:1px solid #f00;border-radius:8px;margin:20px;">
                <strong>Database Connection Failed</strong><br>' . htmlspecialchars($e2->getMessage()) . '
                <br><br>Please ensure MySQL is running and the database exists.
                <br>Run: <code>setup.php</code> to initialise the database.
                </div>');
        }
        die('Service temporarily unavailable. Please try again later.');
    }
}

// ─────────────────────────────────────────────────────────────
// AUTH HELPERS
// ─────────────────────────────────────────────────────────────

function isLoggedIn(): bool {
    return isset($_SESSION['user_id'], $_SESSION['role']);
}
function isAdmin(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}
function isStaff(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'staff';
}
function isClient(): bool {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'client';
}
function requireAuth(string $role = ''): void {
    if (!isLoggedIn()) {
        redirect(rootPath('login.php'));
    }
    if ($role && $_SESSION['role'] !== $role) {
        redirect(rootPath('login.php'));
    }
}

// ─────────────────────────────────────────────────────────────
// ACCOUNT HELPERS
// ─────────────────────────────────────────────────────────────

function isAccountActive(PDO $pdo, int $userId): bool {
    $stmt = $pdo->prepare("SELECT IsActive FROM CUSTOMER WHERE CustomerID = ?");
    $stmt->execute([$userId]);
    $r = $stmt->fetch();
    return $r && (bool)$r['IsActive'];
}

function getAccountStatusMessage(PDO $pdo, int $userId): ?string {
    $stmt = $pdo->prepare(
        "SELECT c.IsActive, a.AccountStatus
         FROM CUSTOMER c
         LEFT JOIN ACCOUNT a ON c.CustomerID = a.CustomerID
         WHERE c.CustomerID = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    $r = $stmt->fetch();
    if (!$r) return 'Account not found.';
    if (!$r['IsActive']) return 'Your account has been deactivated. Please contact support.';
    if ($r['AccountStatus'] === 'Frozen') return 'Your account is frozen. Please contact support.';
    if ($r['AccountStatus'] === 'Closed') return 'This account is closed.';
    return null;
}

function getUserAccount(PDO $pdo, int $userId): array|false {
    $stmt = $pdo->prepare(
        "SELECT a.*, c.FirstName, c.LastName, c.Email, c.Phone,
                c.Address, c.City, c.DateOfBirth, c.Gender,
                c.NationalID, c.IsActive,
                ap.ProductName, ap.AccountType, ap.InterestRate, ap.MinBalance,
                b.BranchName, b.IFSCCode
         FROM ACCOUNT a
         JOIN CUSTOMER c ON a.CustomerID = c.CustomerID
         JOIN ACCOUNTPRODUCT ap ON a.ProductID = ap.ProductID
         JOIN BRANCH b ON a.BranchID = b.BranchID
         WHERE c.CustomerID = ?
         LIMIT 1"
    );
    $stmt->execute([$userId]);
    return $stmt->fetch();
}

function generateAccountNumber(PDO $pdo): string {
    do {
        $num  = (string) mt_rand(1000000000, 9999999999);
        $stmt = $pdo->prepare("SELECT AccountNumber FROM ACCOUNT WHERE AccountNumber = ?");
        $stmt->execute([$num]);
    } while ($stmt->fetch());
    return $num;
}

function generateReference(string $prefix = 'TXN'): string {
    return $prefix . date('Ymd') . strtoupper(substr(uniqid(), -6));
}

// ─────────────────────────────────────────────────────────────
// CSRF PROTECTION
// ─────────────────────────────────────────────────────────────

function csrfToken(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf(): void {
    $token = $_POST['_csrf'] ?? $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (empty($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF token mismatch.');
    }
}

function csrfField(): string {
    $token = htmlspecialchars(csrfToken());
    return '<input type="hidden" name="_csrf" value="' . $token . '"><input type="hidden" name="csrf_token" value="' . $token . '">';
}

if (!function_exists('csrf_token')) {
    function csrf_token(): string {
        return csrfToken();
    }
}
if (!function_exists('csrf_field')) {
    function csrf_field(): string {
        return csrfField();
    }
}
if (!function_exists('verify_csrf')) {
    function verify_csrf(): void {
        verifyCsrf();
    }
}

// ─────────────────────────────────────────────────────────────
// FLASH / TOAST MESSAGES
// ─────────────────────────────────────────────────────────────

function setToast(string $message, string $type = 'success', int $duration = 3500, string $title = ''): void {
    $_SESSION['toast'] = [
        'message'  => $message,
        'type'     => $type,
        'duration' => $duration,
        'title'    => $title
    ];
}

function getToast(): ?array {
    if (isset($_SESSION['toast'])) {
        $t = $_SESSION['toast'];
        unset($_SESSION['toast']);
        return $t;
    }
    return null;
}

// ─────────────────────────────────────────────────────────────
// OUTPUT & ROUTING HELPERS
// ─────────────────────────────────────────────────────────────

function e(mixed $s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function sanitize(string $s): string {
    return trim(strip_tags($s));
}

function redirect(string $url): never {
    if (!str_starts_with($url, 'http://') && !str_starts_with($url, 'https://') && !str_starts_with($url, '/')) {
        $url = (BASE_URL ? BASE_URL . '/' : '/') . ltrim($url, '/');
    }
    header("Location: $url");
    exit;
}

/** Return path relative to the asha_bank root */
function rootPath(string $path = ''): string {
    // Detect depth based on current script path
    $script = $_SERVER['SCRIPT_FILENAME'] ?? '';
    $root   = realpath(__DIR__ . '/..');
    $cur    = realpath(dirname($script));
    $rel    = '';
    if ($cur && $root && strpos($cur, $root) === 0) {
        $depth = substr_count(str_replace($root, '', $cur), DIRECTORY_SEPARATOR);
        $rel   = str_repeat('../', $depth);
    }
    return $rel . $path;
}

// ─────────────────────────────────────────────────────────────
// FORMATTING
// ─────────────────────────────────────────────────────────────

function formatBDT(float $amount): string {
    return CURRENCY_SYMBOL . ' ' . number_format($amount, 2);
}

function formatDate(string $date, string $format = 'd M Y'): string {
    return $date ? date($format, strtotime($date)) : '—';
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return floor($diff / 60) . 'm ago';
    if ($diff < 86400)  return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}

function maskCardNumber(string $num): string {
    return '**** **** **** ' . substr($num, -4);
}

function getCardTier(float $balance): array {
    if ($balance >= 1000000) return ['name' => 'Black Edition', 'color' => '#1a1a1a', 'accent' => '#d4af37'];
    if ($balance >= 500000)  return ['name' => 'Platinum',      'color' => '#2a3a4a', 'accent' => '#c0c0c0'];
    if ($balance >= 100000)  return ['name' => 'Gold',          'color' => '#3a2a1a', 'accent' => '#ffd700'];
    if ($balance >= 10000)   return ['name' => 'Silver',        'color' => '#2a3a3a', 'accent' => '#c0c0c0'];
    return                          ['name' => 'Classic',       'color' => '#185FA5', 'accent' => '#ffd700'];
}

function navIcon(string $icon): string {
    $map = [
        'home'          => 'bx bxs-dashboard',
        'dashboard'     => 'bx bxs-dashboard',
        'users'         => 'bx bx-user',
        'customers'     => 'bx bx-group',
        'activity'      => 'bx bx-trending-up',
        'transactions'  => 'bx bx-transfer',
        'credit-card'   => 'bx bx-credit-card',
        'accounts'      => 'bx bx-credit-card',
        'briefcase'     => 'bx bx-briefcase',
        'staff'         => 'bx bx-briefcase',
        'shield'        => 'bx bx-shield-quarter',
        'kyc'           => 'bx bx-shield-quarter',
        'bell'          => 'bx bx-bell',
        'notifications' => 'bx bx-bell',
        'bar-chart-2'   => 'bx bx-bar-chart-alt-2',
        'reports'       => 'bx bx-bar-chart-alt-2',
        'map-pin'       => 'bx bx-map-pin',
        'branches'      => 'bx bx-map-pin',
        'message-dots'  => 'bx bx-message-dots',
        'feedback'      => 'bx bx-message-dots',
        'trending-up'   => 'bx bx-trending-up',
        'settings'      => 'bx bx-cog',
        'search'        => 'bx bx-search',
    ];
    if (str_starts_with($icon, 'bx ') || str_starts_with($icon, 'bxs-') || str_starts_with($icon, 'bx-')) {
        $cls = str_starts_with($icon, 'bx ') ? $icon : ('bx ' . $icon);
    } else {
        $cls = $map[$icon] ?? ('bx bx-' . $icon);
    }
    return '<i class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '"></i>';
}

// ─────────────────────────────────────────────────────────────
// NOTIFICATIONS
// ─────────────────────────────────────────────────────────────

function sendNotification(PDO $pdo, int $customerId, string $title, string $message, string $type = 'info'): void {
    $pdo->prepare(
        "INSERT INTO NOTIFICATIONS (customer_id, title, message, type) VALUES (?, ?, ?, ?)"
    )->execute([$customerId, $title, $message, $type]);
}

function getUnreadNotifications(PDO $pdo, int $customerId): int {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM NOTIFICATIONS WHERE customer_id = ? AND is_read = 0");
    $stmt->execute([$customerId]);
    return (int) $stmt->fetchColumn();
}

// ─────────────────────────────────────────────────────────────
// PAGINATION
// ─────────────────────────────────────────────────────────────

function paginate(PDO $pdo, string $sql, array $params, int $page, int $perPage = 20): array {
    $countSql   = "SELECT COUNT(*) FROM ({$sql}) AS _count";
    $total      = (int)$pdo->prepare($countSql)->execute($params) ? $pdo->query("SELECT COUNT(*) FROM ({$sql}) AS _c")->fetchColumn() : 0;
    $offset     = ($page - 1) * $perPage;
    $rows       = $pdo->prepare($sql . " LIMIT $perPage OFFSET $offset");
    $rows->execute($params);
    return [
        'data'        => $rows->fetchAll(),
        'total'       => $total,
        'pages'       => (int)ceil($total / $perPage),
        'current'     => $page,
        'per_page'    => $perPage,
    ];
}
