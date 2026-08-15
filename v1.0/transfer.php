<?php
require_once 'config/db.php';
require_once 'config/language.php';

if(!isLoggedIn() || !isClient()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];

// Check if account is active
$statusMessage = getAccountStatusMessage($pdo, $userId);
if ($statusMessage) {
    setToast($statusMessage, 'warning');
    redirect('dashboard.php');
}

$userAccount = getUserAccount($pdo, $userId);
$balance = $userAccount['AvailableBalance'];
$error = '';

// Get user's recent beneficiaries
$beneficiaries = $pdo->prepare("SELECT * FROM BENEFICIARY WHERE CustomerID = ? AND IsActive = 1");
$beneficiaries->execute([$userId]);
$beneficiaryList = $beneficiaries->fetchAll();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $toAccount = trim($_POST['to_account']);
    $amount = floatval($_POST['amount']);
    $description = trim($_POST['description'] ?? 'Fund Transfer');
    
    if($amount <= 0) {
        $error = __("invalid_amount");
    } elseif($amount > $balance) {
        $error = __("insufficient_balance") . " " . formatBDT($balance);
    } else {
        $stmt = $pdo->prepare("SELECT a.AccountNumber, a.CustomerID, c.FirstName, c.LastName 
                               FROM ACCOUNT a 
                               JOIN CUSTOMER c ON a.CustomerID = c.CustomerID 
                               WHERE a.AccountNumber = ? AND a.AccountStatus = 'Active'");
        $stmt->execute([$toAccount]);
        $receiver = $stmt->fetch();
        
        if(!$receiver) {
            $error = __("account_not_found");
        } elseif($receiver['AccountNumber'] == $userAccount['AccountNumber']) {
            $error = __("cannot_transfer_self");
        } else {
            try {
                $pdo->beginTransaction();
                
                $stmt1 = $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance - ? WHERE AccountNumber = ?");
                $stmt1->execute([$amount, $userAccount['AccountNumber']]);
                
                $stmt2 = $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance + ? WHERE AccountNumber = ?");
                $stmt2->execute([$amount, $toAccount]);
                
                $refNumber = 'TXN' . time() . rand(100, 999);
                $stmt3 = $pdo->prepare("INSERT INTO TRANSACTION (TransactionTypeID, TransactionAmount, FromAccountNumber, ToAccountNumber, FromCustomerID, ToCustomerID, Description, ReferenceNumber, TransactionStatus, TransactionDate) VALUES (3, ?, ?, ?, ?, ?, ?, ?, 'Completed', NOW())");
                $stmt3->execute([$amount, $userAccount['AccountNumber'], $toAccount, $userId, $receiver['CustomerID'], $description, $refNumber]);
                
                $checkBen = $pdo->prepare("SELECT * FROM BENEFICIARY WHERE CustomerID = ? AND BeneficiaryAccountNumber = ?");
                $checkBen->execute([$userId, $toAccount]);
                if(!$checkBen->fetch()) {
                    $stmtBen = $pdo->prepare("INSERT INTO BENEFICIARY (CustomerID, BeneficiaryName, BeneficiaryAccountNumber, BeneficiaryIFSC, BeneficiaryBankName, IsActive) VALUES (?, ?, ?, 'ASHA0001', 'Asha Bank', 1)");
                    $stmtBen->execute([$userId, $receiver['FirstName'] . ' ' . $receiver['LastName'], $toAccount]);
                }
                
                $pdo->prepare("INSERT INTO notifications (customer_id, title, message, type) VALUES (?, 'Money Received', CONCAT('You have received ', ?, ' from ', ?), 'success')")->execute([$receiver['CustomerID'], formatBDT($amount), $_SESSION['username']]);
                
                $pdo->commit();
                setToast(__("transfer_success") . " " . formatBDT($amount), "success");
                redirect('dashboard.php');
                
            } catch(Exception $e) {
                $pdo->rollBack();
                $error = __("transfer_failed") . " " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('transfer') ?> - Asha Bank</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --font-sans: 'Inter', sans-serif; --bg-primary: #FFFFFF; --bg-secondary: #F8FAFC; --text-primary: #0F172A; --text-secondary: #475569; --border-color: #E2E8F0; --accent: #185FA5; --danger: #A32D2D; --success: #3B6D11; }
        body.dark { --bg-primary: #0F172A; --bg-secondary: #1E293B; --text-primary: #F1F5F9; --border-color: #334155; --accent: #3B82F6; }
        body { font-family: var(--font-sans); background: var(--bg-secondary); color: var(--text-primary); transition: all 0.3s ease; }
        .navbar { background: var(--bg-primary); border-bottom: 1px solid var(--border-color); padding: 16px 5%; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .logo { display: flex; align-items: center; gap: 12px; }
        .navbar-right { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .container { max-width: 550px; margin: 40px auto; padding: 0 20px; }
        .glass-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 24px; padding: 32px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; }
        input, select, textarea { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); }
        .btn { width: 100%; padding: 14px; background: var(--accent); color: white; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; }
        .error { background: #FCEBEB; color: #A32D2D; padding: 12px; border-radius: 12px; margin-bottom: 20px; }
        .balance-info { text-align: center; margin-bottom: 24px; padding: 16px; background: var(--bg-secondary); border-radius: 16px; }
        .beneficiary-item { padding: 10px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: background 0.2s; }
        .beneficiary-item:hover { background: var(--bg-secondary); }
        @media (max-width: 640px) { .navbar { flex-direction: column; } .navbar-right { justify-content: center; } }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">
            <div class="logo-icon"><svg width="20" height="20" viewBox="0 0 16 16"><rect x="1" y="6" width="14" height="9" rx="1.5" fill="none" stroke="var(--accent)" stroke-width="1.2"/><path d="M4 6V4a4 4 0 0 1 8 0v2" stroke="var(--accent)" stroke-width="1.2"/><circle cx="8" cy="10.5" r="1.5" fill="var(--accent)"/></svg></div>
            <h2>Asha Bank</h2>
        </div>
        <div class="navbar-right">
            <div class="language-switcher">
                <a href="?lang=en" class="lang-btn <?= current_lang() == 'en' ? 'active' : '' ?>">EN</a>
                <a href="?lang=bn" class="lang-btn <?= current_lang() == 'bn' ? 'active' : '' ?>">বাংলা</a>
            </div>
            <button class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i> Dark</button>
            <a href="logout.php" style="background: var(--danger); color: white; padding: 8px 16px; border-radius: 40px;"><?= __('logout') ?></a>
        </div>
    </nav>
    
    <div class="container">
        <div class="back-button-container">
            <a href="dashboard.php" class="back-button"><i class="fas fa-arrow-left"></i> <?= __('back_to_dashboard') ?></a>
        </div>
        
        <div class="glass-card">
            <h2 style="margin-bottom: 20px;"><i class="fas fa-exchange-alt"></i> <?= __('transfer') ?></h2>
            <div class="balance-info"><strong><?= __('current_balance') ?>:</strong> <?= formatBDT($balance) ?></div>
            <?php if($error): ?><div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
            <form method="POST">
                <div class="form-group"><label><i class="fas fa-building"></i> <?= __('receiver_account') ?></label><input type="number" name="to_account" placeholder="Enter 11-digit account number" required></div>
                <div class="form-group"><label><i class="fas fa-money-bill-wave"></i> <?= __('amount') ?> (BDT)</label><input type="number" name="amount" step="0.01" placeholder="<?= __('enter_amount') ?>" required></div>
                <div class="form-group"><label><i class="fas fa-comment"></i> <?= __('description') ?></label><textarea name="description" rows="2" placeholder="Reference / Note"></textarea></div>
                <button type="submit" class="btn"><?= __('send_money') ?> <i class="fas fa-paper-plane"></i></button>
            </form>
            <?php if(!empty($beneficiaryList)): ?>
            <div class="beneficiary-list"><p style="margin: 20px 0 10px; font-size: 12px;"><i class="fas fa-users"></i> Recent Beneficiaries</p>
                <?php foreach($beneficiaryList as $ben): ?><div class="beneficiary-item" onclick="document.querySelector('input[name=\'to_account\']').value = '<?= $ben['BeneficiaryAccountNumber'] ?>'"><strong><?= htmlspecialchars($ben['BeneficiaryName']) ?></strong><br><small>Account: <?= $ben['BeneficiaryAccountNumber'] ?></small></div><?php endforeach; ?>
            </div><?php endif; ?>
        </div>
    </div>
    <script>const themeToggle=document.getElementById('themeToggle');if(localStorage.getItem('theme')==='dark'){document.body.classList.add('dark');themeToggle.innerHTML='<i class="fas fa-sun"></i> Light';}themeToggle.addEventListener('click',()=>{document.body.classList.toggle('dark');localStorage.setItem('theme',document.body.classList.contains('dark')?'dark':'light');themeToggle.innerHTML=document.body.classList.contains('dark')?'<i class="fas fa-sun"></i> Light':'<i class="fas fa-moon"></i> Dark';});</script>
</body>
</html>