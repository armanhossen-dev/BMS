<?php
require_once 'config/db.php';
if(!isLoggedIn()) redirect('login.php');

$userId = $_SESSION['user_id'];
$account = $pdo->prepare("SELECT a.*, c.FirstName, c.LastName, c.Email, c.Phone FROM ACCOUNT a JOIN CUSTOMER c ON a.CustomerID = c.CustomerID WHERE c.CustomerID = ?");
$account->execute([$userId]);
$accountData = $account->fetch();

$balance = $accountData['AvailableBalance'] ?? 0;

// Get card details
$card = $pdo->prepare("SELECT * FROM CARDS WHERE CustomerID = ?");
$card->execute([$userId]);
$cardData = $card->fetch();

// Get transactions
$transactions = $pdo->prepare("SELECT t.*, tt.TypeName FROM TRANSACTION t JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID WHERE t.FromCustomerID = ? OR t.ToCustomerID = ? ORDER BY t.TransactionDate DESC LIMIT 10");
$transactions->execute([$userId, $userId]);
$transactionsData = $transactions->fetchAll();

// Language toggle
if(isset($_GET['lang'])) {
    $_SESSION['language'] = $_GET['lang'];
    redirect('dashboard.php');
}
$currentLang = $_SESSION['language'] ?? 'bn';
?>
<!DOCTYPE html>
<html lang="<?= $currentLang ?>">
<head>
    <meta charset="UTF-8">
    <title>Dashboard - Asha Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">
            <i class="fas fa-university"></i> আশা ব্যাংক
        </div>
        <div class="navbar-menu">
            <div class="language-switch">
                <a href="?lang=bn" style="<?= $currentLang == 'bn' ? 'font-weight:bold' : '' ?>">বাংলা</a> |
                <a href="?lang=en" style="<?= $currentLang == 'en' ? 'font-weight:bold' : '' ?>">English</a>
            </div>
            <span><i class="fas fa-user"></i> <?= htmlspecialchars($accountData['FirstName']) ?></span>
            <a href="dashboard.php"><i class="fas fa-home"></i> <?= t('dashboard') ?></a>
            <a href="transfer.php"><i class="fas fa-exchange-alt"></i> <?= t('transfer') ?></a>
            <a href="deposit.php"><i class="fas fa-plus-circle"></i> <?= t('deposit') ?></a>
            <a href="withdraw.php"><i class="fas fa-minus-circle"></i> <?= t('withdraw') ?></a>
            <a href="profile.php"><i class="fas fa-user-friends"></i> <?= t('nominee') ?></a>
            <button id="themeToggle" class="btn-outline"><i class="fas fa-moon"></i></button>
            <a href="logout.php" class="btn-danger"><?= t('logout') ?></a>
        </div>
    </nav>

    <div class="container">
        <!-- Clickable Balance Card -->
        <div class="balance-card" id="balanceCard" style="cursor: pointer;">
            <div>
                <small><?= t('balance') ?></small>
                <div class="balance-amount"><?= formatBDT($balance) ?></div>
                <small><?= t('account_no') ?>: <?= $accountData['AccountNumber'] ?></small>
            </div>
            <i class="fas fa-credit-card" style="position: absolute; bottom: 20px; right: 30px; font-size: 3rem; opacity: 0.3;"></i>
            <small style="position: absolute; bottom: 20px; left: 30px;"><i class="fas fa-info-circle"></i> Click for card details</small>
        </div>
        
        <div class="dashboard-grid">
            <div class="stat-card">
                <i class="fas fa-credit-card stat-icon"></i>
                <div class="stat-value"><?= $cardData ? '****' . substr($cardData['CardNumber'], -4) : 'No Card' ?></div>
                <div class="stat-label">Card (last 4 digits)</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-chart-line stat-icon"></i>
                <div class="stat-value">Active</div>
                <div class="stat-label">Account Status</div>
            </div>
            <div class="stat-card">
                <i class="fas fa-globe stat-icon"></i>
                <div class="stat-value">Bangladesh</div>
                <div class="stat-label">BDT Currency</div>
            </div>
        </div>
        
        <div class="glass-card" style="margin-top: 2rem;">
            <h3><i class="fas fa-history"></i> <?= t('transactions') ?></h3>
            <div class="table-container">
                <table>
                    <thead><tr><th><?= t('date') ?></th><th>Type</th><th><?= t('amount') ?></th><th><?= t('status') ?></th></tr></thead>
                    <tbody>
                        <?php foreach($transactionsData as $txn): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($txn['TransactionDate'])) ?></td>
                            <td><?= $txn['TypeName'] == 'Deposit' ? 'Deposit' : ($txn['TypeName'] == 'Withdrawal' ? 'Withdraw' : 'Transfer') ?></td>
                            <td class="<?= ($txn['FromCustomerID'] == $userId) ? 'text-danger' : 'text-success' ?>">
                                <?= ($txn['FromCustomerID'] == $userId) ? '-' : '+' ?> <?= formatBDT($txn['TransactionAmount']) ?>
                            </td>
                            <td><span class="badge badge-success"><?= t('completed') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Card Details Modal -->
    <div id="cardModal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h3><i class="fas fa-credit-card"></i> <?= t('card_details') ?></h3>
            <?php if($cardData): ?>
                <div class="form-group">
                    <label><?= t('card_number') ?>:</label>
                    <p><strong><?= chunk_split($cardData['CardNumber'], 4, ' ') ?></strong></p>
                </div>
                <div class="form-group">
                    <label><?= t('expiry') ?>:</label>
                    <p><strong><?= date('m/Y', strtotime($cardData['ExpiryDate'])) ?></strong></p>
                </div>
                <div class="form-group">
                    <label><?= t('cvv') ?>:</label>
                    <p><strong>***</strong> (hidden for security)</p>
                </div>
            <?php else: ?>
                <p>No card issued yet. Please contact branch.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <div id="toastContainer">
        <?php $toast = getToast(); if($toast): ?>
            <div class="toast-notification <?= $toast['type'] ?>">
                <i class="fas fa-check-circle"></i> <?= htmlspecialchars($toast['message']) ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script src="assets/js/main.js"></script>
</body>
</html>