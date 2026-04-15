<?php
require_once 'config/db.php';
if(!isLoggedIn()) redirect('login.php');

$userId = $_SESSION['user_id'];
$userAccount = $pdo->prepare("SELECT AccountNumber, AvailableBalance FROM ACCOUNT WHERE CustomerID = ?");
$userAccount->execute([$userId]);
$account = $userAccount->fetch();
$balance = $account['AvailableBalance'];
$error = '';

$bangladeshCities = [
    'Dhaka', 'Chittagong', 'Khulna', 'Rajshahi', 'Barisal', 'Sylhet', 
    'Rangpur', 'Mymensingh', 'Comilla', 'Narayanganj'
];

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = floatval($_POST['amount']);
    $method = $_POST['method'];
    $atmCard = $_POST['atm_card'] ?? '';
    $atmPin = $_POST['atm_pin'] ?? '';
    $city = $_POST['city'] ?? '';
    $branchName = $_POST['branch_name'] ?? '';
    $mobileNumber = $_POST['mobile_number'] ?? '';
    
    if($amount <= 0) {
        $error = "Please enter a valid amount";
    } elseif($amount > $balance) {
        $error = "Insufficient balance. Available: " . formatBDT($balance);
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
            $stmt2->execute([$amount, $account['AccountNumber'], $userId, $description, $refNumber]);
            
            $pdo->commit();
            setToast("Withdrawal successful! " . formatBDT($amount) . " debited", "success");
            redirect('dashboard.php');
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = "Withdrawal failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'bn' ?>">
<head>
    <meta charset="UTF-8">
    <title>Withdraw - Asha Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">আশা ব্যাংক</div>
        <div class="navbar-menu">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <button id="themeToggle" class="btn-outline"><i class="fas fa-moon"></i></button>
            <a href="logout.php" class="btn-danger">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="glass-card" style="max-width: 550px; margin: 0 auto;">
            <h2><i class="fas fa-minus-circle"></i> Withdraw Money</h2>
            <p>Available Balance: <strong><?= formatBDT($balance) ?></strong></p>
            
            <?php if($error): ?>
                <div style="color: var(--danger); padding: 0.75rem; background: rgba(192,57,43,0.1); border-radius: 12px; margin-bottom: 1rem;">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label>Amount (BDT)</label>
                    <input type="number" name="amount" step="0.01" placeholder="Enter amount" max="<?= $balance ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Withdrawal Method</label>
                    <select name="method" id="withdraw_method" required>
                        <option value="">Select Method</option>
                        <option value="atm">ATM Card Withdrawal</option>
                        <option value="branch_withdraw">Branch Withdrawal (Over Counter)</option>
                        <option value="mobile">Mobile Banking Withdrawal</option>
                    </select>
                </div>
                
                <!-- ATM Fields -->
                <div id="atm_fields" style="display: none;">
                    <div class="form-group">
                        <label>ATM Card Number</label>
                        <input type="text" name="atm_card" placeholder="XXXX-XXXX-XXXX-XXXX">
                    </div>
                    <div class="form-group">
                        <label>ATM PIN</label>
                        <input type="password" name="atm_pin" placeholder="Enter 4-digit PIN" maxlength="4">
                    </div>
                </div>
                
                <!-- Branch Withdrawal Fields -->
                <div id="branch_withdraw_fields" style="display: none;">
                    <div class="form-group">
                        <label>Select City</label>
                        <select name="city">
                            <option value="">Select City</option>
                            <?php foreach($bangladeshCities as $city): ?>
                                <option value="<?= $city ?>"><?= $city ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Branch Name</label>
                        <input type="text" name="branch_name" placeholder="Branch name">
                    </div>
                </div>
                
                <button type="submit" class="btn" style="width: 100%; margin-top: 1rem;">
                    <i class="fas fa-money-bill-wave"></i> Confirm Withdrawal
                </button>
            </form>
        </div>
    </div>
    
    <div id="toastContainer"></div>
    <script src="assets/js/main.js"></script>
</body>
</html>