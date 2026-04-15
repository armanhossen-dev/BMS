<?php
require_once '../config/db.php';

// Check if user is admin
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    redirect('../login.php');
}

// Get statistics
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM CUSTOMER")->fetchColumn();
$totalAccounts = $pdo->query("SELECT COUNT(*) FROM ACCOUNT")->fetchColumn();
$totalBalance = $pdo->query("SELECT SUM(AvailableBalance) FROM ACCOUNT")->fetchColumn();
$totalTransactions = $pdo->query("SELECT COUNT(*) FROM TRANSACTION")->fetchColumn();

// Get recent transactions
$transactions = $pdo->query("SELECT t.*, tt.TypeName, c.FirstName, c.LastName 
                              FROM TRANSACTION t 
                              JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
                              LEFT JOIN CUSTOMER c ON t.FromCustomerID = c.CustomerID
                              ORDER BY t.TransactionDate DESC LIMIT 20")->fetchAll();

// Get all customers
$customers = $pdo->query("SELECT c.*, a.AccountNumber, a.AvailableBalance 
                           FROM CUSTOMER c 
                           LEFT JOIN ACCOUNT a ON c.CustomerID = a.CustomerID 
                           ORDER BY c.CreatedAt DESC LIMIT 20")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Asha Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-university"></i>
            Asha Bank <span style="color: #c0392b;">Admin</span>
        </div>
        <div class="navbar-menu">
            <span><i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
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
                <i class="fas fa-building stat-icon"></i>
                <div class="stat-value"><?= $totalAccounts ?></div>
                <div class="stat-label">Total Accounts</div>
            </div>
            <div class="stat-card text-center">
                <i class="fas fa-money-bill-wave stat-icon"></i>
                <div class="stat-value"><?= formatBDT($totalBalance) ?></div>
                <div class="stat-label">Total Balance</div>
            </div>
            <div class="stat-card text-center">
                <i class="fas fa-exchange-alt stat-icon"></i>
                <div class="stat-value"><?= $totalTransactions ?></div>
                <div class="stat-label">Transactions</div>
            </div>
        </div>
        
        <!-- Recent Customers -->
        <div class="glass-card" style="margin-top: 2rem;">
            <h3><i class="fas fa-users"></i> Recent Customers</h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Account</th><th>Balance</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($customers as $customer): ?>
                        <tr>
                            <td><?= $customer['CustomerID'] ?></td>
                            <td><?= htmlspecialchars($customer['FirstName'] . ' ' . $customer['LastName']) ?></td>
                            <td><?= htmlspecialchars($customer['Email']) ?></td>
                            <td><?= htmlspecialchars($customer['Phone']) ?></td>
                            <td><?= $customer['AccountNumber'] ?? 'No Account' ?></td>
                            <td><?= $customer['AvailableBalance'] ? formatBDT($customer['AvailableBalance']) : '0' ?></td>
                            <td><span class="badge badge-success">Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Transactions -->
        <div class="glass-card" style="margin-top: 2rem;">
            <h3><i class="fas fa-history"></i> Recent Transactions</h3>
            <div class="table-container">
                <table class="table">
                    <thead>
                        <tr><th>Date</th><th>Type</th><th>Amount</th><th>Customer</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($transactions as $txn): ?>
                        <tr>
                            <td><?= date('d M Y H:i', strtotime($txn['TransactionDate'])) ?></td>
                            <td><?= $txn['TypeName'] ?></td>
                            <td><?= formatBDT($txn['TransactionAmount']) ?></td>
                            <td><?= htmlspecialchars($txn['FirstName'] ?? 'System') . ' ' . ($txn['LastName'] ?? '') ?></td>
                            <td><span class="badge badge-success"><?= $txn['TransactionStatus'] ?></span></td>
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