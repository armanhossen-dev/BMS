<?php require_once 'config/db.php'; 
if(!isLoggedIn()) redirect('login.php');

$userId = $_SESSION['user_id'];
$userAccount = getUserAccount($pdo, $userId);
$balance = $userAccount['AvailableBalance'];
$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $toAccount = trim($_POST['to_account']);
    $amount = floatval($_POST['amount']);
    
    if($amount <= 0) {
        $error = "Amount must be greater than 0";
    } elseif($amount > $balance) {
        $error = "Insufficient balance. Available: ₹ " . number_format($balance, 2);
    } else {
        // Find receiver
        $stmt = $pdo->prepare("SELECT a.AccountNumber, a.CustomerID, c.FirstName, c.LastName 
                               FROM ACCOUNT a 
                               JOIN CUSTOMER c ON a.CustomerID = c.CustomerID 
                               WHERE a.AccountNumber = ?");
        $stmt->execute([$toAccount]);
        $receiver = $stmt->fetch();
        
        if(!$receiver) {
            $error = "Receiver account not found";
        } elseif($receiver['AccountNumber'] == $userAccount['AccountNumber']) {
            $error = "Cannot transfer to your own account";
        } else {
            try {
                $pdo->beginTransaction();
                
                // Deduct from sender
                $stmt1 = $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance - ? WHERE AccountNumber = ?");
                $stmt1->execute([$amount, $userAccount['AccountNumber']]);
                
                // Add to receiver
                $stmt2 = $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance + ? WHERE AccountNumber = ?");
                $stmt2->execute([$amount, $toAccount]);
                
                // Record transaction
                $refNumber = 'TXN' . time() . rand(100, 999);
                $stmt3 = $pdo->prepare("INSERT INTO TRANSACTION (TransactionTypeID, TransactionAmount, FromAccountNumber, ToAccountNumber, FromCustomerID, ToCustomerID, ReferenceNumber, TransactionStatus, TransactionDate) VALUES (3, ?, ?, ?, ?, ?, ?, 'Completed', NOW())");
                $stmt3->execute([$amount, $userAccount['AccountNumber'], $toAccount, $userId, $receiver['CustomerID'], $refNumber]);
                
                $pdo->commit();
                setToast("Transfer successful! ₹" . number_format($amount, 2) . " sent to " . $receiver['FirstName'] . " " . $receiver['LastName'], "success");
                redirect('dashboard.php');
                
            } catch(Exception $e) {
                $pdo->rollBack();
                $error = "Transaction failed: " . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer Money - Asha Bank</title>
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
                <i class="fas fa-exchange-alt" style="font-size: 3rem; color: var(--accent);"></i>
                <h2>Transfer Money</h2>
                <p class="text-muted">Available Balance: <strong>₹ <?= number_format($balance, 2) ?></strong></p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-danger" style="background: rgba(192,57,43,0.1); padding: 0.75rem; border-radius: 12px; margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" data-validate="true">
                <div class="form-group">
                    <label><i class="fas fa-building"></i> Receiver's Account Number</label>
                    <input type="number" name="to_account" placeholder="Enter 11-digit account number" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-rupee-sign"></i> Amount (₹)</label>
                    <input type="number" name="amount" step="0.01" placeholder="Enter amount" required>
                </div>
                <button type="submit" class="btn btn-block">Send Money <i class="fas fa-paper-plane"></i></button>
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