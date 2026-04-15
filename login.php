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
    
    if(empty($login) || empty($password)) {
        $error = "Please enter username/email and password";
    } else {
        if($role == 'admin') {
            // Admin login from ADMIN_USER table
            $stmt = $pdo->prepare("SELECT * FROM ADMIN_USER WHERE Username = ? AND IsActive = 1");
            $stmt->execute([$login]);
            $admin = $stmt->fetch();
            
            if($admin && password_verify($password, $admin['PasswordHash'])) {
                $_SESSION['user_id'] = $admin['AdminID'];
                $_SESSION['username'] = $admin['Username'];
                $_SESSION['role'] = 'admin';
                $_SESSION['is_admin'] = 1;
                setToast("Welcome Admin!", "success");
                redirect('admin/index.php');
            } else {
                $error = "Invalid admin credentials";
            }
            
        } elseif($role == 'staff') {
            // Staff login from EMPLOYEE table (simplified - you can create STAFF table)
            $stmt = $pdo->prepare("SELECT * FROM EMPLOYEE WHERE (Email = ? OR EmployeeID = ?) AND IsActive = 1");
            $stmt->execute([$login, $login]);
            $staff = $stmt->fetch();
            
            // For demo, password is 'staff123' - in production use proper hashing
            if($staff && ($password == 'staff123' || password_verify($password, $staff['PasswordHash'] ?? ''))) {
                $_SESSION['user_id'] = $staff['EmployeeID'];
                $_SESSION['username'] = $staff['FirstName'] . ' ' . $staff['LastName'];
                $_SESSION['role'] = 'staff';
                $_SESSION['is_admin'] = 0;
                setToast("Welcome Staff!", "success");
                redirect('staff/dashboard.php');
            } else {
                $error = "Invalid staff credentials";
            }
            
        } else {
            // Client login from DIGITALBANKINGUSER
            $stmt = $pdo->prepare("SELECT d.UserID, d.CustomerID, d.PasswordHash, c.FirstName, c.LastName, c.Email 
                                   FROM DIGITALBANKINGUSER d 
                                   JOIN CUSTOMER c ON d.CustomerID = c.CustomerID 
                                   WHERE d.Username = ? OR c.Email = ?");
            $stmt->execute([$login, $login]);
            $user = $stmt->fetch();
            
            if($user && password_verify($password, $user['PasswordHash'])) {
                $_SESSION['user_id'] = $user['CustomerID'];
                $_SESSION['username'] = $user['FirstName'] . ' ' . $user['LastName'];
                $_SESSION['role'] = 'client';
                $_SESSION['is_admin'] = 0;
                setToast("Welcome back, " . $user['FirstName'] . "!", "success");
                redirect('dashboard.php');
            } else {
                $error = "Invalid username/email or password";
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
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .role-selector {
            display: flex;
            gap: 1rem;
            margin-bottom: 1.5rem;
            padding: 0.5rem;
            background: var(--glass-bg);
            border-radius: 60px;
        }
        .role-option {
            flex: 1;
            text-align: center;
            padding: 0.75rem;
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 500;
        }
        .role-option i {
            margin-right: 0.5rem;
        }
        .role-option.active {
            background: var(--accent);
            color: white;
        }
        .role-option.admin.active { background: #c0392b; }
        .role-option.staff.active { background: #e67e22; }
        .role-option.client.active { background: var(--accent); }
        .role-input {
            display: none;
        }
        .role-input.active {
            display: block;
        }
    </style>
</head>
<body style="display: flex; align-items: center; justify-content: center; min-height: 100vh;">
    <div class="glass-card fade-in" style="max-width: 450px; width: 100%;">
        <div class="text-center">
            <i class="fas fa-university" style="font-size: 3rem; color: var(--accent);"></i>
            <h2>আশা ব্যাংক</h2>
            <p class="text-muted">Login to your account</p>
        </div>
        
        <?php if($error): ?>
            <div class="alert alert-danger" style="background: rgba(192,57,43,0.1); padding: 0.75rem; border-radius: 12px; margin-bottom: 1rem; color: var(--danger);">
                <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <!-- Role Selector UI -->
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
            
            <div class="form-group" id="usernameGroup">
                <label for="username"><i class="fas fa-user"></i> Username or Email</label>
                <input type="text" id="username" name="login" placeholder="Enter your username or email" required>
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-lock"></i> Password</label>
                <input type="password" id="password" name="password" placeholder="Enter your password" required>
            </div>
            
            <!-- Admin Hint -->
            <div id="adminHint" style="display: none; font-size: 0.8rem; margin-bottom: 1rem; color: var(--text-muted);">
                <i class="fas fa-info-circle"></i> Admin demo: username = "admin", password = "Admin@123"
            </div>
            
            <!-- Staff Hint -->
            <div id="staffHint" style="display: none; font-size: 0.8rem; margin-bottom: 1rem; color: var(--text-muted);">
                <i class="fas fa-info-circle"></i> Staff demo: Email = "rajesh@ashabank.in", password = "staff123"
            </div>
            
            <!-- Client Hint -->
            <div id="clientHint" style="display: none; font-size: 0.8rem; margin-bottom: 1rem; color: var(--text-muted);">
                <i class="fas fa-info-circle"></i> Client demo: username = "arjun.kapoor", password = "password"
            </div>
            
            <button type="submit" class="btn btn-block">Login <i class="fas fa-arrow-right"></i></button>
        </form>
        
        <div class="text-center mt-3">
            <p>Don't have an account? <a href="register.php">Create Account</a></p>
        </div>
    </div>
    
    <div id="toastContainer"></div>
    <script src="assets/js/main.js"></script>
    <script>
        // Role selector functionality
        document.querySelectorAll('.role-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove active class from all
                document.querySelectorAll('.role-option').forEach(opt => opt.classList.remove('active'));
                // Add active class to clicked
                this.classList.add('active');
                
                const role = this.dataset.role;
                document.getElementById('role').value = role;
                
                const usernameGroup = document.getElementById('usernameGroup');
                const usernameInput = document.getElementById('username');
                const adminHint = document.getElementById('adminHint');
                const staffHint = document.getElementById('staffHint');
                const clientHint = document.getElementById('clientHint');
                
                // Hide all hints
                adminHint.style.display = 'none';
                staffHint.style.display = 'none';
                clientHint.style.display = 'none';
                
                if(role === 'admin') {
                    usernameGroup.querySelector('label').innerHTML = '<i class="fas fa-user-shield"></i> Admin Username';
                    usernameInput.placeholder = 'Enter admin username';
                    adminHint.style.display = 'block';
                } else if(role === 'staff') {
                    usernameGroup.querySelector('label').innerHTML = '<i class="fas fa-user-tie"></i> Staff ID / Email';
                    usernameInput.placeholder = 'Enter staff ID or email';
                    staffHint.style.display = 'block';
                } else {
                    usernameGroup.querySelector('label').innerHTML = '<i class="fas fa-user"></i> Username or Email';
                    usernameInput.placeholder = 'Enter your username or email';
                    clientHint.style.display = 'block';
                }
            });
        });
        
        // Show client hint by default
        document.getElementById('clientHint').style.display = 'block';
        
        // Dark mode sync
        if(localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark');
        }
    </script>
</body>
</html>