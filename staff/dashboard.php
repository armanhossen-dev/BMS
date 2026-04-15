<?php
require_once '../config/db.php';

// Check if user is staff
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'staff') {
    redirect('../login.php');
}

// Get pending transactions, customer queries, etc.
$pendingTransactions = $pdo->query("SELECT COUNT(*) FROM TRANSACTION WHERE TransactionStatus = 'Pending'")->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM CUSTOMER")->fetchColumn();
$todayTransactions = $pdo->query("SELECT COUNT(*) FROM TRANSACTION WHERE DATE(TransactionDate) = CURDATE()")->fetchColumn();

// Get recent customer registrations
$newCustomers = $pdo->query("SELECT * FROM CUSTOMER ORDER BY CreatedAt DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Asha Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-university"></i>
            Asha Bank <span style="color: #e67e22;">Staff Portal</span>
        </div>
        <div class="navbar-menu">
            <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
            <button id="themeToggle" class="btn-outline"><i class="fas fa-moon"></i></button>
            <a href="../logout.php" class="btn-danger">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="dashboard-grid">
            <div class="stat-card text-center">
                <i class="fas fa-users stat-icon"></i>
                <div class="stat-value"><?= $totalCustomers ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-card text-center">
                <i class="fas fa-clock stat-icon"></i>
                <div class="stat-value"><?= $pendingTransactions ?></div>
                <div class="stat-label">Pending Approvals</div>
            </div>
            <div class="stat-card text-center">
                <i class="fas fa-calendar-day stat-icon"></i>
                <div class="stat-value"><?= $todayTransactions ?></div>
                <div class="stat-label">Today's Transactions</div>
            </div>
        </div>
        
        <div class="glass-card" style="margin-top: 2rem;">
            <h3><i class="fas fa-user-plus"></i> New Customer Registrations</h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Registered</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($newCustomers as $customer): ?>
                        <tr>
                            <td><?= $customer['CustomerID'] ?></td>
                            <td><?= htmlspecialchars($customer['FirstName'] . ' ' . $customer['LastName']) ?></td>
                            <td><?= htmlspecialchars($customer['Email']) ?></td>
                            <td><?= htmlspecialchars($customer['Phone']) ?></td>
                            <td><?= date('d M Y', strtotime($customer['CreatedAt'])) ?></td>
                            <td><button class="btn-sm" onclick="alert('Process customer request')">Verify KYC</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div id="toastContainer"></div>
    <script src="../assets/js/main.js"></script>
</body>
</html>