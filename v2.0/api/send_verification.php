<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
header('Content-Type: application/json');

if (empty($_SESSION['customer_id'])) { echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }

$customerId = $_SESSION['customer_id'];
$code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
$expiry = date('Y-m-d H:i:s', time() + 600);

$stmt = $pdo->prepare("UPDATE KYC_VERIFICATIONS SET verification_code = ?, code_expiry = ? WHERE customer_id = ? ORDER BY submitted_at DESC LIMIT 1");
// MySQL doesn't support ORDER BY/LIMIT in UPDATE without subquery on some configs; do it safely:
$stmt = $pdo->prepare("SELECT kyc_id FROM KYC_VERIFICATIONS WHERE customer_id = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt->execute([$customerId]);
$kycId = $stmt->fetchColumn();

if ($kycId) {
    $pdo->prepare("UPDATE KYC_VERIFICATIONS SET verification_code=?, code_expiry=? WHERE kyc_id=?")->execute([$code, $expiry, $kycId]);
}

// Demo mode: code is returned directly instead of sent via SMS/Email
echo json_encode(['success' => true, 'message' => 'Verification code sent (demo mode).', 'demo_code' => $code]);
