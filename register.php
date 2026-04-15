<?php require_once 'config/db.php'; 
if(isLoggedIn()) redirect('dashboard.php');

$error = '';
$success = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $address = trim($_POST['address']);
    $dob = $_POST['dob'];
    
    try {
        $pdo->beginTransaction();
        
        // Check if email exists
        $check = $pdo->prepare("SELECT CustomerID FROM CUSTOMER WHERE Email = ?");
        $check->execute([$email]);
        if($check->fetch()) {
            throw new Exception("Email already registered");
        }
        
        // Insert Customer
        $stmt = $pdo->prepare("INSERT INTO CUSTOMER (FirstName, LastName, Email, Phone, Address, DateOfBirth, CustomerCategoryID, PrimaryBranchID, CreatedAt, IsActive) VALUES (?, ?, ?, ?, ?, ?, 1, 1, NOW(), 1)");
        $stmt->execute([$firstName, $lastName, $email, $phone, $address, $dob]);
        $customerId = $pdo->lastInsertId();
        
        // Generate Account Number
        $accountNumber = generateAccountNumber($pdo);
        
        // Create Account
        $stmtAcc = $pdo->prepare("INSERT INTO ACCOUNT (AccountNumber, ProductID, CustomerID, BranchID, OpeningDate, AvailableBalance, AccountStatus) VALUES (?, 1, ?, 1, CURDATE(), 0, 'Active')");
        $stmtAcc->execute([$accountNumber, $customerId]);
        
        // Create Card
        $pdo->exec("CREATE TABLE IF NOT EXISTS CARDS (
            CardID INT AUTO_INCREMENT PRIMARY KEY,
            CardNumber VARCHAR(20),
            CustomerID INT,
            ExpiryDate DATE,
            CVV VARCHAR(4),
            FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID)
        )");
        
        $cardNumber = "4" . mt_rand(100000000000000, 999999999999999);
        $expiry = date("Y-m-d", strtotime("+5 years"));
        $cvv = mt_rand(100, 999);
        $stmtCard = $pdo->prepare("INSERT INTO CARDS (CardNumber, CustomerID, ExpiryDate, CVV) VALUES (?, ?, ?, ?)");
        $stmtCard->execute([$cardNumber, $customerId, $expiry, $cvv]);
        
        // Create Digital Banking User
        $username = strtolower($firstName) . rand(100, 999);
        $stmtUser = $pdo->prepare("INSERT INTO DIGITALBANKINGUSER (CustomerID, Username, PasswordHash, CreatedAt, IsActive) VALUES (?, ?, ?, NOW(), 1)");
        $stmtUser->execute([$customerId, $username, $password]);
        
        $pdo->commit();
        
        $_SESSION['user_id'] = $customerId;
        $_SESSION['username'] = $firstName . ' ' . $lastName;
        $_SESSION['is_admin'] = 0;
        setToast("Account created successfully! Account Number: " . $accountNumber, "success");
        redirect('dashboard.php');
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Open Account - Asha Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh; padding: 2rem;">
    <div class="glass-card fade-in" style="max-width: 600px; width: 100%;">
        <div class="text-center">
            <i class="fas fa-user-plus" style="font-size: 3rem; color: var(--accent);"></i>
            <h2>Open Account</h2>
            <p class="text-muted">Join Asha Bank today</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger" style="background: rgba(192,57,43,0.1); padding: 0.75rem; border-radius: 12px; margin-bottom: 1rem;">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" data-validate="true">
            <div class="form-row">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" placeholder="First name" required>
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" placeholder="Last name" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" placeholder="you@example.com" required>
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" placeholder="+91XXXXXXXXXX" required>
                </div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="dob" required>
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" placeholder="Create a password" required>
                </div>
            </div>
            
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" rows="2" placeholder="Your full address"></textarea>
            </div>
            
            <button type="submit" class="btn btn-block">Create Account <i class="fas fa-check-circle"></i></button>
        </form>
        
        <div class="text-center mt-3">
            <p>Already have an account? <a href="login.php">Login</a></p>
        </div>
    </div>
    
    <div id="toastContainer"></div>
    <script src="assets/js/main.js"></script>
</body>
</html>