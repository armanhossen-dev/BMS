<?php require_once 'config/db.php'; 
if(!isLoggedIn()) redirect('login.php');

$userId = $_SESSION['user_id'];
$userAccount = getUserAccount($pdo, $userId);
$balance = $userAccount['AvailableBalance'];
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = floatval($_POST['amount']);
    $method = $_POST['method'];
    
    if($amount <= 0) {
        $error = "Amount must be greater than 0";
    } elseif($amount > $balance) {
        $error = "Insufficient balance. Available: ₹ " . number_format($balance, 2);
    } else {
        try {
            $pdo->beginTransaction();
            
            // Deduct balance
            $stmt = $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance - ? WHERE CustomerID = ?");
            $stmt->execute([$amount, $userId]);
            
            // Record transaction
            $refNumber = 'WDL' . time() . rand(100, 999);
            $stmt2 = $pdo->prepare("INSERT INTO TRANSACTION (TransactionTypeID, TransactionAmount, FromAccountNumber, FromCustomerID, Description, ReferenceNumber, TransactionStatus, TransactionDate) VALUES (2, ?, ?, ?, ?, ?, 'Completed', NOW())");
            $stmt2->execute([$amount, $userAccount['AccountNumber'], $userId, "Withdrawal via $method", $refNumber]);
            
            $pdo->commit();
            setToast("Withdrawal successful! ₹" . number_format($amount, 2) . " debited from your account", "success");
            redirect('dashboard.php');
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = "Withdrawal failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdraw Money - Asha Bank</title>
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
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <button id="themeToggle" class="btn-outline" style="background: transparent; padding: 0.5rem 1rem;">
                <i class="fas fa-moon"></i>
            </button>
            <a href="logout.php" class="btn-danger" style="padding: 0.5rem 1rem;">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="glass-card fade-in" style="max-width: 550px; margin: 0 auto;">
            <div class="text-center">
                <i class="fas fa-minus-circle" style="font-size: 3rem; color: var(--warning);"></i>
                <h2>Withdraw Money</h2>
                <p class="text-muted">Available Balance: <strong>₹ <?= number_format($balance, 2) ?></strong></p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-danger" style="background: rgba(192,57,43,0.1); padding: 0.75rem; border-radius: 12px; margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" data-validate="true">
                <div class="form-group">
                    <label><i class="fas fa-rupee-sign"></i> Amount (₹)</label>
                    <input type="number" name="amount" step="0.01" placeholder="Enter amount to withdraw" max="<?= $balance ?>" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-credit-card"></i> Withdrawal Method</label>
                    <select name="method" required>
                        <option value="Offline (Bank Visit)">Offline (Bank Visit)</option>
                        <option value="Card Withdrawal">Card Withdrawal (ATM)</option>
                        <option value="Mobile Banking">Mobile Banking</option>
                    </select>
                </div>
                <button type="submit" class="btn btn-warning btn-block">Withdraw <i class="fas fa-money-bill-wave"></i></button>
            </form>
            
            <div class="text-center mt-3">
                <a href="dashboard.php">← Back to Dashboard</a>
            </div>
        </div>
    </div>
    
    <div id="toastContainer"></div>
    <script src="assets/js/main.js"></script>
</body>
</html>