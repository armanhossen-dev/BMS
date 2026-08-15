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

$bangladeshCities = ['Dhaka', 'Chittagong', 'Khulna', 'Rajshahi', 'Barisal', 'Sylhet', 'Rangpur', 'Mymensingh'];

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = floatval($_POST['amount']);
    $method = $_POST['method'];
    $atmCard = $_POST['atm_card'] ?? '';
    $city = $_POST['city'] ?? '';
    $branchName = $_POST['branch_name'] ?? '';
    $mobileNumber = $_POST['mobile_number'] ?? '';
    
    if($amount <= 0) {
        $error = __("invalid_amount");
    } elseif($amount > $balance) {
        $error = __("insufficient_balance") . " " . formatBDT($balance);
    } else {
        try {
            $pdo->beginTransaction();
            $stmt = $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance - ? WHERE CustomerID = ?");
            $stmt->execute([$amount, $userId]);
            
            $description = "Withdrawal via ";
            if($method == 'atm') {
                $description .= "ATM - Card: ****" . substr($atmCard, -4);
            } elseif($method == 'branch_withdraw') {
                $description .= "Branch - $branchName, $city";
            } else {
                $description .= "Mobile Banking - $mobileNumber";
            }
            
            $refNumber = 'WDL' . time() . rand(100, 999);
            $stmt2 = $pdo->prepare("INSERT INTO TRANSACTION (TransactionTypeID, TransactionAmount, FromAccountNumber, FromCustomerID, Description, ReferenceNumber, TransactionStatus, TransactionDate) VALUES (2, ?, ?, ?, ?, ?, 'Completed', NOW())");
            $stmt2->execute([$amount, $userAccount['AccountNumber'], $userId, $description, $refNumber]);
            $pdo->commit();
            setToast(__("withdraw_success") . " " . formatBDT($amount), "success");
            redirect('dashboard.php');
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = __("withdraw_failed") . " " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('withdraw') ?> - Asha Bank</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/common.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --font-sans: 'Inter', sans-serif; --bg-primary: #FFFFFF; --bg-secondary: #F8FAFC; --text-primary: #0F172A; --border-color: #E2E8F0; --accent: #185FA5; --danger: #A32D2D; }
        body.dark { --bg-primary: #0F172A; --bg-secondary: #1E293B; --text-primary: #F1F5F9; --border-color: #334155; --accent: #3B82F6; }
        body { font-family: var(--font-sans); background: var(--bg-secondary); color: var(--text-primary); transition: all 0.3s ease; }
        .navbar { background: var(--bg-primary); border-bottom: 1px solid var(--border-color); padding: 16px 5%; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .logo { display: flex; align-items: center; gap: 12px; }
        .navbar-right { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .container { max-width: 550px; margin: 40px auto; padding: 0 20px; }
        .glass-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 24px; padding: 32px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; }
        input, select { width: 100%; padding: 12px; border: 1px solid var(--border-color); border-radius: 12px; background: var(--bg-secondary); color: var(--text-primary); }
        .btn { width: 100%; padding: 14px; background: var(--accent); color: white; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; }
        .error { background: #FCEBEB; color: #A32D2D; padding: 12px; border-radius: 12px; margin-bottom: 20px; }
        .balance-info { text-align: center; margin-bottom: 24px; padding: 16px; background: var(--bg-secondary); border-radius: 16px; }
        @media (max-width: 640px) { .navbar { flex-direction: column; } .navbar-right { justify-content: center; } }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo"><div class="logo-icon"><svg width="20" height="20" viewBox="0 0 16 16"><rect x="1" y="6" width="14" height="9" rx="1.5" fill="none" stroke="var(--accent)" stroke-width="1.2"/><path d="M4 6V4a4 4 0 0 1 8 0v2" stroke="var(--accent)" stroke-width="1.2"/><circle cx="8" cy="10.5" r="1.5" fill="var(--accent)"/></svg></div><h2>Asha Bank</h2></div>
        <div class="navbar-right">
            <div class="language-switcher"><a href="?lang=en" class="lang-btn <?= current_lang() == 'en' ? 'active' : '' ?>">EN</a><a href="?lang=bn" class="lang-btn <?= current_lang() == 'bn' ? 'active' : '' ?>">বাংলা</a></div>
            <button class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i> Dark</button>
            <a href="logout.php" style="background: var(--danger); color: white; padding: 8px 16px; border-radius: 40px;"><?= __('logout') ?></a>
        </div>
    </nav>
    
    <div class="container">
        <div class="back-button-container">
            <a href="dashboard.php" class="back-button"><i class="fas fa-arrow-left"></i> <?= __('back_to_dashboard') ?></a>
        </div>
        
        <div class="glass-card">
            <h2 style="margin-bottom: 20px;"><i class="fas fa-minus-circle"></i> <?= __('withdraw') ?></h2>
            <div class="balance-info"><strong><?= __('current_balance') ?>:</strong> <?= formatBDT($balance) ?></div>
            <?php if($error): ?><div class="error"><i class="fas fa-exclamation-circle"></i> <?= $error ?></div><?php endif; ?>
            <form method="POST" id="withdrawForm">
                <div class="form-group"><label><i class="fas fa-money-bill-wave"></i> <?= __('amount') ?> (BDT)</label><input type="number" name="amount" step="0.01" placeholder="<?= __('enter_amount') ?>" max="<?= $balance ?>" required></div>
                <div class="form-group"><label><i class="fas fa-credit-card"></i> Withdrawal Method</label><select name="method" id="withdrawMethod" required><option value="">Select Method</option><option value="atm">🏧 ATM Card Withdrawal</option><option value="branch_withdraw">🏦 Branch Withdrawal</option><option value="mobile">📱 Mobile Banking</option></select></div>
                <div id="atmFields" style="display: none;"><div class="form-group"><label><i class="fas fa-credit-card"></i> ATM Card Number</label><input type="text" name="atm_card" placeholder="XXXX-XXXX-XXXX-XXXX"></div></div>
                <div id="branchFields" style="display: none;"><div class="form-group"><label><i class="fas fa-city"></i> Select City</label><select name="city"><option value="">Select City</option><?php foreach($bangladeshCities as $city): ?><option value="<?= $city ?>"><?= $city ?></option><?php endforeach; ?></select></div><div class="form-group"><label><i class="fas fa-building"></i> Branch Name</label><input type="text" name="branch_name" placeholder="Branch name"></div></div>
                <button type="submit" class="btn"><?= __('confirm_withdrawal') ?> <i class="fas fa-money-bill-wave"></i></button>
            </form>
        </div>
    </div>
    <script>const methodSelect=document.getElementById('withdrawMethod'),atmFields=document.getElementById('atmFields'),branchFields=document.getElementById('branchFields');methodSelect.addEventListener('change',function(){atmFields.style.display='none';branchFields.style.display='none';if(this.value==='atm')atmFields.style.display='block';else if(this.value==='branch_withdraw')branchFields.style.display='block';});const themeToggle=document.getElementById('themeToggle');if(localStorage.getItem('theme')==='dark'){document.body.classList.add('dark');themeToggle.innerHTML='<i class="fas fa-sun"></i> Light';}themeToggle.addEventListener('click',()=>{document.body.classList.toggle('dark');localStorage.setItem('theme',document.body.classList.contains('dark')?'dark':'light');themeToggle.innerHTML=document.body.classList.contains('dark')?'<i class="fas fa-sun"></i> Light':'<i class="fas fa-moon"></i> Dark';});</script>
</body>
</html>