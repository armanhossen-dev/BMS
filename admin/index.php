<?php
require_once '../config/db.php';

if(!isAdmin()) {
    redirect('../login.php');
}

// Handle actions
if(isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = $_GET['id'];
    
    if($action == 'delete_customer') {
        $pdo->prepare("UPDATE CUSTOMER SET IsActive = 0 WHERE CustomerID = ?")->execute([$id]);
        setToast("Customer deactivated", "success");
    } elseif($action == 'activate_customer') {
        $pdo->prepare("UPDATE CUSTOMER SET IsActive = 1 WHERE CustomerID = ?")->execute([$id]);
        setToast("Customer activated", "success");
    } elseif($action == 'freeze_account') {
        $pdo->prepare("UPDATE ACCOUNT SET AccountStatus = 'Frozen' WHERE CustomerID = ?")->execute([$id]);
        setToast("Account frozen", "warning");
    } elseif($action == 'verify_kyc') {
        $pdo->prepare("UPDATE CUSTOMERKYC SET KYCStatus = 'Verified', VerifiedDate = CURDATE() WHERE CustomerID = ?")->execute([$id]);
        setToast("KYC verified", "success");
    }
    redirect('index.php');
}

// Get statistics
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM CUSTOMER")->fetchColumn();
$totalBalance = $pdo->query("SELECT SUM(AvailableBalance) FROM ACCOUNT")->fetchColumn();
$totalTransactions = $pdo->query("SELECT COUNT(*) FROM TRANSACTION")->fetchColumn();
$pendingKYC = $pdo->query("SELECT COUNT(*) FROM CUSTOMERKYC WHERE KYCStatus = 'Pending'")->fetchColumn();
$frozenAccounts = $pdo->query("SELECT COUNT(*) FROM ACCOUNT WHERE AccountStatus = 'Frozen'")->fetchColumn();

