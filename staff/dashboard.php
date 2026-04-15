<?php
require_once '../config/db.php';

if(!isStaff()) {
    redirect('../login.php');
}

// Get statistics
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM CUSTOMER")->fetchColumn();
$pendingKYC = $pdo->query("SELECT COUNT(*) FROM CUSTOMERKYC WHERE KYCStatus = 'Pending'")->fetchColumn();
$todayTransactions = $pdo->query("SELECT COUNT(*) FROM TRANSACTION WHERE DATE(TransactionDate) = CURDATE()")->fetchColumn();
$pendingTransactions = $pdo->query("SELECT COUNT(*) FROM TRANSACTION WHERE TransactionStatus = 'Pending'")->fetchColumn();

// Get pending KYC customers
$pendingKYCCustomers = $pdo->query("SELECT c.*, k.KYCID, k.DocumentType 
                                     FROM CUSTOMER c 
                                     JOIN CUSTOMERKYC k ON c.CustomerID = k.CustomerID 
                                     WHERE k.KYCStatus = 'Pending' 
                                     ORDER BY c.CreatedAt DESC")->fetchAll();

// Get recent customers
$recentCustomers = $pdo->query("SELECT * FROM CUSTOMER ORDER BY CreatedAt DESC LIMIT 10")->fetchAll();

// Get pending transactions (if any)
$pendingTrans = $pdo->query("SELECT t.*, tt.TypeName, c.FirstName, c.LastName 
                              FROM TRANSACTION t 
                              JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
                              LEFT JOIN CUSTOMER c ON t.FromCustomerID = c.CustomerID
                              WHERE t.TransactionStatus = 'Pending'
                              ORDER BY t.TransactionDate DESC")->fetchAll();

// Handle KYC verification
if(isset($_GET['verify_kyc']) && isset($_GET['id'])) {
    $pdo->prepare("UPDATE CUSTOMERKYC SET KYCStatus = 'Verified', VerifiedDate = CURDATE() WHERE CustomerID = ?")->execute([$_GET['id']]);
    setToast("KYC verified successfully", "success");
    redirect('dashboard.php');
}

// Handle transaction approval
if(isset($_GET['approve_txn']) && isset($_GET['id'])) {
    $pdo->prepare("UPDATE TRANSACTION SET TransactionStatus = 'Completed' WHERE TransactionID = ?")->execute([$_GET['id']]);
    setToast("Transaction approved", "success");
    redirect('dashboard.php');
}

// Language switch
if(isset($_GET['lang'])) {
    $_SESSION['language'] = $_GET['lang'];
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Asha Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .action-buttons { display: flex; gap: 0.5rem; }
        .btn-approve { background: #27ae60; color: white; padding: 0.2rem 0.8rem; border-radius: 20px; text-decoration: none; font-size: 0.75rem; }
        .btn-verify { background: #2980b9; color: white; padding: 0.2rem 0.8rem; border-radius: 20px; text-decoration: none; font-size: 0.75rem; }
        .stat-card { cursor: pointer; transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand"><i class="fas fa-university"></i> Asha Bank <span style="color:#e67e22;">Staff Portal</span></div>
        <div class="navbar-menu">
            <div class="language-switch">
                <a href="?lang=en">EN</a> | <a href="?lang=bn">বাংলা</a>
            </div>
            <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
            <button id="themeToggle" class="btn-outline"><i class="fas fa-moon"></i></button>
            <a href="../logout.php" class="btn-danger"><?= t('logout') ?></a>
        </div>
    </nav>
    
    <div class="container">
        <div class="dashboard-grid">
            <div class="stat-card">
                <i class="fas fa-users stat-icon"></i>
                <div class="stat-value"><?= $totalCustomers ?></div>
                <div class="stat-label"><?= t('total_customers') ?></div>
            </div>
            <div class="stat-card" onclick="document.getElementById('kycSection').scrollIntoView()">
                <i class="fas fa-id-card stat-icon"></i>
                <div class="stat-value"><?= $pendingKYC ?></div>
                <div class="stat-label"><?= t('pending_kyc') ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-exchange-alt stat-icon"></i>
                <div class="stat-value"><?= $todayTransactions ?></div>
                <div class="stat-label"><?= t('today_transactions') ?></div>
            </div>
            <div class="stat-card">
                <i class="fas fa-clock stat-icon"></i>
                <div class="stat-value"><?= $pendingTransactions ?></div>
                <div class="stat-label">Pending Approvals</div>
            </div>
        </div>
        
        <!-- Quick Actions for Staff -->
        <div class="glass-card" style="margin-top: 2rem;">
            <h3><i class="fas fa-tasks"></i> Staff Actions</h3>
            <div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));">
                <a href="#kycSection" class="stat-card" style="text-align: center; text-decoration: none;">
                    <i class="fas fa-id-card"></i> Verify KYC
                </a>
                <a href="#customerSection" class="stat-card" style="text-align: center; text-decoration: none;">
                    <i class="fas fa-user-plus"></i> View Customers
                </a>
                <a href="#pendingSection" class="stat-card" style="text-align: center; text-decoration: none;">
                    <i class="fas fa-clock"></i> Approve Transactions
                </a>
                <a href="#" onclick="issueCard()" class="stat-card" style="text-align: center; text-decoration: none;">
                    <i class="fas fa-credit-card"></i> Issue Card
                </a>
            </div>
        </div>
        
        <!-- Pending KYC Section -->
        <div class="glass-card" style="margin-top: 2rem;" id="kycSection">
            <h3><i class="fas fa-id-card"></i> Pending KYC Verification</h3>
            <?php if(empty($pendingKYCCustomers)): ?>
                <p>No pending KYC requests</p>
            <?php else: ?>
                <div class="table-container">
                    <table>
                        <thead><tr><th>ID</th><th><?= t('name') ?></th><th><?= t('email') ?></th><th><?= t('phone') ?></th><th>Document</th><th><?= t('action') ?></th></tr></thead>
                        <tbody>
                            <?php foreach($pendingKYCCustomers as $c): ?>
                            <tr>
                                <td><?= $c['CustomerID'] ?></td>
                                <td><?= htmlspecialchars($c['FirstName'] . ' ' . $c['LastName']) ?></td>
                                <td><?= htmlspecialchars($c['Email']) ?></td>
                                <td><?= htmlspecialchars($c['Phone']) ?></td>
                                <td><?= $c['DocumentType'] ?? 'NID' ?></td>
                                <td><a href="?verify_kyc=1&id=<?= $c['CustomerID'] ?>" class="btn-verify" onclick="return confirm('Verify this customer\'s KYC?')"><i class="fas fa-check"></i> Verify</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Recent Customers -->
        <div class="glass-card" style="margin-top: 2rem;" id="customerSection">
            <h3><i class="fas fa-user-plus"></i> <?= t('recent_customers') ?></h3>
            <div class="table-container">
                <table>
                    <thead><tr><th>ID</th><th><?= t('name') ?></th><th><?= t('email') ?></th><th><?= t('phone') ?></th><th>Registered</th></tr></thead>
                    <tbody>
                        <?php foreach($recentCustomers as $c): ?>
                        <tr>
                            <td><?= $c['CustomerID'] ?></td>
                            <td><?= htmlspecialchars($c['FirstName'] . ' ' . $c['LastName']) ?></td>
                            <td><?= htmlspecialchars($c['Email']) ?></td>
                            <td><?= htmlspecialchars($c['Phone']) ?></td>
                            <td><?= date('d M Y', strtotime($c['CreatedAt'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Pending Transactions -->
        <?php if(!empty($pendingTrans)): ?>
        <div class="glass-card" style="margin-top: 2rem;" id="pendingSection">
            <h3><i class="fas fa-clock"></i> Pending Transactions (Need Approval)</h3>
            <div class="table-container">
                <table>
                    <thead><tr><th>ID</th><th>Date</th><th>Type</th><th>Amount</th><th>Customer</th><th>Action</th></tr></thead>
                    <tbody>
                        <?php foreach($pendingTrans as $txn): ?>
                        <tr>
                            <td><?= $txn['TransactionID'] ?></td>
                            <td><?= date('d M Y H:i', strtotime($txn['TransactionDate'])) ?></td>
                            <td><?= $txn['TypeName'] ?></td>
                            <td><?= formatBDT($txn['TransactionAmount']) ?></td>
                            <td><?= htmlspecialchars($txn['FirstName'] ?? 'Unknown') ?></td>
                            <td><a href="?approve_txn=1&id=<?= $txn['TransactionID'] ?>" class="btn-approve" onclick="return confirm('Approve this transaction?')"><i class="fas fa-check"></i> Approve</a></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div id="toastContainer">
        <?php $toast = getToast(); if($toast): ?>
            <div class="toast-notification <?= $toast['type'] ?>"><?= htmlspecialchars($toast['message']) ?></div>
        <?php endif; ?>
    </div>
    
    <script src="../assets/js/main.js"></script>
    <script>
        function issueCard() {
            let customerId = prompt("Enter Customer ID to issue card:");
            if(customerId) {
                alert("Card issuance requested for Customer ID: " + customerId);
                // You can implement AJAX call here
            }
        }
    </script>
</body>
</html>