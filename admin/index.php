<?php require_once '../config/db.php'; 
if(!isAdmin()) redirect('../login.php');

// Get all users
$users = $pdo->query("SELECT CustomerID, FirstName, LastName, Email, Phone, CreatedAt FROM CUSTOMER ORDER BY CreatedAt DESC")->fetchAll();

// Get all transactions
$transactions = $pdo->query("SELECT t.*, tt.TypeName, c.FirstName, c.LastName 
                              FROM TRANSACTION t 
                              JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
                              LEFT JOIN CUSTOMER c ON t.FromCustomerID = c.CustomerID
                              ORDER BY t.TransactionDate DESC LIMIT 50")->fetchAll();

// Get account stats
$stats = $pdo->query("SELECT COUNT(*) as total_customers, SUM(AvailableBalance) as total_balance FROM ACCOUNT WHERE AccountStatus = 'Active'")->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Asha Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-university"></i>
            Asha <span>Bank</span> <span style="font-size: 0.8rem; background: var(--danger); padding: 0.2rem 0.5rem; border-radius: 20px;">Admin</span>
        </div>
        <div class="navbar-menu">
            <span><i class="fas fa-user-shield"></i> Admin</span>
            <button id="themeToggle" class="btn-outline" style="background: transparent; padding: 0.5rem 1rem;">
                <i class="fas fa-moon"></i>
            </button>
            <a href="../logout.php" class="btn-danger" style="padding: 0.5rem 1rem;">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="dashboard-grid">
            <div class="stat-card text-center">
                <i class="fas fa-users stat-icon"></i>
                <div class="stat-value"><?= $stats['total_customers'] ?></div>
                <div class="stat-label">Total Customers</div>
            </div>
            <div class="stat-card text-center">
                <i class="fas fa-rupee-sign stat-icon"></i>
                <div class="stat-value">₹ <?= number_format($stats['total_balance'], 0) ?></div>
                <div class="stat-label">Total Bank Balance</div>
            </div>
            <div class="stat-card text-center">
                <i class="fas fa-exchange-alt stat-icon"></i>
                <div class="stat-value"><?= count($transactions) ?></div>
                <div class="stat-label">Recent Transactions</div>
            </div>
        </div>
        
        <!-- All Customers -->
        <div class="glass-card" style="margin-top: 2rem;">
            <h3><i class="fas fa-users"></i> All Customers</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Joined</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($users as $user): ?>
                            <tr>
                                <td><?= $user['CustomerID'] ?></td>
                                <td><?= htmlspecialchars($user['FirstName'] . ' ' . $user['LastName']) ?></td>
                                <td><?= htmlspecialchars($user['Email']) ?></td>
                                <td><?= htmlspecialchars($user['Phone']) ?></td>
                                <td><?= date('d M Y', strtotime($user['CreatedAt'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- All Transactions -->
        <div class="glass-card" style="margin-top: 2rem;">
            <h3><i class="fas fa-history"></i> All Transactions</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>From/To</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($transactions as $txn): ?>
                            <tr>
                                <td><?= date('d M Y H:i', strtotime($txn['TransactionDate'])) ?></td>
                                <td><?= $txn['TypeName'] ?></td>
                                <td>₹ <?= number_format($txn['TransactionAmount'], 2) ?></td>
                                <td><?= htmlspecialchars($txn['FirstName'] ?? 'System') ?> <?= htmlspecialchars($txn['LastName'] ?? '') ?></td>
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