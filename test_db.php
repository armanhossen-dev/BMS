<?php
echo "<h2>Database Connection Test</h2>";

// Test database connection
try {
    $pdo = new PDO("mysql:host=localhost;charset=utf8mb4", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Check if database exists
    $stmt = $pdo->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = 'asha_bank'");
    if($stmt->fetch()) {
        echo "✅ Database 'asha_bank' exists<br>";
        
        // Connect to database
        $pdo->exec("USE asha_bank");
        
        // Check tables
        $tables = ['CUSTOMER', 'ACCOUNT', 'DIGITALBANKINGUSER', 'CARDS', 'NOMINEE'];
        foreach($tables as $table) {
            $result = $pdo->query("SHOW TABLES LIKE '$table'");
            if($result->rowCount() > 0) {
                echo "✅ Table '$table' exists<br>";
            } else {
                echo "❌ Table '$table' is MISSING! Please import the SQL file.<br>";
            }
        }
    } else {
        echo "❌ Database 'asha_bank' does NOT exist! Please create it and import the SQL file.<br>";
    }
    
} catch(PDOException $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}
?>