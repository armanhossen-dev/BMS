<?php require_once 'config/db.php'; 
if(!isLoggedIn()) redirect('login.php');

$userId = $_SESSION['user_id'];
$account = getUserAccount($pdo, $userId);
$balance = $account['AvailableBalance'] ?? 0;

// Get recent transactions
$stmt = $pdo->prepare("SELECT t.*, tt.TypeName 
                       FROM TRANSACTION t 
                       JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
                       WHERE t.FromCustomerID = ? OR t.ToCustomerID = ? 
                       ORDER BY t.TransactionDate DESC LIMIT 10");
$stmt->execute([$userId, $userId]);
$transactions = $stmt->fetchAll();

// Get nominee
$stmtNom = $pdo->prepare("SELECT * FROM NOMINEE WHERE CustomerID = ?");
$stmtNom->execute([$userId]);
$nominee = $stmtNom->fetch();

// Get card
$stmtCard = $pdo->prepare("SELECT * FROM CARDS WHERE CustomerID = ?");
$stmtCard->execute([$userId]);
$card = $stmtCard->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Asha Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-university"></i>
            Asha <span>Bank</span>
        </div>
        <div class="navbar-menu">
            <span><i class="fas fa-user"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
            <a href="dashboard.php"><i class="fas fa-home"></i> Home</a>
            <a href="transfer.php"><i class="fas fa-exchange-alt"></i> Transfer</a>
            <a href="deposit.php"><i class="fas fa-plus-circle"></i> Deposit</a>
            <a href="withdraw.php"><i class="fas fa-minus-circle"></i> Withdraw</a>
            <a href="profile.php"><i class="fas fa-user-friends"></i> Nominee</a>
            <button id="themeToggle" class="btn-outline" style="background: transparent; padding: 0.5rem 1rem;">
                <i class="fas fa-moon"></i>
            </button>
            <a href="logout.php" class="btn-danger" style="padding: 0.5rem 1rem; border-radius: 40px;"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>

    <div class="container">
        <!-- Balance Card -->
        <div class="balance-card fade-in">
            <div>
                <small>Available Balance</small>
                <div class="balance-amount">₹ <?= number_format($balance, 2) ?></div>
                <small>Account: <?= $account['AccountNumber'] ?></small>
            </div>
            <i class="fas fa-credit-card" style="position: absolute; bottom: 20px; right: 30px; font-size: 3rem; opacity: 0.3;"></i>
        </div>
        
        <!-- Dashboard Grid -->
        <div class="dashboard-grid">
            <div class="stat-card">
                <i class="fas fa-credit-card stat-icon"></div>
                <div class="stat-value"><?= $card ? substr($card['CardNumber'], -4) : 'Not issued' ?></div>
                <div class="stat-label">Card (last 4 digits)</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-user-friends stat-icon"></div>
                <div class="stat-value"><?= $nominee ? '✓ Added' : 'Not Added' ?></div>
                <div class="stat-label">Nominee Status</div>
            </div>
            
            <div class="stat-card">
                <i class="fas fa-chart-line stat-icon"></div>
                <div class="stat-value">Active</div>
                <div class="stat-label">Account Status</div>
            </div>
        </div>
        
        <!-- Transaction History -->
        <div class="glass-card" style="margin-top: 2rem;">
            <h3><i class="fas fa-history"></i> Recent Transactions</h3>
            <div class="table-container">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Type</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if(empty($transactions)): ?>
                            <tr><td colspan="4" class="text-center">No transactions yet</td></tr>
                        <?php else: ?>
                            <?php foreach($transactions as $txn): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($txn['TransactionDate'])) ?></td>
                                    <td><?= $txn['TypeName'] ?></td>
                                    <td class="<?= $txn['FromCustomerID'] == $userId ? 'text-danger' : 'text-success' ?>">
                                        <?= $txn['FromCustomerID'] == $userId ? '-' : '+' ?> ₹ <?= number_format($txn['TransactionAmount'], 2) ?>
                                    </td>
                                    <td><span class="badge badge-success"><?= $txn['TransactionStatus'] ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <div id="toastContainer">
        <?php $toast = getToast(); if($toast): ?>
            <div class="toast-notification <?= $toast['type'] ?>">
                <i class="fas <?= $toast['type'] == 'success' ? 'fa-check-circle' : 'fa-info-circle' ?>"></i>
                <span><?= htmlspecialchars($toast['message']) ?></span>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>