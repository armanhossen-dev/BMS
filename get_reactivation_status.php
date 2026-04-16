<?php
require_once 'config/db.php';
session_start();

header('Content-Type: application/json');

if(!isset($_SESSION['user_id']) || $_SESSION['role'] != 'client') {
    echo json_encode(['success' => false, 'error' => 'Not authenticated']);
    exit();
}

$userId = $_SESSION['user_id'];

$stmt = $pdo->prepare("SELECT * FROM reactivation_requests WHERE customer_id = ? ORDER BY created_at DESC LIMIT 1");
$stmt->execute([$userId]);
$request = $stmt->fetch();

if($request) {
    echo json_encode([
        'success' => true,
        'has_request' => true,
        'status' => $request['status'],
        'reason' => $request['reason'],
        'admin_reply' => $request['admin_reply'],
        'estimated_timeframe' => $request['estimated_timeframe'],
        'created_at' => $request['created_at'],
        'reviewed_at' => $request['reviewed_at']
    ]);
} else {
    echo json_encode(['success' => true, 'has_request' => false]);
}
?>
