<?php
require_once 'config/db.php';
session_start();

header('Content-Type: application/json');

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

$requestId = $_GET['id'] ?? 0;

$stmt = $pdo->prepare("SELECT r.*, c.FirstName, c.LastName FROM reactivation_requests r JOIN CUSTOMER c ON r.customer_id = c.CustomerID WHERE r.request_id = ?");
$stmt->execute([$requestId]);
$data = $stmt->fetch();

if($data) {
    echo json_encode([
        'customer_name' => $data['FirstName'] . ' ' . $data['LastName'],
        'reason' => $data['reason'],
        'status' => $data['status'],
        'admin_reply' => $data['admin_reply'],
        'estimated_timeframe' => $data['estimated_timeframe'],
        'created_at' => $data['created_at'],
        'reviewed_at' => $data['reviewed_at']
    ]);
} else {
    echo json_encode(['error' => 'Not found']);
}
?>
