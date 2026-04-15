<?php
echo "<h2>Debug Information</h2>";
echo "<pre>";

echo "=== Session Data ===\n";
session_start();
print_r($_SESSION);

echo "\n=== POST Data ===\n";
print_r($_POST);

echo "\n=== Server Path ===\n";
echo "Document Root: " . $_SERVER['DOCUMENT_ROOT'] . "\n";
echo "Script Name: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "Request URI: " . $_SERVER['REQUEST_URI'] . "\n";

// Check database connection
require_once 'config/db.php';
echo "\n=== Database Connection ===\n";
echo "Connected successfully\n";

// Check if tables exist
$tables = ['CUSTOMER', 'ACCOUNT', 'DIGITALBANKINGUSER'];
foreach($tables as $table) {
    $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
    echo "Table '$table': " . ($stmt->rowCount() > 0 ? "EXISTS" : "MISSING") . "\n";
}

echo "\n=== PHP Error Log ===\n";
echo "Error log location: " . ini_get('error_log') . "\n";

echo "</pre>";
?>