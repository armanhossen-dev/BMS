<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

$customerId = (int)($_SESSION['customer_id'] ?? $_SESSION['user_id'] ?? 0);
if ($customerId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Please log in again.']);
    exit;
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid request. Please refresh and try again.']);
    exit;
}

$rawType = $_POST['type'] ?? 'feedback';
$map = ['feedback' => 'feedback', 'suggestion' => 'feedback', 'complaint' => 'complaint', 'issue' => 'complaint', 'inquiry' => 'inquiry'];
$type = $map[$rawType] ?? 'feedback';
$subject = trim($_POST['subject'] ?? '');
$message = trim(substr($_POST['message'] ?? '', 0, 500));

if ($subject === '' || $message === '') {
    echo json_encode(['success' => false, 'message' => 'Please fill in subject and message.']);
    exit;
}

$stmt = $pdo->prepare("INSERT INTO FEEDBACK (customer_id, subject, message, type, status) VALUES (?,?,?,?, 'pending')");
$stmt->execute([$customerId, $subject, $message, $type]);

echo json_encode(['success' => true, 'message' => 'Thanks — your ' . $type . ' was submitted.']);
