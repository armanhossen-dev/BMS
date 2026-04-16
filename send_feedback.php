<?php
require_once 'config/db.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'client') {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$userId = $_SESSION['user_id'];
$type = $_POST['type'] ?? 'feedback';
$subject = trim($_POST['subject'] ?? '');
$message = trim($_POST['message'] ?? '');

if(empty($subject) || empty($message)) {
    echo json_encode(['success' => false, 'error' => 'Subject and message are required']);
    exit();
}

if(strlen($message) > 500) {
    echo json_encode(['success' => false, 'error' => 'Message exceeds 500 characters']);
    exit();
}

try {
    // Create table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
        feedback_id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        subject VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('feedback', 'complaint', 'suggestion', 'issue') DEFAULT 'feedback',
        status ENUM('pending', 'read', 'replied', 'resolved') DEFAULT 'pending',
        staff_reply TEXT,
        replied_by INT,
        replied_at DATETIME,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    $stmt = $pdo->prepare("INSERT INTO feedback (customer_id, subject, message, type, status, created_at) VALUES (?, ?, ?, ?, 'pending', NOW())");
    $stmt->execute([$userId, $subject, $message, $type]);
    
    // Set session toast for success
    $_SESSION['toast'] = ['message' => '✓ Feedback sent successfully! Our team will respond within 24 hours.', 'type' => 'success'];
    
    echo json_encode(['success' => true, 'message' => 'Feedback sent successfully']);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>