// Get all customers
$customers = $pdo->query("SELECT c.*, a.AccountNumber, a.AvailableBalance, a.AccountStatus,
                          (SELECT KYCStatus FROM CUSTOMERKYC WHERE CustomerID = c.CustomerID LIMIT 1) as KYCStatus
                          FROM CUSTOMER c 
                          LEFT JOIN ACCOUNT a ON c.CustomerID = a.CustomerID 
                          ORDER BY c.CreatedAt DESC")->fetchAll();

// Get recent transactions
$transactions = $pdo->query("SELECT t.*, tt.TypeName, c.FirstName, c.LastName 
                              FROM TRANSACTION t 
                              JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
                              LEFT JOIN CUSTOMER c ON t.FromCustomerID = c.CustomerID
                              ORDER BY t.TransactionDate DESC LIMIT 30")->fetchAll();

// Language switch
if(isset($_GET['lang'])) {
    $_SESSION['language'] = $_GET['lang'];
    redirect('index.php');
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Asha Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .action-buttons { display: flex; gap: 0.5rem; flex-wrap: wrap; }
        .btn-icon { padding: 0.3rem 0.6rem; border-radius: 20px; font-size: 0.75rem; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-verify { background: #27ae60; color: white; }
        .btn-freeze { background: #e67e22; color: white; }
        .btn-delete { background: #c0392b; color: white; }
        .btn-activate { background: #2980b9; color: white; }
        .stat-card { cursor: pointer; }
        .stat-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand"><i class="fas fa-university"></i> Asha Bank <span style="color:#c0392b;">Admin Panel</span></div>
        <div class="navbar-menu">
            <div class="language-switch">
                <a href="?lang=en">EN</a> | <a href="?lang=bn">বাংলা</a>
            </div>
            <span><i class="fas fa-user-shield"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
            <button id="themeToggle" class="btn-outline"><i class="fas fa-moon"></i></button>
            <a href="../logout.php" class="btn-danger"><?= t('logout') ?></a>
        </div>
    </nav>
    
    <div class="container">
        <div class="dashboard-grid">
            <div class="stat-card" onclick="document.getElementById('customersSection').scrollIntoView()">
                <i class="fas fa-users stat-icon"></i>
                <div class="stat-value"><?= $totalCustomers ?></div>
                <div class="stat-label"><?= t('total_customers') ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-money-bill-wave stat-icon"></i>
                <div class="stat-value"><?= formatBDT($totalBalance) ?></div>
                <div class="stat-label"><?= t('total_balance') ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-exchange-alt stat-icon"></i>
                <div class="stat-value"><?= $totalTransactions ?></div>
                <div class="stat-label"><?= t('total_transactions') ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-id-card stat-icon"></i>
                <div class="stat-value"><?= $pendingKYC ?></div>
                <div class="stat-label"><?= t('pending_kyc') ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-ban stat-icon"></i>
                <div class="stat-value"><?= $frozenAccounts ?></div>
                <div class="stat-label">Frozen Accounts</div>
            </div>
        </div>
        
        <!-- Quick Actions -->
        <div class="glass-card" style="margin-top: 2rem;">
            <h3><i class="fas fa-tasks"></i> Quick Actions</h3>
            <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <a href="#customersSection" class="stat-card" style="text-align: center; text-decoration: none;">
                    <i class="fas fa-users"></i> Manage Customers
                </a>
                <a href="#transactionsSection" class="stat-card" style="text-align: center; text-decoration: none;">
                    <i class="fas fa-history"></i> View Transactions
                </a>
                <a href="#" onclick="generateReport()" class="stat-card" style="text-align: center; text-decoration: none;">
                    <i class="fas fa-chart-bar"></i> Generate Report
                </a>
                <a href="#" onclick="backupDatabase()" class="stat-card" style="text-align: center; text-decoration: none;">
                    <i class="fas fa-database"></i> Backup Database
                </a>
            </div>
        </div>
        
        <!-- Customer Management -->
        <div class="glass-card" style="margin-top: 2rem;" id="customersSection">
            <h3><i class="fas fa-users"></i> <?= t('all_customers') ?></h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th><th><?= t('name') ?></th><th><?= t('email') ?></th><th><?= t('phone') ?></th>
                            <th>Account</th><th>Balance</th><th>KYC</th><th>Status</th><th><?= t('action') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($customers as $c): ?>
                        <tr>
                            <td><?= $c['CustomerID'] ?></td>
                            <td><?= htmlspecialchars($c['FirstName'] . ' ' . $c['LastName']) ?></td>
                            <td><?= htmlspecialchars($c['Email']) ?></td>
                            <td><?= htmlspecialchars($c['Phone']) ?></td>
                            <td><?= $c['AccountNumber'] ?? 'N/A' ?></td>
                            <td><?= $c['AvailableBalance'] ? formatBDT($c['AvailableBalance']) : '0' ?></td>
                            <td><span class="badge <?= $c['KYCStatus'] == 'Verified' ? 'badge-success' : 'badge-warning' ?>"><?= $c['KYCStatus'] ?? 'Pending' ?></span></td>
                            <td><span class="badge <?= $c['AccountStatus'] == 'Active' ? 'badge-success' : 'badge-danger' ?>"><?= $c['AccountStatus'] ?? 'Active' ?></span></td>
                            <td class="action-buttons">
                                <?php if(($c['KYCStatus'] ?? 'Pending') != 'Verified'): ?>
                                    <a href="?action=verify_kyc&id=<?= $c['CustomerID'] ?>" class="btn-icon btn-verify" onclick="return confirm('Verify KYC for this customer?')"><i class="fas fa-check"></i> Verify</a>
                                <?php endif; ?>
                                <?php if(($c['AccountStatus'] ?? 'Active') != 'Frozen'): ?>
                                    <a href="?action=freeze_account&id=<?= $c['CustomerID'] ?>" class="btn-icon btn-freeze" onclick="return confirm('Freeze this account?')"><i class="fas fa-snowflake"></i> Freeze</a>
                                <?php endif; ?>
                                <?php if($c['IsActive'] == 1): ?>
                                    <a href="?action=delete_customer&id=<?= $c['CustomerID'] ?>" class="btn-icon btn-delete" onclick="return confirm('Deactivate this customer?')"><i class="fas fa-trash"></i> Deactivate</a>
                                <?php else: ?>
                                    <a href="?action=activate_customer&id=<?= $c['CustomerID'] ?>" class="btn-icon btn-activate" onclick="return confirm('Activate this customer?')"><i class="fas fa-play"></i> Activate</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Transactions -->
        <div class="glass-card" style="margin-top: 2rem;" id="transactionsSection">
            <h3><i class="fas fa-history"></i> <?= t('all_transactions') ?></h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>ID</th><th><?= t('date') ?></th><th>Type</th><th><?= t('amount') ?></th><th>Customer</th><th>Reference</th><th><?= t('status') ?></th></tr>
                    </thead>
                    <tbody>
                        <?php foreach($transactions as $txn): ?>
                        <tr>
                            <td><?= $txn['TransactionID'] ?></td>
                            <td><?= date('d M Y H:i', strtotime($txn['TransactionDate'])) ?></td>
                            <td><?= $txn['TypeName'] ?></td>
                            <td><?= formatBDT($txn['TransactionAmount']) ?></td>
                            <td><?= htmlspecialchars($txn['FirstName'] ?? 'System') . ' ' . ($txn['LastName'] ?? '') ?></td>
                            <td><?= $txn['ReferenceNumber'] ?? 'N/A' ?></td>
                            <td><span class="badge badge-success"><?= $txn['TransactionStatus'] ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div id="toastContainer">
        <?php $toast = getToast(); if($toast): ?>
            <div class="toast-notification <?= $toast['type'] ?>"><?= htmlspecialchars($toast['message']) ?></div>
        <?php endif; ?>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        function generateReport() {
            alert("Report generation feature - would export transaction history to CSV");
            window.location.href = '?export=csv';
        }
        function backupDatabase() {
            alert("Database backup feature - would create SQL backup");
        }
    </script>
</body>
</html>