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

// Base URL — adjust if the app lives in a subfolder
if (!defined('BASE_URL')) {
    $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
    // Normalize when included from subfolders (admin/, staff/, api/, modules/*)
    define('BASE_URL', '/asha_bank');
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
