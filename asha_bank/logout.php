<?php
require_once 'config/db.php';

if (isLoggedIn()) {
    // Audit log
    try {
        $pdo->prepare("INSERT INTO AUDITLOG (UserID, UserRole, Action, IPAddress, LoggedAt) VALUES (?, ?, 'logout', ?, NOW())")
            ->execute([$_SESSION['user_id'], $_SESSION['role'], $_SERVER['REMOTE_ADDR'] ?? '']);
    } catch (\Exception $e) {}
}

session_destroy();

setcookie(session_name(), '', time() - 3600, '/');

// Restart session for toast
session_start(['cookie_httponly' => true, 'cookie_samesite' => 'Strict']);
setToast('You have been signed out successfully.', 'success');

redirect('login.php');
