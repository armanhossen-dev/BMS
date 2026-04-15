<?php require_once 'config/db.php';
if(isLoggedIn()) redirect('dashboard.php');

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstName = trim($_POST['first_name']); 
    $lastName = trim($_POST['last_name']); 
    $email = trim($_POST['email']); 
    $phone = trim($_POST['phone']); 
    $password = $_POST['password']; 
    $address = trim($_POST['address']); 
    $dob = $_POST['dob'];
    
    if(empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($password)) {
        $error = "Please fill all required fields";
    } else {
        try { 
            $pdo->beginTransaction(); 
            $check = $pdo->prepare("SELECT CustomerID FROM CUSTOMER WHERE Email = ?"); 
            $check->execute([$email]); 
            if($check->fetch()) throw new Exception("Email already registered");
            
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            $stmt = $pdo->prepare("INSERT INTO CUSTOMER (FirstName, LastName, Email, Phone, Address, DateOfBirth, CustomerCategoryID, PrimaryBranchID, CreatedAt, IsActive) VALUES (?,?,?,?,?,?,1,1,NOW(),1)");
            $stmt->execute([$firstName, $lastName, $email, $phone, $address, $dob]); 
            $customerId = $pdo->lastInsertId();
            
            $accountNumber = generateAccountNumber($pdo);
            
            $stmtAcc = $pdo->prepare("INSERT INTO ACCOUNT (AccountNumber, ProductID, CustomerID, BranchID, OpeningDate, AvailableBalance, AccountStatus) VALUES (?,1,?,1,CURDATE(),0,'Active')");
            $stmtAcc->execute([$accountNumber, $customerId]);
            
            $pdo->exec("CREATE TABLE IF NOT EXISTS CARDS (CardID INT AUTO_INCREMENT PRIMARY KEY, CardNumber VARCHAR(20) NOT NULL UNIQUE, CustomerID INT NOT NULL, ExpiryDate DATE NOT NULL, CVV VARCHAR(4) NOT NULL, FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID))");
            
            $cardNumber = "4" . mt_rand(100000000000000, 999999999999999); 
            $expiry = date("Y-m-d", strtotime("+5 years")); 
            $cvv = mt_rand(100, 999);
            $stmtCard = $pdo->prepare("INSERT INTO CARDS (CardNumber, CustomerID, ExpiryDate, CVV) VALUES (?,?,?,?)"); 
            $stmtCard->execute([$cardNumber, $customerId, $expiry, $cvv]);
            
            $username = strtolower($firstName) . rand(100, 999);
            $stmtUser = $pdo->prepare("INSERT INTO DIGITALBANKINGUSER (CustomerID, Username, PasswordHash, CreatedAt, IsActive) VALUES (?,?,?,NOW(),1)");
            $stmtUser->execute([$customerId, $username, $hashedPassword]);
            
            $pdo->commit();
            
            $_SESSION['user_id'] = $customerId; 
            $_SESSION['username'] = $firstName . ' ' . $lastName; 
            $_SESSION['role'] = 'client';
            setToast("Account created! Account No: $accountNumber", "success"); 
            redirect('dashboard.php');
        } catch(Exception $e) { 
            if(isset($pdo)) $pdo->rollBack(); 
            $error = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Asha Bank Bangladesh</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700;14..32,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link rel="stylesheet" href="assets/css/theme-switch.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        :root {
            --font-sans: 'Inter', sans-serif;
            --bg-primary: #FFFFFF;
            --bg-secondary: #F8FAFC;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-tertiary: #94A3B8;
            --border-color: #E2E8F0;
            --accent: #185FA5;
            --accent-dark: #0C447C;
            --accent-bg: #E6F1FB;
            --danger: #A32D2D;
            --danger-bg: #FCEBEB;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        body.dark {
            --bg-primary: #0F172A;
            --bg-secondary: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #CBD5E1;
            --text-tertiary: #94A3B8;
            --border-color: #334155;
            --accent: #3B82F6;
            --accent-dark: #2563EB;
            --accent-bg: #1E3A5F;
        }
        
        body {
            font-family: var(--font-sans);
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
        }
        
        body.dark {
            background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        }
        
        .home-button {
            position: fixed;
            top: 24px;
            left: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            text-decoration: none;
            color: white;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            z-index: 100;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        .home-button:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }
        
        body.dark .home-button {
            background: rgba(0, 0, 0, 0.4);
        }
        
        .theme-switch-wrapper {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 100;
        }
        
        .register-container {
            background: var(--bg-primary);
            border-radius: 32px;
            padding: 40px;
            width: 100%;
            max-width: 520px;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        .logo-section {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .logo-icon {
            width: 64px;
            height: 64px;
            background: var(--accent-bg);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 16px;
        }
        
        .logo-icon svg {
            width: 36px;
            height: 36px;
        }
        
        .logo-section h1 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }
        
        .logo-section p {
            font-size: 13px;
            color: var(--text-tertiary);
        }
        
        .field-group {
            margin-bottom: 16px;
        }
        
        .field-label {
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            display: block;
        }
        
        .field-input {
            width: 100%;
            height: 44px;
            border-radius: 12px;
            border: 1.5px solid var(--border-color);
            background: var(--bg-secondary);
            padding: 0 16px;
            font-size: 14px;
            color: var(--text-primary);
            outline: none;
            transition: all 0.2s ease;
        }
        
        .field-input:focus {
            border-color: var(--accent);
            box-shadow: 0 0 0 3px var(--accent-bg);
        }
        
        .field-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }
        
        .submit-btn {
            width: 100%;
            height: 48px;
            border-radius: 12px;
            background: var(--accent);
            color: white;
            border: none;
            font-weight: 600;
            font-size: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
        }
        
        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .error-message {
            background: var(--danger-bg);
            color: var(--danger);
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .login-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        
        @media (max-width: 640px) {
            .register-container {
                padding: 28px;
                margin-top: 60px;
            }
            
            .field-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .home-button {
                top: 16px;
                left: 16px;
                padding: 8px 16px;
                font-size: 12px;
            }
            
            .theme-switch-wrapper {
                top: 16px;
                right: 16px;
            }
        }
    </style>
</head>
<body>
    <a href="index.php" class="home-button">
        <i class="fas fa-home"></i> Home
    </a>
    
    <div class="theme-switch-wrapper">
        <label class="theme-switch">
            <input type="checkbox" class="theme-switch__checkbox" id="themeCheckbox">
            <div class="theme-switch__container">
                <div class="theme-switch__clouds"></div>
                <div class="theme-switch__stars-container">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 144 55" fill="none">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M135.831 3.00688C135.055 3.85027 134.111 4.29946 133 4.35447C134.111 4.40947 135.055 4.85867 135.831 5.71123C136.607 6.55462 136.996 7.56303 136.996 8.72727C136.996 7.95722 137.172 7.25134 137.525 6.59129C137.886 5.93124 138.372 5.39954 138.98 5.00535C139.598 4.60199 140.268 4.39114 141 4.35447C139.88 4.2903 138.936 3.85027 138.16 3.00688C137.384 2.16348 136.996 1.16425 136.996 0C136.996 1.16425 136.607 2.16348 135.831 3.00688Z" fill="currentColor"></path>
                    </svg>
                </div>
                <div class="theme-switch__circle-container">
                    <div class="theme-switch__sun-moon-container">
                        <div class="theme-switch__moon">
                            <div class="theme-switch__spot"></div>
                            <div class="theme-switch__spot"></div>
                            <div class="theme-switch__spot"></div>
                        </div>
                    </div>
                </div>
            </div>
        </label>
    </div>
    
    <div class="register-container">
        <div class="logo-section">
            <div class="logo-icon">
                <svg width="36" height="36" viewBox="0 0 16 16" fill="none">
                    <rect x="1" y="6" width="14" height="9" rx="1.5" fill="none" stroke="var(--accent)" stroke-width="1.2"/>
                    <path d="M4 6V4a4 4 0 0 1 8 0v2" stroke="var(--accent)" stroke-width="1.2"/>
                    <circle cx="8" cy="10.5" r="1.5" fill="var(--accent)"/>
                </svg>
            </div>
            <h1>Open Account</h1>
            <p>Join Asha Bank today</p>
        </div>
        
        <?php if($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="field-row">
                <div class="field-group">
                    <label class="field-label">First Name *</label>
                    <input type="text" name="first_name" class="field-input" placeholder="First name" required>
                </div>
                <div class="field-group">
                    <label class="field-label">Last Name *</label>
                    <input type="text" name="last_name" class="field-input" placeholder="Last name" required>
                </div>
            </div>
            
            <div class="field-group">
                <label class="field-label">Email Address *</label>
                <input type="email" name="email" class="field-input" placeholder="you@example.com" required>
            </div>
            
            <div class="field-group">
                <label class="field-label">Phone Number *</label>
                <input type="tel" name="phone" class="field-input" placeholder="01XXXXXXXXX" required>
            </div>
            
            <div class="field-row">
                <div class="field-group">
                    <label class="field-label">Date of Birth</label>
                    <input type="date" name="dob" class="field-input">
                </div>
                <div class="field-group">
                    <label class="field-label">Password *</label>
                    <input type="password" name="password" class="field-input" placeholder="Create password (min 4 chars)" required>
                </div>
            </div>
            
            <div class="field-group">
                <label class="field-label">Address</label>
                <input type="text" name="address" class="field-input" placeholder="Your full address">
            </div>
            
            <button type="submit" class="submit-btn">Create Account →</button>
        </form>
        
        <div class="login-link">
            Already have an account? <a href="login.php">Login</a>
        </div>
    </div>
    
    <script>
        const themeCheckbox = document.getElementById('themeCheckbox');
        const savedTheme = localStorage.getItem('theme');
        
        if (savedTheme === 'dark') {
            document.body.classList.add('dark');
            themeCheckbox.checked = true;
        }
        
        themeCheckbox.addEventListener('change', function() {
            if (this.checked) {
                document.body.classList.add('dark');
                localStorage.setItem('theme', 'dark');
            } else {
                document.body.classList.remove('dark');
                localStorage.setItem('theme', 'light');
            }
        });
    </script>
</body>
</html>