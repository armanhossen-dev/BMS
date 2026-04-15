<?php require_once 'config/db.php'; 
if(!isLoggedIn()) redirect('login.php');

$userId = $_SESSION['user_id'];
$userAccount = getUserAccount($pdo, $userId);
$message = '';
$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = floatval($_POST['amount']);
    $method = $_POST['method'];
    $transactionId = trim($_POST['transaction_id'] ?? '');
    
    if($amount <= 0) {
        $error = "Amount must be greater than 0";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Add balance
            $stmt = $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance + ? WHERE CustomerID = ?");
            $stmt->execute([$amount, $userId]);
            
            // Record transaction
            $refNumber = 'DEP' . time() . rand(100, 999);
            $stmt2 = $pdo->prepare("INSERT INTO TRANSACTION (TransactionTypeID, TransactionAmount, ToAccountNumber, ToCustomerID, Description, ReferenceNumber, TransactionStatus, TransactionDate) VALUES (1, ?, ?, ?, ?, ?, 'Completed', NOW())");
            $stmt2->execute([$amount, $userAccount['AccountNumber'], $userId, "Deposit via $method" . ($transactionId ? " (ID: $transactionId)" : ""), $refNumber]);
            
            $pdo->commit();
            setToast("Deposit successful! ₹" . number_format($amount, 2) . " added to your account", "success");
            redirect('dashboard.php');
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = "Deposit failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Deposit Money - Asha Bank</title>
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
                <i class="fas fa-plus-circle" style="font-size: 3rem; color: var(--success);"></i>
                <h2>Deposit Money</h2>
                <p class="text-muted">Add funds to your account</p>
            </div>
            
            <?php if($error): ?>
                <div class="alert alert-danger" style="background: rgba(192,57,43,0.1); padding: 0.75rem; border-radius: 12px; margin-bottom: 1rem;">
                    <i class="fas fa-exclamation-circle"></i> <?= $error ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" data-validate="true">
                <div class="form-group">
                    <label><i class="fas fa-rupee-sign"></i> Amount (₹)</label>
                    <input type="number" name="amount" step="0.01" placeholder="Enter amount to deposit" required>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-mobile-alt"></i> Deposit Method</label>
                    <select name="method" required>
                        <option value="Mobile Banking">Mobile Banking</option>
                        <option value="Cash Deposit">Cash Deposit (Branch)</option>
                        <option value="Cheque Deposit">Cheque Deposit</option>
                        <option value="NEFT/RTGS">NEFT/RTGS</option>
                    </select>
                </div>
                <div class="form-group">
                    <label><i class="fas fa-hashtag"></i> Transaction ID (Optional)</label>
                    <input type="text" name="transaction_id" placeholder="Reference number if any">
                </div>
                <button type="submit" class="btn btn-success btn-block">Deposit <i class="fas fa-check-circle"></i></button>
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