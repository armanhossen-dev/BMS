<?php
require_once 'config/db.php';
session_start();

if(!isset($_SESSION['user_id'])) {
    echo json_encode([]);
    exit();
}

$userId = $_SESSION['user_id'];
$role = $_SESSION['role'] ?? 'client';

try {
    if($role == 'client') {
        $stmt = $pdo->prepare("SELECT * FROM feedback WHERE customer_id = ? ORDER BY created_at DESC LIMIT 20");
        $stmt->execute([$userId]);
    } elseif($role == 'staff') {
        $stmt = $pdo->prepare("SELECT f.*, c.FirstName, c.LastName FROM feedback f JOIN CUSTOMER c ON f.customer_id = c.CustomerID WHERE f.status != 'resolved' ORDER BY f.created_at DESC");
        $stmt->execute();
    } elseif($role == 'admin') {
        $stmt = $pdo->prepare("SELECT f.*, c.FirstName, c.LastName, s.first_name as staff_name FROM feedback f LEFT JOIN CUSTOMER c ON f.customer_id = c.CustomerID LEFT JOIN staff s ON f.replied_by = s.staff_id ORDER BY f.created_at DESC LIMIT 50");
        $stmt->execute();
    } else {
        echo json_encode([]);
        exit();
    }
    
    $feedbacks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($feedbacks);
} catch(Exception $e) {
    echo json_encode([]);
}
?>