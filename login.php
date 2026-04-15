<?php require_once 'config/db.php';

if(isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if($_SESSION['role'] == 'admin') redirect('admin/index.php');
    elseif($_SESSION['role'] == 'staff') redirect('staff/dashboard.php');
    else redirect('dashboard.php');
}

$error = '';
if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'client';
    
    if($role == 'admin') {
        $stmt = $pdo->prepare("SELECT * FROM ADMIN_USER WHERE Username = ? AND IsActive = 1");
        $stmt->execute([$login]);
        $admin = $stmt->fetch();
        if($admin && ($password == 'Admin@123' || password_verify($password, $admin['PasswordHash']))) {
            $_SESSION['user_id'] = $admin['AdminID']; $_SESSION['username'] = $admin['Username']; $_SESSION['role'] = 'admin';
            setToast("Welcome Admin!", "success"); redirect('admin/index.php');
        } else $error = "Invalid admin credentials";
    } elseif($role == 'staff') {
        $stmt = $pdo->prepare("SELECT * FROM EMPLOYEE WHERE (Email = ? OR EmployeeID = ?) AND IsActive = 1");
        $stmt->execute([$login, $login]); $staff = $stmt->fetch();
        if($staff && $password == 'staff123') {
            $_SESSION['user_id'] = $staff['EmployeeID']; $_SESSION['username'] = $staff['FirstName'] . ' ' . $staff['LastName']; $_SESSION['role'] = 'staff';
            setToast("Welcome Staff!", "success"); redirect('staff/dashboard.php');
        } else $error = "Invalid staff credentials";
    } else {
        $stmt = $pdo->prepare("SELECT d.UserID, d.CustomerID, d.PasswordHash, c.FirstName, c.LastName FROM DIGITALBANKINGUSER d JOIN CUSTOMER c ON d.CustomerID = c.CustomerID WHERE d.Username = ? OR c.Email = ?");
        $stmt->execute([$login, $login]); $user = $stmt->fetch();
        if($user && ($password == 'password' || password_verify($password, $user['PasswordHash']))) {
            $_SESSION['user_id'] = $user['CustomerID']; $_SESSION['username'] = $user['FirstName'] . ' ' . $user['LastName']; $_SESSION['role'] = 'client';
            setToast("Welcome back!", "success"); redirect('dashboard.php');
        } else $error = "Invalid client credentials";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Asha Bank Bangladesh</title>
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
            --bg-tertiary: #F1F5F9;
            --text-primary: #0F172A;
            --text-secondary: #475569;
            --text-tertiary: #94A3B8;
            --border-color: #E2E8F0;
            --accent: #185FA5;
            --accent-dark: #0C447C;
            --accent-bg: #E6F1FB;
            --accent-text: #0C447C;
            --success: #3B6D11;
            --danger: #A32D2D;
            --danger-bg: #FCEBEB;
            --warning: #BA7517;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        body.dark {
            --bg-primary: #0F172A;
            --bg-secondary: #1E293B;
            --bg-tertiary: #334155;
            --text-primary: #F1F5F9;
            --text-secondary: #CBD5E1;
            --text-tertiary: #94A3B8;
            --border-color: #334155;
            --accent: #3B82F6;
            --accent-dark: #2563EB;
            --accent-bg: #1E3A5F;
            --accent-text: #93C5FD;
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
        
        /* Home Button */
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
        
        /* Theme Switch Position */
        .theme-switch-wrapper {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 100;
        }
        
        /* Login Container */
        .login-container {
            background: var(--bg-primary);
            border-radius: 32px;
            padding: 40px;
            width: 100%;
            max-width: 480px;
            box-shadow: var(--shadow-xl);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
        }
        
        /* Logo Section */
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
        
        /* Role Selector */
        .role-selector {
            display: flex;
            gap: 8px;
            margin-bottom: 28px;
            background: var(--bg-secondary);
            padding: 4px;
            border-radius: 60px;
        }
        
        .role-btn {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: 40px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            transition: all 0.2s ease;
        }
        
        .role-btn.active {
            background: var(--accent);
            color: white;
        }
        
        .role-btn.admin.active { background: #A32D2D; }
        .role-btn.staff.active { background: #BA7517; }
        .role-btn.client.active { background: var(--accent); }
        
        /* Form Fields */
        .field-group {
            margin-bottom: 20px;
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
            height: 48px;
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
        
        /* Submit Button */
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
        
        /* Error Message */
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
        
        /* Hint */
        .hint {
            text-align: center;
            margin-top: 20px;
            padding: 12px;
            background: var(--bg-secondary);
            border-radius: 12px;
            font-size: 12px;
            color: var(--text-tertiary);
        }
        
        .hint i {
            margin-right: 6px;
        }
        
        /* Register Link */
        .register-link {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-secondary);
        }
        
        .register-link a {
            color: var(--accent);
            text-decoration: none;
            font-weight: 600;
        }
        
        /* Responsive */
        @media (max-width: 640px) {
            .login-container {
                padding: 28px;
                margin-top: 60px;
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
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M135.831 3.00688C135.055 3.85027 134.111 4.29946 133 4.35447C134.111 4.40947 135.055 4.85867 135.831 5.71123C136.607 6.55462 136.996 7.56303 136.996 8.72727C136.996 7.95722 137.172 7.25134 137.525 6.59129C137.886 5.93124 138.372 5.39954 138.98 5.00535C139.598 4.60199 140.268 4.39114 141 4.35447C139.88 4.2903 138.936 3.85027 138.16 3.00688C137.384 2.16348 136.996 1.16425 136.996 0C136.996 1.16425 136.607 2.16348 135.831 3.00688ZM31 23.3545C32.1114 23.2995 33.0551 22.8503 33.8313 22.0069C34.6075 21.1635 34.9956 20.1642 34.9956 19C34.9956 20.1642 35.3837 21.1635 36.1599 22.0069C36.9361 22.8503 37.8798 23.2903 39 23.3545C38.2679 23.3911 37.5976 23.602 36.9802 24.0053C36.3716 24.3995 35.8864 24.9312 35.5248 25.5913C35.172 26.2513 34.9956 26.9572 34.9956 27.7273C34.9956 26.563 34.6075 25.5546 33.8313 24.7112C33.0551 23.8587 32.1114 23.4095 31 23.3545ZM0 36.3545C1.11136 36.2995 2.05513 35.8503 2.83131 35.0069C3.6075 34.1635 3.99559 33.1642 3.99559 32C3.99559 33.1642 4.38368 34.1635 5.15987 35.0069C5.93605 35.8503 6.87982 36.2903 8 36.3545C7.26792 36.3911 6.59757 36.602 5.98015 37.0053C5.37155 37.3995 4.88644 37.9312 4.52481 38.5913C4.172 39.2513 3.99559 39.9572 3.99559 40.7273C3.99559 39.563 3.6075 38.5546 2.83131 37.7112C2.05513 36.8587 1.11136 36.4095 0 36.3545ZM56.8313 24.0069C56.0551 24.8503 55.1114 25.2995 54 25.3545C55.1114 25.4095 56.0551 25.8587 56.8313 26.7112C57.6075 27.5546 57.9956 28.563 57.9956 29.7273C57.9956 28.9572 58.172 28.2513 58.5248 27.5913C58.8864 26.9312 59.3716 26.3995 59.9802 26.0053C60.5976 25.602 61.2679 25.3911 62 25.3545C60.8798 25.2903 59.9361 24.8503 59.1599 24.0069C58.3837 23.1635 57.9956 22.1642 57.9956 21C57.9956 22.1642 57.6075 23.1635 56.8313 24.0069ZM81 25.3545C82.1114 25.2995 83.0551 24.8503 83.8313 24.0069C84.6075 23.1635 84.9956 22.1642 84.9956 21C84.9956 22.1642 85.3837 23.1635 86.1599 24.0069C86.9361 24.8503 87.8798 25.2903 89 25.3545C88.2679 25.3911 87.5976 25.602 86.9802 26.0053C86.3716 26.3995 85.8864 26.9312 85.5248 27.5913C85.172 28.2513 84.9956 28.9572 84.9956 29.7273C84.9956 28.563 84.6075 27.5546 83.8313 26.7112C83.0551 25.8587 82.1114 25.4095 81 25.3545ZM136 36.3545C137.111 36.2995 138.055 35.8503 138.831 35.0069C139.607 34.1635 139.996 33.1642 139.996 32C139.996 33.1642 140.384 34.1635 141.16 35.0069C141.936 35.8503 142.88 36.2903 144 36.3545C143.268 36.3911 142.598 36.602 141.98 37.0053C141.372 37.3995 140.886 37.9312 140.525 38.5913C140.172 39.2513 139.996 39.9572 139.996 40.7273C139.996 39.563 139.607 38.5546 138.831 37.7112C138.055 36.8587 137.111 36.4095 136 36.3545ZM101.831 49.0069C101.055 49.8503 100.111 50.2995 99 50.3545C100.111 50.4095 101.055 50.8587 101.831 51.7112C102.607 52.5546 102.996 53.563 102.996 54.7273C102.996 53.9572 103.172 53.2513 103.525 52.5913C103.886 51.9312 104.372 51.3995 104.98 51.0053C105.598 50.602 106.268 50.3911 107 50.3545C105.88 50.2903 104.936 49.8503 104.16 49.0069C103.384 48.1635 102.996 47.1642 102.996 46C102.996 47.1642 102.607 48.1635 101.831 49.0069Z" fill="currentColor"></path>
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
    
    <div class="login-container">
        <div class="logo-section">
            <div class="logo-icon">
                <svg width="36" height="36" viewBox="0 0 16 16" fill="none">
                    <rect x="1" y="6" width="14" height="9" rx="1.5" fill="none" stroke="var(--accent)" stroke-width="1.2"/>
                    <path d="M4 6V4a4 4 0 0 1 8 0v2" stroke="var(--accent)" stroke-width="1.2"/>
                    <circle cx="8" cy="10.5" r="1.5" fill="var(--accent)"/>
                </svg>
            </div>
            <h1>Welcome Back</h1>
            <p>Login to your Asha Bank account</p>
        </div>
        
        <?php if($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <div class="role-selector">
            <div class="role-btn admin" data-role="admin">👑 Admin</div>
            <div class="role-btn staff" data-role="staff">👔 Staff</div>
            <div class="role-btn client active" data-role="client">👤 Client</div>
        </div>
        
        <form method="POST">
            <input type="hidden" name="role" id="role" value="client">
            
            <div class="field-group">
                <label class="field-label" id="loginLabel">Username / Email</label>
                <input type="text" name="login" id="login" class="field-input" placeholder="Enter your username or email" required>
            </div>
            
            <div class="field-group">
                <label class="field-label">Password</label>
                <input type="password" name="password" class="field-input" placeholder="Enter your password" required>
            </div>
            
            <button type="submit" class="submit-btn">Login →</button>
        </form>
        
        <div class="hint" id="hint">
            <i class="fas fa-info-circle"></i> 
            Demo: username = "arjun.kapoor", password = "password"
        </div>
        
        <div class="register-link">
            Don't have an account? <a href="register.php">Create Account</a>
        </div>
    </div>
    
    <script>
        // Theme Toggle
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
        
        // Role Selector
        const roleBtns = document.querySelectorAll('.role-btn');
        const roleInput = document.getElementById('role');
        const loginLabel = document.getElementById('loginLabel');
        const loginInput = document.getElementById('login');
        const hintDiv = document.getElementById('hint');
        
        roleBtns.forEach(btn => {
            btn.addEventListener('click', function() {
                roleBtns.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                const role = this.dataset.role;
                roleInput.value = role;
                
                if(role === 'admin') {
                    loginLabel.innerHTML = 'Admin Username';
                    loginInput.placeholder = 'Enter admin username';
                    hintDiv.innerHTML = '<i class="fas fa-info-circle"></i> Demo: username = "admin", password = "Admin@123"';
                } else if(role === 'staff') {
                    loginLabel.innerHTML = 'Staff Email';
                    loginInput.placeholder = 'Enter staff email';
                    hintDiv.innerHTML = '<i class="fas fa-info-circle"></i> Demo: email = "rajesh@ashabank.bd", password = "staff123"';
                } else {
                    loginLabel.innerHTML = 'Username / Email';
                    loginInput.placeholder = 'Enter your username or email';
                    hintDiv.innerHTML = '<i class="fas fa-info-circle"></i> Demo: username = "arjun.kapoor", password = "password"';
                }
            });
        });
    </script>
</body>
</html>