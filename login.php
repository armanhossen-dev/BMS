<?php
require_once 'config/db.php';

// If already logged in, redirect based on role
if(isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if($_SESSION['role'] == 'admin') {
        redirect('admin/index.php');
    } elseif($_SESSION['role'] == 'staff') {
        redirect('staff/dashboard.php');
    } else {
        redirect('dashboard.php');
    }
}

$error = '';

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $login = trim($_POST['login'] ?? '');
    $password = $_POST['password'] ?? '';
    $role = $_POST['role'] ?? 'client';
    
    echo "<!-- Debug: Role = $role, Login = $login -->";
    
    if(empty($login) || empty($password)) {
        $error = "Please enter username/email and password";
    } else {
        if($role == 'admin') {
            // Admin login from ADMIN_USER table
            $stmt = $pdo->prepare("SELECT * FROM ADMIN_USER WHERE Username = ? AND IsActive = 1");
            $stmt->execute([$login]);
            $admin = $stmt->fetch();
            
            // Check password (Admin@123)
            if($admin && ($password == 'Admin@123' || password_verify($password, $admin['PasswordHash']))) {
                $_SESSION['user_id'] = $admin['AdminID'];
                $_SESSION['username'] = $admin['Username'];
                $_SESSION['role'] = 'admin';
                $_SESSION['is_admin'] = 1;
                setToast("Welcome Admin!", "success");
                redirect('admin/index.php');
            } else {
                $error = "Invalid admin credentials. Use username: admin, password: Admin@123";
            }
            
        } elseif($role == 'staff') {
            // Staff login from EMPLOYEE table
            $stmt = $pdo->prepare("SELECT * FROM EMPLOYEE WHERE (Email = ? OR EmployeeID = ?) AND IsActive = 1");
            $stmt->execute([$login, $login]);
            $staff = $stmt->fetch();
            
            // For staff, password is 'staff123'
            if($staff && $password == 'staff123') {
                $_SESSION['user_id'] = $staff['EmployeeID'];
                $_SESSION['username'] = $staff['FirstName'] . ' ' . $staff['LastName'];
                $_SESSION['role'] = 'staff';
                $_SESSION['is_admin'] = 0;
                setToast("Welcome Staff!", "success");
                redirect('staff/dashboard.php');
            } else {
                $error = "Invalid staff credentials. Use email: rajesh@ashabank.in, password: staff123";
            }
            
        } else {
            // Client login from DIGITALBANKINGUSER
            $stmt = $pdo->prepare("SELECT d.UserID, d.CustomerID, d.PasswordHash, c.FirstName, c.LastName, c.Email 
                                   FROM DIGITALBANKINGUSER d 
                                   JOIN CUSTOMER c ON d.CustomerID = c.CustomerID 
                                   WHERE d.Username = ? OR c.Email = ?");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();
            
            // For clients, password is 'password'
            if($user && ($password == 'password' || password_verify($password, $user['PasswordHash']))) {
                $_SESSION['user_id'] = $user['CustomerID'];
                $_SESSION['username'] = $user['FirstName'] . ' ' . $user['LastName'];
                $_SESSION['role'] = 'client';
                $_SESSION['is_admin'] = 0;
                setToast("Welcome back, " . $user['FirstName'] . "!", "success");
                redirect('dashboard.php');
            } else {
                $error = "Invalid client credentials. Use username: arjun.kapoor, password: password";
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
    <title>Login - Asha Bank Bangladesh</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        
        :root {
            --bg-gradient-start: #eef2f5;
            --bg-gradient-end: #d9e2ec;
            --glass-bg: rgba(255, 255, 255, 0.45);
            --glass-border: rgba(255, 255, 255, 0.6);
            --text-primary: #1a2c3e;
            --text-muted: #5a6e7c;
            --accent: #0f4c5c;
            --danger: #c0392b;
            --success: #27ae60;
        }
        
        body.dark {
            --bg-gradient-start: #0f172a;
            --bg-gradient-end: #1e293b;
            --glass-bg: rgba(15, 23, 42, 0.6);
            --text-primary: #e2e8f0;
            --accent: #2c7da0;
        }
        
        body {
            background: linear-gradient(135deg, var(--bg-gradient-start), var(--bg-gradient-end));
            color: var(--text-primary);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', sans-serif;
        }
        
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: 28px;
            border: 1px solid var(--glass-border);
            padding: 2rem;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
        }
        
        .text-center {
            text-align: center;
        }
        
        .btn {
            background: var(--accent);
            border: none;
            border-radius: 40px;
            padding: 0.75rem 1.5rem;
            color: white;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
        }
        
        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
        }
        
        .form-group {
            margin-bottom: 1.2rem;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        input, select {
            width: 100%;
            padding: 0.75rem 1rem;
            border-radius: 40px;
            border: 1px solid rgba(0,0,0,0.1);
            background: rgba(255,255,255,0.8);
            font-size: 1rem;
        }
        
        body.dark input, body.dark select {
            background: rgba(30,41,59,0.8);
            color: white;
            border-color: rgba(255,255,255,0.2);
        }
        
        .role-selector {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
        }
        
        .role-option {
            flex: 1;
            text-align: center;
            padding: 0.6rem;
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
            background: rgba(255,255,255,0.3);
        }
        
        .role-option.active {
            background: var(--accent);
            color: white;
        }
        
        .role-option.admin.active { background: #c0392b; }
        .role-option.staff.active { background: #e67e22; }
        
        .error-message {
            background: rgba(192,57,43,0.1);
            padding: 0.75rem;
            border-radius: 12px;
            margin-bottom: 1rem;
            color: var(--danger);
            border-left: 3px solid var(--danger);
        }
        
        .demo-hint {
            font-size: 0.75rem;
            margin-top: 0.5rem;
            color: var(--text-muted);
            background: rgba(0,0,0,0.05);
            padding: 0.5rem;
            border-radius: 8px;
        }
        
        .mt-3 {
            margin-top: 1rem;
        }
        
        a {
            color: var(--accent);
            text-decoration: none;
        }
        
        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--glass-bg);
            border: 1px solid var(--glass-border);
            border-radius: 40px;
            padding: 0.5rem 1rem;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <button class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i> Dark/Light</button>
    
    <div class="glass-card fade-in">
        <div class="text-center">
            <i class="fas fa-university" style="font-size: 3rem; color: var(--accent);"></i>
            <h2>আশা ব্যাংক</h2>
            <p style="color: var(--text-muted);">Login to your account</p>
        </div>
        
        <?php if($error): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Role Selector -->
        <div class="role-selector">
            <div class="role-option admin" data-role="admin">
                <i class="fas fa-user-shield"></i> Admin
            </div>
            <div class="role-option staff" data-role="staff">
                <i class="fas fa-user-tie"></i> Staff
            </div>
            <div class="role-option client active" data-role="client">
                <i class="fas fa-user"></i> Client
            </div>
        </div>
        
        <form method="POST" id="loginForm">
            <input type="hidden" name="role" id="role" value="client">
            
            <div class="form-group">
                <label for="login" id="loginLabel"><i class="fas fa-user"></i> Username or Email</label>
                <input type="text" id="login" name="login" placeholder="Enter your username or email" required>
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            
            <div id="demoHint" class="demo-hint">
                <i class="fas fa-info-circle"></i> 
                <span id="hintText">Demo: username = "arjun.kapoor", password = "password"</span>
            </div>
            
            <button type="submit" class="btn">Login <i class="fas fa-arrow-right"></i></button>
        </form>
        
        <div class="text-center mt-3">
            <p>Don't have an account? <a href="register.php">Create Account</a></p>
        </div>
    </div>
    
    <script>
        // Theme toggle
        const themeToggle = document.getElementById('themeToggle');
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark');
            localStorage.setItem('theme', document.body.classList.contains('dark') ? 'dark' : 'light');
        });
        
        if (localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark');
        }
        
        // Role selector
        const roleOptions = document.querySelectorAll('.role-option');
        const roleInput = document.getElementById('role');
        const loginLabel = document.getElementById('loginLabel');
        const loginInput = document.getElementById('login');
        const hintText = document.getElementById('hintText');
        
        roleOptions.forEach(option => {
            option.addEventListener('click', function() {
                roleOptions.forEach(opt => opt.classList.remove('active'));
                this.classList.add('active');
                
                const role = this.dataset.role;
                roleInput.value = role;
                
                if(role === 'admin') {
                    loginLabel.innerHTML = '<i class="fas fa-user-shield"></i> Admin Username';
                    loginInput.placeholder = 'Enter admin username';
                    hintText.innerHTML = 'Demo: username = "admin", password = "Admin@123"';
                } else if(role === 'staff') {
                    loginLabel.innerHTML = '<i class="fas fa-user-tie"></i> Staff Email';
                    loginInput.placeholder = 'Enter staff email';
                    hintText.innerHTML = 'Demo: email = "rajesh@ashabank.in", password = "staff123"';
                } else {
                    loginLabel.innerHTML = '<i class="fas fa-user"></i> Username or Email';
                    loginInput.placeholder = 'Enter your username or email';
                    hintText.innerHTML = 'Demo: username = "arjun.kapoor", password = "password"';
                }
            });
        });
    </script>
</body>
</html>