<?php
require_once 'config/db.php';
require_once 'config/language.php';

if(!isLoggedIn() || !isClient()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];

// Check if account is active
$statusMessage = getAccountStatusMessage($pdo, $userId);
$accountActive = ($statusMessage === null);

if(!$accountActive) {
    setToast($statusMessage, 'warning');
}

$error = '';
$success = '';

// Get user data
$stmt = $pdo->prepare("SELECT c.*, a.AccountNumber, a.AvailableBalance, a.AccountStatus 
                       FROM CUSTOMER c 
                       LEFT JOIN ACCOUNT a ON c.CustomerID = a.CustomerID 
                       WHERE c.CustomerID = ?");
$stmt->execute([$userId]);
$userData = $stmt->fetch();

// Get nominee
$stmtNom = $pdo->prepare("SELECT * FROM NOMINEE WHERE CustomerID = ?");
$stmtNom->execute([$userId]);
$nominee = $stmtNom->fetch();

// Update Profile
if(isset($_POST['update_profile']) && $accountActive) {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $city = trim($_POST['city']);
    
    try {
        $pdo->prepare("UPDATE CUSTOMER SET FirstName = ?, LastName = ?, Email = ?, Phone = ?, Address = ?, City = ? WHERE CustomerID = ?")
            ->execute([$firstName, $lastName, $email, $phone, $address, $city, $userId]);
        setToast(__("profile_updated"), "success");
        redirect('profile.php');
    } catch(Exception $e) {
        $error = "Update failed: " . $e->getMessage();
    }
}

// Update Nominee
if(isset($_POST['update_nominee'])) {
    $nomineeName = trim($_POST['nominee_name']);
    $nomineeRelation = trim($_POST['nominee_relation']);
    $nomineePhone = trim($_POST['nominee_phone']);
    
    try {
        $pdo->prepare("DELETE FROM NOMINEE WHERE CustomerID = ?")->execute([$userId]);
        if(!empty($nomineeName)) {
            $pdo->prepare("INSERT INTO NOMINEE (CustomerID, NomineeName, NomineeRelation, NomineePhone) VALUES (?, ?, ?, ?)")
                ->execute([$userId, $nomineeName, $nomineeRelation, $nomineePhone]);
        }
        setToast(__("nominee_updated"), "success");
        redirect('profile.php');
    } catch(Exception $e) {
        $error = "Nominee update failed: " . $e->getMessage();
    }
}

// Change Password
if(isset($_POST['change_password']) && $accountActive) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    $stmt = $pdo->prepare("SELECT PasswordHash FROM DIGITALBANKINGUSER WHERE CustomerID = ?");
    $stmt->execute([$userId]);
    $userPass = $stmt->fetch();
    
    if(password_verify($currentPassword, $userPass['PasswordHash'])) {
        if($newPassword == $confirmPassword && strlen($newPassword) >= 4) {
            $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE DIGITALBANKINGUSER SET PasswordHash = ? WHERE CustomerID = ?")->execute([$newHash, $userId]);
            setToast(__("password_changed"), "success");
            redirect('profile.php');
        } else {
            $error = __("password_mismatch");
        }
    } else {
        $error = __("wrong_password");
    }
}

// Delete Account Request
if(isset($_POST['delete_account']) && $accountActive) {
    $confirmDelete = $_POST['confirm_delete'];
    if($confirmDelete == 'DELETE') {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE CUSTOMER SET IsActive = 0 WHERE CustomerID = ?")->execute([$userId]);
            $pdo->prepare("UPDATE ACCOUNT SET AccountStatus = 'Closed' WHERE CustomerID = ?")->execute([$userId]);
            $pdo->commit();
            session_destroy();
            setToast("Account closed successfully", "info");
            redirect('login.php');
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = "Account deletion failed: " . $e->getMessage();
        }
    } else {
        $error = "Please type DELETE to confirm";
    }
}

