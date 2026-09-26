<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if (empty($_SESSION['customer_id'])) {
    echo json_encode(['success' => false]); exit;
}
if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
    echo json_encode(['success' => false]); exit;
}

$customerId = $_SESSION['customer_id'];

if (!empty($_POST['all'])) {
    $pdo->prepare("UPDATE NOTIFICATIONS SET is_read = 1, read_at = NOW() WHERE customer_id = ? AND is_read = 0")
        ->execute([$customerId]);
    echo json_encode(['success' => true]);
    exit;
}

$id = (int)($_POST['id'] ?? 0);
$stmt = $pdo->prepare("UPDATE NOTIFICATIONS SET is_read = 1, read_at = NOW() WHERE notification_id = ? AND customer_id = ?");
$stmt->execute([$id, $customerId]);

echo json_encode(['success' => true]);
