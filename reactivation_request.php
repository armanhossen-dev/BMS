<?php
require_once 'config/db.php';
session_start();

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'client') {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$userId = $_SESSION['user_id'];
$reason = trim($_POST['reason'] ?? '');

if(empty($reason)) {
    echo json_encode(['success' => false, 'error' => 'Please provide a reason for reactivation']);
    exit();
}

try {
    // Check if there's already a pending request
    $check = $pdo->prepare("SELECT * FROM reactivation_requests WHERE customer_id = ? AND status = 'pending'");
    $check->execute([$userId]);
    
    if($check->fetch()) {
        echo json_encode(['success' => false, 'error' => 'You already have a pending reactivation request. Please wait for admin response.']);
        exit();
    }
    
    // Insert reactivation request
    $stmt = $pdo->prepare("INSERT INTO reactivation_requests (customer_id, reason, status, created_at) VALUES (?, ?, 'pending', NOW())");
    $stmt->execute([$userId, $reason]);
    
    // Notify admin (insert notification for admin panel)
    $adminNotif = $pdo->prepare("INSERT INTO notifications (customer_id, title, message, type) VALUES (NULL, 'New Account Reactivation Request', 'A customer has requested account reactivation. Please review.', 'warning')");
    $adminNotif->execute();
    
    echo json_encode(['success' => true, 'message' => 'Reactivation request sent to admin. You will be notified once reviewed.']);
} catch(Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