$tab = $_GET['tab'] ?? 'profile';
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('profile') ?> - Asha Bank</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,300;14..32,400;14..32,500;14..32,600;14..32,700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
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
            --accent-text: #0C447C;
            --success: #3B6D11;
            --success-bg: #EAF3DE;
            --danger: #A32D2D;
            --danger-bg: #FCEBEB;
            --warning: #BA7517;
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
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
            --accent-text: #93C5FD;
            --danger-bg: #7f1d1d;
            --success-bg: #14532d;
        }
        
        body { font-family: var(--font-sans); background: var(--bg-secondary); color: var(--text-primary); transition: all 0.3s ease; }
        
        /* Navbar */
        .navbar {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 12px;
        }
        
        .logo { display: flex; align-items: center; gap: 12px; }
        .logo-icon { width: 36px; height: 36px; background: var(--accent-bg); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .logo-icon svg { width: 20px; height: 20px; }
        .navbar-right { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        
        .back-button {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 40px;
            padding: 8px 16px;
            text-decoration: none;
            color: var(--text-primary);
            font-size: 13px;
            font-weight: 500;
            transition: all 0.2s;
            margin-bottom: 20px;
        }
        .back-button:hover { background: var(--accent-bg); border-color: var(--accent); color: var(--accent-text); }
        
        .theme-toggle { 
            background: var(--bg-secondary); 
            border: 1px solid var(--border-color); 
            border-radius: 40px; 
            padding: 8px 16px; 
            cursor: pointer; 
            font-size: 13px; 
            transition: all 0.2s;
            color: var(--text-primary);
        }
        .theme-toggle:hover { background: var(--accent-bg); }
        
        .container { max-width: 1000px; margin: 0 auto; padding: 24px; }
        
        /* Tabs */
        .tabs {
            display: flex;
            gap: 8px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 60px;
            padding: 6px;
            margin-bottom: 24px;
        }
        .tab {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: 40px;
            cursor: pointer;
            transition: all 0.2s;
            font-weight: 500;
            font-size: 14px;
            color: var(--text-secondary);
        }
        .tab.active {
            background: var(--accent);
            color: white;
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* Cards */
        .glass-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 24px;
            padding: 28px;
            margin-bottom: 24px;
        }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; margin-bottom: 8px; font-weight: 500; font-size: 13px; color: var(--text-secondary); }
        .form-group input { 
            width: 100%; 
            padding: 12px; 
            border: 1px solid var(--border-color); 
            border-radius: 12px; 
            background: var(--bg-secondary); 
            color: var(--text-primary); 
            outline: none; 
        }
        .form-group input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-bg); }
        .form-group input:disabled { opacity: 0.6; cursor: not-allowed; }
        
        /* Buttons */
        .btn { 
            padding: 12px 24px; 
            border-radius: 40px; 
            border: none; 
            font-weight: 600; 
            cursor: pointer; 
            transition: all 0.2s; 
        }
        .btn-primary { 
            background: var(--accent); 
            color: white; 
            width: 100%;
        }
        .btn-primary:hover { 
            background: var(--accent-dark); 
            transform: translateY(-2px);
            color: white;
        }
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }
        .btn-danger { 
            background: var(--danger); 
            color: white;
        }
        .btn-danger:hover { 
            background: #a32d2d; 
            transform: translateY(-2px);
            color: white;
        }
        
        /* Messages */
        .error-message { 
            background: var(--danger-bg); 
            color: var(--danger); 
            padding: 12px; 
            border-radius: 12px; 
            margin-bottom: 20px; 
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .warning-message {
            background: var(--warning-bg);
            color: var(--warning);
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        /* Info Box */
        .info-box {
            background: var(--bg-secondary);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid var(--border-color);
        }
        .info-row:last-child { border-bottom: none; }
        .info-label { font-weight: 500; color: var(--text-secondary); }
        .info-value { font-weight: 600; color: var(--text-primary); }
        
        /* Delete Warning */
        .delete-warning {
            background: var(--danger-bg);
            border: 1px solid var(--danger);
            border-radius: 16px;
            padding: 20px;
            text-align: center;
            margin-top: 20px;
        }
        .delete-warning p, .delete-warning h4 { color: var(--danger); }
        .delete-warning .form-group label { color: var(--danger); }
        
        /* Responsive */
        @media (max-width: 768px) {
            .navbar { flex-direction: column; }
            .navbar-right { justify-content: center; }
            .tabs { flex-wrap: wrap; border-radius: 16px; }
            .tab { font-size: 12px; }
            .container { padding: 16px; }
            .glass-card { padding: 20px; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">
            <div class="logo-icon">
                <svg width="20" height="20" viewBox="0 0 16 16" fill="none">
                    <rect x="1" y="6" width="14" height="9" rx="1.5" fill="none" stroke="var(--accent)" stroke-width="1.2"/>
                    <path d="M4 6V4a4 4 0 0 1 8 0v2" stroke="var(--accent)" stroke-width="1.2"/>
                    <circle cx="8" cy="10.5" r="1.5" fill="var(--accent)"/>
                </svg>
            </div>
            <h2>Asha Bank</h2>
        </div>
        <div class="navbar-right">
            <div class="language-switcher" style="display: flex; gap: 5px; background: var(--bg-secondary); padding: 4px 8px; border-radius: 40px;">
                <a href="?lang=en" style="text-decoration: none; padding: 4px 8px; border-radius: 30px; <?= (current_lang() == 'en') ? 'background: var(--accent); color: white;' : 'color: var(--text-secondary);' ?>">EN</a>
                <a href="?lang=bn" style="text-decoration: none; padding: 4px 8px; border-radius: 30px; <?= (current_lang() == 'bn') ? 'background: var(--accent); color: white;' : 'color: var(--text-secondary);' ?>">বাংলা</a>
            </div>
            <button class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i> Dark</button>
            <a href="logout.php" style="background: var(--danger); color: white; padding: 8px 16px; border-radius: 40px; text-decoration: none;"><?= __('logout') ?></a>
        </div>
    </nav>
    
    <div class="container">
        <div class="back-button-container">
            <a href="dashboard.php" class="back-button"><i class="fas fa-arrow-left"></i> <?= __('back_to_dashboard') ?></a>
        </div>
        
        <?php if(!$accountActive): ?>
            <div class="warning-message">
                <i class="fas fa-lock"></i> 
                <span>Your account is deactivated. You cannot update your profile. Please contact support for reactivation.</span>
            </div>
        <?php endif; ?>
        
        <div class="tabs">
            <div class="tab <?= $tab == 'profile' ? 'active' : '' ?>" data-tab="profile"><i class="fas fa-user"></i> <?= __('profile') ?></div>
            <div class="tab <?= $tab == 'nominee' ? 'active' : '' ?>" data-tab="nominee"><i class="fas fa-user-friends"></i> <?= __('nominee') ?></div>
            <div class="tab <?= $tab == 'security' ? 'active' : '' ?>" data-tab="security"><i class="fas fa-lock"></i> Security</div>
            <div class="tab <?= $tab == 'account' ? 'active' : '' ?>" data-tab="account"><i class="fas fa-cog"></i> Account</div>
        </div>
        
        <?php if($error): ?>
            <div class="error-message"><i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Profile Tab -->
        <div id="profileTab" class="tab-content <?= $tab == 'profile' ? 'active' : '' ?>">
            <div class="glass-card">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-user-circle"></i> <?= __('profile') ?></h3>
                <form method="POST">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($userData['FirstName'] ?? '') ?>" <?= !$accountActive ? 'disabled' : '' ?> required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($userData['LastName'] ?? '') ?>" <?= !$accountActive ? 'disabled' : '' ?> required>
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" value="<?= htmlspecialchars($userData['Email'] ?? '') ?>" <?= !$accountActive ? 'disabled' : '' ?> required>
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="phone" value="<?= htmlspecialchars($userData['Phone'] ?? '') ?>" <?= !$accountActive ? 'disabled' : '' ?> required>
                    </div>
                    <div class="form-group">
                        <label>Address</label>
                        <input type="text" name="address" value="<?= htmlspecialchars($userData['Address'] ?? '') ?>" <?= !$accountActive ? 'disabled' : '' ?>>
                    </div>
                    <div class="form-group">
                        <label>City</label>
                        <input type="text" name="city" value="<?= htmlspecialchars($userData['City'] ?? '') ?>" <?= !$accountActive ? 'disabled' : '' ?>>
                    </div>
                    <button type="submit" name="update_profile" class="btn btn-primary" <?= !$accountActive ? 'disabled' : '' ?>>
                        <?= __('update_profile') ?>
                    </button>
                </form>
            </div>
            
            <div class="glass-card">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-credit-card"></i> Account Information</h3>
                <div class="info-box">
                    <div class="info-row"><span class="info-label">Account Number</span><span class="info-value"><?= htmlspecialchars($userData['AccountNumber'] ?? 'N/A') ?></span></div>
                    <div class="info-row"><span class="info-label">Current Balance</span><span class="info-value"><?= formatBDT($userData['AvailableBalance'] ?? 0) ?></span></div>
                    <div class="info-row"><span class="info-label">Account Status</span><span class="info-value"><?= htmlspecialchars($userData['AccountStatus'] ?? 'Active') ?></span></div>
                    <div class="info-row"><span class="info-label">Member Since</span><span class="info-value"><?= date('d M Y', strtotime($userData['CreatedAt'] ?? 'now')) ?></span></div>
                </div>
            </div>
        </div>
        
        <!-- Nominee Tab -->
        <div id="nomineeTab" class="tab-content <?= $tab == 'nominee' ? 'active' : '' ?>">
            <div class="glass-card">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-user-friends"></i> <?= __('nominee') ?></h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Nominee Name</label>
                        <input type="text" name="nominee_name" value="<?= htmlspecialchars($nominee['NomineeName'] ?? '') ?>" placeholder="Enter nominee's full name">
                    </div>
                    <div class="form-group">
                        <label>Relationship</label>
                        <input type="text" name="nominee_relation" value="<?= htmlspecialchars($nominee['NomineeRelation'] ?? '') ?>" placeholder="e.g., Spouse, Child, Parent">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="nominee_phone" value="<?= htmlspecialchars($nominee['NomineePhone'] ?? '') ?>" placeholder="Nominee's phone number">
                    </div>
                    <button type="submit" name="update_nominee" class="btn btn-primary"><?= __('save_changes') ?></button>
                </form>
                <div class="delete-warning" style="margin-top: 20px; background: var(--accent-bg); border-color: var(--accent);">
                    <p style="margin-bottom: 8px;"><i class="fas fa-info-circle"></i> Why add a nominee?</p>
                    <small>Adding a nominee ensures that your assets are transferred to your loved ones smoothly.</small>
                </div>
            </div>
        </div>
        
        <!-- Security Tab -->
        <div id="securityTab" class="tab-content <?= $tab == 'security' ? 'active' : '' ?>">
            <div class="glass-card">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-key"></i> <?= __('change_password') ?></h3>
                <form method="POST">
                    <div class="form-group">
                        <label>Current Password</label>
                        <input type="password" name="current_password" <?= !$accountActive ? 'disabled' : '' ?> required>
                    </div>
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="new_password" <?= !$accountActive ? 'disabled' : '' ?> required>
                        <small>Minimum 4 characters</small>
                    </div>
                    <div class="form-group">
                        <label>Confirm Password</label>
                        <input type="password" name="confirm_password" <?= !$accountActive ? 'disabled' : '' ?> required>
                    </div>
                    <button type="submit" name="change_password" class="btn btn-primary" <?= !$accountActive ? 'disabled' : '' ?>>
                        <?= __('change_password') ?>
                    </button>
                </form>
            </div>
        </div>
        
        <!-- Account Tab -->
        <div id="accountTab" class="tab-content <?= $tab == 'account' ? 'active' : '' ?>">
            <div class="glass-card">
                <h3 style="margin-bottom: 20px;"><i class="fas fa-exclamation-triangle"></i> Account Settings</h3>
                <div class="delete-warning">
                    <i class="fas fa-trash-alt" style="font-size: 32px; margin-bottom: 12px; display: block; color: var(--danger);"></i>
                    <h4 style="margin-bottom: 12px;">Delete Account</h4>
                    <p style="margin-bottom: 16px; font-size: 13px;">Once you delete your account, there is no going back. Please be certain.</p>
                    <form method="POST" onsubmit="return confirm('Are you absolutely sure you want to delete your account? This action cannot be undone!')">
                        <div class="form-group">
                            <label>Type "DELETE" to confirm</label>
                            <input type="text" name="confirm_delete" placeholder="DELETE" <?= !$accountActive ? 'disabled' : '' ?> required>
                        </div>
                        <button type="submit" name="delete_account" class="btn btn-danger" style="width: 100%;" <?= !$accountActive ? 'disabled' : '' ?>>
                            Permanently Delete Account
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <div id="toastContainer">
        <?php $toast = getToast(); if($toast): ?>
            <div style="position: fixed; top: 80px; right: 20px; background: var(--bg-primary); border-left: 4px solid var(--success); padding: 12px 20px; border-radius: 12px; box-shadow: var(--shadow-lg); z-index: 1000;">
                <i class="fas fa-check-circle" style="color: var(--success);"></i> <?= htmlspecialchars($toast['message']) ?>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        if(localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i> Light';
        }
        themeToggle.addEventListener('click', () => {
            if(document.body.classList.contains('dark')) {
                document.body.classList.remove('dark');
                localStorage.setItem('theme', 'light');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i> Dark';
            } else {
                document.body.classList.add('dark');
                localStorage.setItem('theme', 'dark');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i> Light';
            }
        });
        
        // Tab switching
        document.querySelectorAll('.tab').forEach(tab => {
            tab.addEventListener('click', function() {
                window.location.href = `?tab=${this.dataset.tab}`;
            });
        });
        
        // Auto-hide toast after 3 seconds
        setTimeout(() => {
            const toast = document.querySelector('#toastContainer > div');
            if(toast) {
                toast.style.animation = 'slideOutRight 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }
        }, 3000);
    </script>
</body>
</html>