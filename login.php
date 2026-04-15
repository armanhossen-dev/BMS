<?php require_once 'config/db.php'; 
if(isLoggedIn()) redirect('dashboard.php');

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = trim($_POST['login']);
    $password = $_POST['password'];
    
    $stmt = $pdo->prepare("SELECT d.UserID, d.CustomerID, d.PasswordHash, c.FirstName, c.LastName 
                           FROM DIGITALBANKINGUSER d 
                           JOIN CUSTOMER c ON d.CustomerID = c.CustomerID 
                           WHERE d.Username = ? OR c.Email = ?");
    $stmt->execute([$login, $login]);
    $user = $stmt->fetch();
    
    if($user && password_verify($password, $user['PasswordHash'])) {
        $_SESSION['user_id'] = $user['CustomerID'];
        $_SESSION['username'] = $user['FirstName'] . ' ' . $user['LastName'];
        $_SESSION['is_admin'] = 0;
        setToast("Welcome back, " . $user['FirstName'] . "!", "success");
        redirect('dashboard.php');
    } else {
        $error = "Invalid username/email or password";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Asha Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh;">
    <div class="glass-card fade-in" style="max-width: 450px; width: 100%;">
        <div class="text-center">
            <i class="fas fa-university" style="font-size: 3rem; color: var(--accent);"></i>
            <h2>Welcome Back</h2>
            <p class="text-muted">Login to your Asha Bank account</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger" style="background: rgba(192,57,43,0.1); padding: 0.75rem; border-radius: 12px; margin-bottom: 1rem; color: var(--danger);">
                <i class="fas fa-exclamation-circle"></i> <?= $error ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" data-validate="true">
            <div class="form-group">
                <label>Username or Email</label>
                <input type="text" name="login" placeholder="Enter your username or email" required>
            </div>
            <div class="form-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            <button type="submit" class="btn btn-block">Login <i class="fas fa-arrow-right"></i></button>
        </form>
        
        <div class="text-center mt-3">
            <p>Don't have an account? <a href="register.php">Create Account</a></p>
        </div>
    </div>
    
    <div id="toastContainer"></div>
    <script src="assets/js/main.js"></script>
</body>
</html>