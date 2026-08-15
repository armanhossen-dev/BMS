<?php
require_once 'config/db.php';

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
            $_SESSION['user_id'] = $admin['AdminID']; 
            $_SESSION['username'] = $admin['Username']; 
            $_SESSION['role'] = 'admin';
            setToast("Welcome Admin!", "success"); 
            redirect('admin/index.php');
        } else $error = "Invalid admin credentials";
    } elseif($role == 'staff') {
    // Staff login from staff table (not EMPLOYEE)
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE (email = ? OR username = ?) AND is_active = 1");
    $stmt->execute([$login, $login]); 
    $staff = $stmt->fetch();
    
    // Verify password using password_verify
    if($staff && password_verify($password, $staff['password_hash'])) {
        $_SESSION['user_id'] = $staff['staff_id']; 
        $_SESSION['username'] = $staff['first_name'] . ' ' . $staff['last_name']; 
        $_SESSION['role'] = 'staff';
        $_SESSION['staff_username'] = $staff['username'];
        setToast("Welcome Staff!", "success"); 
        redirect('staff/dashboard.php');
    } else {
        $error = "Invalid staff credentials. Use username/email and password.";
    }
    }  else {
        $stmt = $pdo->prepare("SELECT d.UserID, d.CustomerID, d.PasswordHash, c.FirstName, c.LastName FROM DIGITALBANKINGUSER d JOIN CUSTOMER c ON d.CustomerID = c.CustomerID WHERE d.Username = ? OR c.Email = ?");
        $stmt->execute([$login, $login]); 
        $user = $stmt->fetch();
        if($user && ($password == 'password' || password_verify($password, $user['PasswordHash']))) {
            $_SESSION['user_id'] = $user['CustomerID']; 
            $_SESSION['username'] = $user['FirstName'] . ' ' . $user['LastName']; 
            $_SESSION['role'] = 'client';
            setToast("Welcome back!", "success"); 
            redirect('dashboard.php');
        } else $error = "Invalid credentials. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — Asha Bank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
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
            --success: #3B6D11;
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
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
            font-family: 'Inter', sans-serif;
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

        .home-btn {
            position: fixed;
            top: 24px;
            left: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border-radius: 40px;
            padding: 10px 20px;
            text-decoration: none;
            color: white;
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
            z-index: 100;
            border: 1px solid rgba(255, 255, 255, 0.3);
        }

        .home-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        body.dark .home-btn {
            background: rgba(0, 0, 0, 0.4);
        }

        /* Theme Toggle Button - Fixed Visibility */
        .theme-toggle-btn {
            position: fixed;
            top: 24px;
            right: 24px;
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 40px;
            padding: 10px 16px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 13px;
            font-weight: 500;
            color: white;
            transition: all 0.3s ease;
            z-index: 100;
        }

        body.dark .theme-toggle-btn {
            background: rgba(0, 0, 0, 0.4);
            color: white;
        }

        .theme-toggle-btn:hover {
            transform: translateY(-2px);
        }

        .login-container {
            display: flex;
            max-width: 1000px;
            width: 100%;
            background: var(--bg-primary);
            border-radius: 32px;
            overflow: hidden;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .brand-panel {
            flex: 1;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            padding: 48px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            position: relative;
            overflow: hidden;
        }

        .brand-panel::before {
            content: '';
            position: absolute;
            top: -50px;
            right: -50px;
            width: 200px;
            height: 200px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 50%;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            position: relative;
            z-index: 1;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: #3b82f6;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-text {
            font-size: 20px;
            font-weight: 700;
            color: white;
        }

        .brand-content {
            position: relative;
            z-index: 1;
        }

        .brand-content h2 {
            font-size: 32px;
            font-weight: 700;
            color: white;
            margin-bottom: 16px;
            line-height: 1.2;
        }

        .brand-content h2 span {
            color: #3b82f6;
        }

        .brand-content p {
            color: rgba(255, 255, 255, 0.6);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 32px;
        }

        .features-list {
            display: flex;
            flex-direction: column;
            gap: 16px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
        }

        .feature-dot {
            width: 6px;
            height: 6px;
            background: #3b82f6;
            border-radius: 50%;
        }

        .footer-note {
            position: relative;
            z-index: 1;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.4);
        }

        .form-panel {
            flex: 1;
            padding: 48px;
            background: var(--bg-primary);
        }

        .form-header {
            margin-bottom: 32px;
        }

        .form-header h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .form-header p {
            font-size: 14px;
            color: var(--text-tertiary);
        }

        .role-tabs {
            display: flex;
            gap: 8px;
            background: var(--bg-secondary);
            border-radius: 60px;
            padding: 4px;
            margin-bottom: 28px;
        }

        .role-tab {
            flex: 1;
            padding: 10px;
            border-radius: 40px;
            border: none;
            background: transparent;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-secondary);
            cursor: pointer;
            transition: all 0.2s;
            text-align: center;
        }

        .role-tab.active {
            background: var(--bg-primary);
            color: var(--text-primary);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .role-tab[data-role="admin"].active { color: #dc2626; }
        .role-tab[data-role="staff"].active { color: #d97706; }
        .role-tab[data-role="client"].active { color: #3b82f6; }

        .input-group {
            margin-bottom: 20px;
        }

        .input-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group input {
            width: 100%;
            height: 48px;
            border-radius: 12px;
            border: 1.5px solid var(--border-color);
            background: var(--bg-secondary);
            padding: 0 16px;
            font-size: 14px;
            transition: all 0.2s;
            outline: none;
            color: var(--text-primary);
        }

        .input-group input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .submit-btn {
            width: 100%;
            height: 48px;
            background: #0f172a;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
            margin-top: 8px;
        }

        .submit-btn:hover {
            background: #1e293b;
            transform: translateY(-2px);
        }

        body.dark .submit-btn {
            background: var(--accent);
        }

        body.dark .submit-btn:hover {
            background: var(--accent-dark);
        }

        .error-box {
            background: var(--danger-bg);
            border: 1px solid #fecaca;
            color: var(--danger);
            padding: 12px 16px;
            border-radius: 12px;
            font-size: 13px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .hint-box {
            background: var(--accent-bg);
            border-radius: 12px;
            padding: 14px 16px;
            font-size: 12px;
            color: var(--accent-dark);
            margin-top: 20px;
            line-height: 1.5;
        }

        body.dark .hint-box {
            color: var(--accent-text);
        }

        .form-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 13px;
            color: var(--text-tertiary);
        }

        .form-footer a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                max-width: 450px;
            }
            .brand-panel {
                padding: 32px;
                text-align: center;
            }
            .brand-content h2 {
                font-size: 24px;
            }
            .features-list {
                display: none;
            }
            .form-panel {
                padding: 32px;
            }
            .home-btn, .theme-toggle-btn {
                top: 16px;
                padding: 8px 16px;
                font-size: 12px;
            }
            .home-btn { left: 16px; }
            .theme-toggle-btn { right: 16px; }
        }
        /* Toast Notification - Top Right Position */
        .toast-notification {
            position: fixed;
            top: 80px;
            right: 20px;
            background: white;
            border-left: 4px solid #3b6d11;
            padding: 14px 20px;
            border-radius: 12px;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInRight 0.3s ease;
            font-size: 14px;
            min-width: 280px;
            max-width: 400px;
        }

        .toast-notification.success { border-left-color: #3b6d11; }
        .toast-notification.error { border-left-color: #a32d2d; }
        .toast-notification.warning { border-left-color: #ba7517; }

        .toast-notification i { font-size: 18px; }
        .toast-notification.success i { color: #3b6d11; }
        .toast-notification.error i { color: #a32d2d; }
        .toast-notification.warning i { color: #ba7517; }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    </style>
</head>
<body>

<a href="index.php" class="home-btn">
    <i class="fas fa-home"></i> Back to Home
</a>

<button class="theme-toggle-btn" id="themeToggleBtn">
    <i class="fas fa-moon"></i> <span id="themeText">Dark</span>
</button>

<div class="login-container">
    <div class="brand-panel">
        <div class="logo">
            <div class="logo-icon">
                <svg viewBox="0 0 18 18" fill="none">
                    <rect x="1" y="7" width="16" height="10" rx="2" stroke="white" stroke-width="1.4"/>
                    <path d="M5 7V5a4 4 0 0 1 8 0v2" stroke="white" stroke-width="1.4"/>
                    <circle cx="9" cy="12" r="1.5" fill="white"/>
                </svg>
            </div>
            <span class="logo-text">Asha Bank</span>
        </div>
        
        <div class="brand-content">
            <h2>Welcome <span>back</span></h2>
            <p>Access your accounts, track transactions, and manage your finances securely.</p>
            
            <div class="features-list">
                <div class="feature-item"><div class="feature-dot"></div>256-bit SSL encryption</div>
                <div class="feature-item"><div class="feature-dot"></div>Multi-factor authentication</div>
                <div class="feature-item"><div class="feature-dot"></div>Real-time fraud monitoring</div>
            </div>
        </div>
        
        <div class="footer-note">Secured by Asha Bank © 2024</div>
    </div>
    
    <div class="form-panel">
        <div class="form-header">
            <h1>Sign in</h1>
            <p>Enter your credentials to access your account</p>
        </div>
        
        <?php if($error): ?>
        <div class="error-box">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <div class="role-tabs">
            <button class="role-tab" data-role="admin">👑 Admin</button>
            <button class="role-tab" data-role="staff">👔 Staff</button>
            <button class="role-tab active" data-role="client">👤 Client</button>
        </div>
        
        <form method="POST">
            <input type="hidden" name="role" id="roleInput" value="client">
            
            <div class="input-group">
                <label id="loginLabel">Username or Email</label>
                <input type="text" name="login" id="loginInput" placeholder="Enter your username or email" required>
            </div>
            
            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" placeholder="Enter your password" required>
            </div>
            
            <button type="submit" class="submit-btn">Sign In →</button>
        </form>
        
        <div class="hint-box" id="hintBox">
            <i class="fas fa-info-circle"></i>
            <strong>Demo credentials:</strong> username = "arjun.kapoor" / password = "password"
        </div>
        
        <div class="form-footer">
            No account yet? <a href="register.php">Open one free →</a>
        </div>
    </div>
</div>

<script>
    // Theme Toggle for Login Page
    const themeToggleBtn = document.getElementById('themeToggleBtn');
    const themeText = document.getElementById('themeText');
    const themeIcon = themeToggleBtn.querySelector('i');
    
    if(localStorage.getItem('theme') === 'dark') {
        document.body.classList.add('dark');
        themeIcon.className = 'fas fa-sun';
        themeText.textContent = 'Light';
    }
    
    themeToggleBtn.addEventListener('click', () => {
        if(document.body.classList.contains('dark')) {
            document.body.classList.remove('dark');
            localStorage.setItem('theme', 'light');
            themeIcon.className = 'fas fa-moon';
            themeText.textContent = 'Dark';
        } else {
            document.body.classList.add('dark');
            localStorage.setItem('theme', 'dark');
            themeIcon.className = 'fas fa-sun';
            themeText.textContent = 'Light';
        }
    });
    
    // Role tabs
    const tabs = document.querySelectorAll('.role-tab');
    const roleInput = document.getElementById('roleInput');
    const loginLabel = document.getElementById('loginLabel');
    const loginInput = document.getElementById('loginInput');
    const hintBox = document.getElementById('hintBox');
    
    const hints = {
        admin: { label: 'Admin Username', placeholder: 'Enter admin username', hint: '<i class="fas fa-info-circle"></i> <strong>Demo:</strong> username = "admin" / password = "Admin@123"' },
        staff: { label: 'Staff Email', placeholder: 'Enter staff email', hint: '<i class="fas fa-info-circle"></i> <strong>Demo:</strong> email = "rajesh@ashabank.bd" / password = "staff123"' },
        client: { label: 'Username or Email', placeholder: 'Enter your username or email', hint: '<i class="fas fa-info-circle"></i> <strong>Demo:</strong> username = "arjun.kapoor" / password = "password"' }
    };
    
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            const role = tab.dataset.role;
            roleInput.value = role;
            const h = hints[role];
            loginLabel.textContent = h.label;
            loginInput.placeholder = h.placeholder;
            hintBox.innerHTML = h.hint;
        });
    });
</script>
<!-- Toast Container -->
<div id="toastContainer" style="position: fixed; top: 80px; right: 20px; z-index: 1000;">
    <?php $toast = getToast(); if($toast): ?>
        <div class="toast-notification <?= $toast['type'] ?>">
            <i class="fas <?= $toast['type'] == 'success' ? 'fa-check-circle' : 'fa-info-circle' ?>"></i>
            <span><?= htmlspecialchars($toast['message']) ?></span>
        </div>
        <script>
            setTimeout(() => {
                const toast = document.querySelector('.toast-notification');
                if(toast) {
                    toast.style.animation = 'slideOutRight 0.3s ease';
                    setTimeout(() => toast.remove(), 300);
                }
            }, 3000);
        </script>
    <?php endif; ?>
</div>

</body>
</html>