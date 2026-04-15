<?php
require_once 'config/db.php';
if(!isLoggedIn()) redirect('login.php');

$userId = $_SESSION['user_id'];
$userAccount = $pdo->prepare("SELECT AccountNumber FROM ACCOUNT WHERE CustomerID = ?");
$userAccount->execute([$userId]);
$account = $userAccount->fetch();
$message = '';
$error = '';

// Bangladesh Cities
$bangladeshCities = [
    'Dhaka', 'Chittagong', 'Khulna', 'Rajshahi', 'Barisal', 'Sylhet', 
    'Rangpur', 'Mymensingh', 'Comilla', 'Narayanganj', 'Gazipur', 
    'Jessore', 'Bogra', 'Dinajpur', 'Pabna', 'Tangail', 'Jamalpur'
];

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = floatval($_POST['amount']);
    $method = $_POST['method'];
    $mobileNumber = $_POST['mobile_number'] ?? '';
    $pin = $_POST['pin'] ?? '';
    $transactionId = $_POST['transaction_id'] ?? '';
    $city = $_POST['city'] ?? '';
    $branchName = $_POST['branch_name'] ?? '';
    
    if($amount <= 0) {
        $error = "Please enter a valid amount";
    } else {
        try {
            $pdo->beginTransaction();
            
            $stmt = $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance + ? WHERE CustomerID = ?");
            $stmt->execute([$amount, $userId]);
            
            $description = "Deposit via ";
            if(in_array($method, ['bkash', 'nagad', 'rocket', 'upai'])) {
                $methodNames = ['bkash' => 'bKash', 'nagad' => 'Nagad', 'rocket' => 'Rocket', 'upai' => 'Upai'];
                $description .= $methodNames[$method] . " (Mobile: $mobileNumber, TXN: $transactionId)";
            } else {
                $description .= "Cash Deposit at $branchName, $city";
            }
            
            $refNumber = 'DEP' . time() . rand(100, 999);
            $stmt2 = $pdo->prepare("INSERT INTO TRANSACTION (TransactionTypeID, TransactionAmount, ToAccountNumber, ToCustomerID, Description, ReferenceNumber, TransactionStatus, TransactionDate) VALUES (1, ?, ?, ?, ?, ?, 'Completed', NOW())");
            $stmt2->execute([$amount, $account['AccountNumber'], $userId, $description, $refNumber]);
            
            $pdo->commit();
            setToast("Deposit successful! " . formatBDT($amount) . " added", "success");
            redirect('dashboard.php');
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = "Deposit failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="<?= $_SESSION['language'] ?? 'bn' ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= t('deposit') ?> - Asha Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand">আশা ব্যাংক</div>
        <div class="navbar-menu">
            <a href="dashboard.php"><i class="fas fa-home"></i> <?= t('dashboard') ?></a>
            <button id="themeToggle" class="btn-outline"><i class="fas fa-moon"></i></button>
            <a href="logout.php" class="btn-danger"><?= t('logout') ?></a>
        </div>
    </nav>
    
    <div class="container">
        <div class="glass-card" style="max-width: 550px; margin: 0 auto;">
            <h2><i class="fas fa-plus-circle"></i> <?= t('deposit') ?></h2>
            
            <?php if($error): ?>
                <div style="color: var(--danger); padding: 0.75rem; background: rgba(192,57,43,0.1); border-radius: 12px; margin-bottom: 1rem;">
                    <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST">
                <div class="form-group">
                    <label><?= t('amount') ?> (BDT)</label>
                    <input type="number" name="amount" step="0.01" placeholder="Enter amount" required>
                </div>
                
                <div class="form-group">
                    <label>Deposit Method</label>
                    <select name="method" id="deposit_method" required>
                        <option value="">Select Method</option>
                        <option value="bkash">bKash</option>
                        <option value="nagad">Nagad</option>
                        <option value="rocket">Rocket</option>
                        <option value="upai">Upai</option>
                        <option value="branch">Cash Deposit (Branch)</option>
                    </select>
                </div>
                
                <!-- Mobile Banking Fields -->
                <div id="mobile_fields" style="display: none;">
                    <div class="form-group">
                        <label>Mobile Number</label>
                        <input type="tel" name="mobile_number" placeholder="01XXXXXXXXX">
                    </div>
                    <div class="form-group">
                        <label>PIN/Password</label>
                        <input type="password" name="pin" placeholder="Enter your mobile banking PIN">
                    </div>
                    <div class="form-group">
                        <label>Transaction ID (Reference)</label>
                        <input type="text" name="transaction_id" placeholder="Transaction ID from SMS">
                    </div>
                    <div class="form-group">
                        <label>Message (Optional)</label>
                        <textarea name="message" rows="2" placeholder="Any note about this deposit"></textarea>
                    </div>
                </div>
                
                <!-- Branch Deposit Fields -->
                <div id="branch_fields" style="display: none;">
                    <div class="form-group">
                        <label>Select City</label>
                        <select name="city" required>
                            <option value="">Select City</option>
                            <?php foreach($bangladeshCities as $city): ?>
                                <option value="<?= $city ?>"><?= $city ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Branch Name</label>
                        <input type="text" name="branch_name" placeholder="Branch name where depositing">
                    </div>
                    <div class="form-group">
                        <label>Teller/Reference Name</label>
                        <input type="text" name="teller_name" placeholder="Bank officer name (if known)">
                    </div>
                </div>
                
                <button type="submit" class="btn" style="width: 100%; margin-top: 1rem;">
                    <i class="fas fa-check-circle"></i> Confirm Deposit
                </button>
            </form>
        </div>
    </div>
    
    <div id="toastContainer"></div>
    <script src="assets/js/main.js"></script>
</body>
</html>