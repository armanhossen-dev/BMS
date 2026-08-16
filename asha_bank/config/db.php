<?php
/**
 * Asha Bank — Database Configuration
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'asha_bank');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_NAME', 'Asha Bank');
define('APP_CURRENCY', '৳');

/**
 * Base URL — auto-detected so the app works no matter what the project
 * folder is named or where it's deployed (root, subfolder, php -S, XAMPP,
 * Apache vhost, etc). This is what makes <?= BASE_URL ?>/assets/... resolve
 * correctly from any page, including ones nested in admin/, staff/, api/,
 * and modules/*.
 *
 * How it works: this file lives at <project>/config/db.php, so its parent
 * folder is always the project root. We compare that folder's real path to
 * the web server's DOCUMENT_ROOT to work out the URL prefix needed to reach
 * it. If they're the same folder, BASE_URL is '' (app is at the domain root).
 */
if (!defined('BASE_URL')) {
    $projectRoot = realpath(__DIR__ . '/..');
    $docRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? $projectRoot);

    if ($docRoot && $projectRoot && strpos($projectRoot, $docRoot) === 0) {
        $prefix = substr($projectRoot, strlen($docRoot));
        $prefix = str_replace('\\', '/', $prefix);
        define('BASE_URL', rtrim($prefix, '/'));
    } else {
        // Fallback: derive from the currently executing script's URL path.
        // Walks up out of any known subfolder (admin/staff/api/modules/*) so
        // BASE_URL always points at the project root regardless of which
        // page triggered this include.
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $scriptDir = preg_replace('#/(admin|staff|api)$#', '', $scriptDir);
        $scriptDir = preg_replace('#/modules/(loans|bills|cheques)$#', '', $scriptDir);
        define('BASE_URL', rtrim($scriptDir, '/'));
    }
}

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die('<div style="font-family:sans-serif;background:#0b0b0d;color:#f2f2f2;padding:40px;">
        <h2>⚠️ Database connection failed</h2>
        <p>Please import <code>database.sql</code> and check your credentials in <code>config/db.php</code>.</p>
        <p style="color:#888;font-size:13px;">' . htmlspecialchars($e->getMessage()) . '</p></div>');
}
