<?php
// run_fix.php - Run this once to fix all database issues
require_once 'config/db.php';

echo "<h2>🔧 Fixing Database Tables...</h2>";

// Create kyc_verifications table with correct structure
try {
    $pdo->exec("DROP TABLE IF EXISTS kyc_verifications");
    $pdo->exec("CREATE TABLE kyc_verifications (
        kyc_id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT NOT NULL,
        nid_number VARCHAR(50),
        phone_number VARCHAR(20),
        verification_code VARCHAR(10),
        status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
        rejection_reason TEXT,
        verified_at DATETIME,
        submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
    )");
    echo "<p style='color:green'>✅ kyc_verifications table recreated</p>";
} catch(PDOException $e) {
    echo "<p style='color:orange'>" . $e->getMessage() . "</p>";
}

// Create notifications table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
        notification_id INT AUTO_INCREMENT PRIMARY KEY,
        customer_id INT,
        title VARCHAR(200) NOT NULL,
        message TEXT NOT NULL,
        type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
        is_read TINYINT(1) DEFAULT 0,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
    )");
    echo "<p style='color:green'>✅ notifications table ready</p>";
} catch(PDOException $e) {
    echo "<p style='color:orange'>" . $e->getMessage() . "</p>";
}

// Insert default KYC records for existing customers
try {
    $customers = $pdo->query("SELECT CustomerID FROM CUSTOMER")->fetchAll();
    foreach($customers as $c) {
        $check = $pdo->prepare("SELECT * FROM kyc_verifications WHERE customer_id = ?");
        $check->execute([$c['CustomerID']]);
        if(!$check->fetch()) {
            $pdo->prepare("INSERT INTO kyc_verifications (customer_id, status) VALUES (?, 'pending')")->execute([$c['CustomerID']]);
        }
    }
    echo "<p style='color:green'>✅ Default KYC records created for all customers</p>";
} catch(PDOException $e) {
    echo "<p style='color:orange'>" . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>✅ Database fix complete!</h3>";
echo "<a href='dashboard.php' style='display:inline-block; padding:10px 20px; background:#185FA5; color:white; text-decoration:none; border-radius:8px;'>Go to Dashboard →</a>";
?>