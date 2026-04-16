<?php 
require_once 'config/db.php';

if(isLoggedIn()) redirect('dashboard.php');

$error = '';
$showKycPopup = false;
$newCustomerId = null;

if($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $address = trim($_POST['address']);
    $dob = $_POST['dob'];
    $nid = trim($_POST['nid'] ?? '');

    if(empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($password)) {
        $error = "Please fill all required fields";
    } else {
        try {
            // Check if email exists
            $check = $pdo->prepare("SELECT CustomerID FROM CUSTOMER WHERE Email = ?");
            $check->execute([$email]);
            if($check->fetch()) {
                throw new Exception("This email address is already registered");
            }
            
            // Hash password
            $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
            
            // Insert Customer
            $stmt = $pdo->prepare("INSERT INTO CUSTOMER (FirstName, LastName, Email, Phone, Address, DateOfBirth, CustomerCategoryID, PrimaryBranchID, CreatedAt, IsActive) VALUES (?,?,?,?,?,?,1,1,NOW(),1)");
            $stmt->execute([$firstName, $lastName, $email, $phone, $address, $dob]);
            $customerId = $pdo->lastInsertId();
            
            if(!$customerId) {
                throw new Exception("Failed to create customer record");
            }
            
            // Generate and insert account
            $accountNumber = generateAccountNumber($pdo);
            $stmtAcc = $pdo->prepare("INSERT INTO ACCOUNT (AccountNumber, ProductID, CustomerID, BranchID, OpeningDate, AvailableBalance, AccountStatus) VALUES (?,1,?,1,CURDATE(),0,'Active')");
            $stmtAcc->execute([$accountNumber, $customerId]);
            
            // Create cards table if not exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS CARDS (
                CardID INT AUTO_INCREMENT PRIMARY KEY, 
                CardNumber VARCHAR(20) NOT NULL UNIQUE, 
                CustomerID INT NOT NULL, 
                ExpiryDate DATE NOT NULL, 
                CVV VARCHAR(4) NOT NULL, 
                CardType ENUM('Debit','Credit','Prepaid') DEFAULT 'Debit',
                IsActive TINYINT(1) DEFAULT 1,
                FOREIGN KEY (CustomerID) REFERENCES CUSTOMER(CustomerID)
            )");
            
            // Insert card
            $cardNumber = "4" . mt_rand(100000000000000, 999999999999999);
            $expiry = date("Y-m-d", strtotime("+5 years"));
            $cvv = mt_rand(100, 999);
            $stmtCard = $pdo->prepare("INSERT INTO CARDS (CardNumber, CustomerID, ExpiryDate, CVV) VALUES (?,?,?,?)");
            $stmtCard->execute([$cardNumber, $customerId, $expiry, $cvv]);
            
            // Create digital banking user
            $username = strtolower($firstName) . rand(100, 999);
            $stmtUser = $pdo->prepare("INSERT INTO DIGITALBANKINGUSER (CustomerID, Username, PasswordHash, CreatedAt, IsActive) VALUES (?,?,?,NOW(),1)");
            $stmtUser->execute([$customerId, $username, $hashedPassword]);
            
            // Create kyc_verifications table if not exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS kyc_verifications (
                kyc_id INT AUTO_INCREMENT PRIMARY KEY,
                customer_id INT NOT NULL,
                nid_number VARCHAR(50),
                phone_number VARCHAR(20),
                verification_code VARCHAR(10),
                status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
                rejection_reason TEXT,
                verified_at DATETIME,
                submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
            )");
            
            // Insert KYC record
            $stmtKyc = $pdo->prepare("INSERT INTO kyc_verifications (customer_id, nid_number, status, submitted_at) VALUES (?, ?, 'pending', NOW())");
            $stmtKyc->execute([$customerId, $nid]);
            
            // Create notifications table if not exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
                notification_id INT AUTO_INCREMENT PRIMARY KEY,
                customer_id INT,
                title VARCHAR(200) NOT NULL,
                message TEXT NOT NULL,
                type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
                is_read TINYINT(1) DEFAULT 0,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (customer_id) REFERENCES CUSTOMER(CustomerID) ON DELETE CASCADE
            )");
            
            // Insert notifications
            $stmtNotif = $pdo->prepare("INSERT INTO notifications (customer_id, title, message, type) VALUES (?, 'Welcome to Asha Bank!', 'Thank you for joining Asha Bank. Please complete your KYC verification to activate full account features.', 'info')");
            $stmtNotif->execute([$customerId]);
            
            $stmtNotif2 = $pdo->prepare("INSERT INTO notifications (customer_id, title, message, type) VALUES (?, 'KYC Verification Required', 'Your KYC verification is pending. Please submit your NID/Passport for verification.', 'warning')");
            $stmtNotif2->execute([$customerId]);

            // Set session
            $_SESSION['user_id'] = $customerId;
            $_SESSION['username'] = $firstName . ' ' . $lastName;
            $_SESSION['role'] = 'client';

            $showKycPopup = true;
            $newCustomerId = $customerId;

        } catch(Exception $e) {
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
    <title>Open Account — Asha Bank</title>
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

        .register-container {
            display: flex;
            max-width: 1100px;
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

        .brand-panel::after {
            content: '';
            position: absolute;
            bottom: -50px;
            left: -50px;
            width: 250px;
            height: 250px;
            background: rgba(139, 92, 246, 0.08);
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

        .steps-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .step-item {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .step-number {
            width: 28px;
            height: 28px;
            background: rgba(59, 130, 246, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: 600;
            color: #3b82f6;
        }

        .step-text {
            color: rgba(255, 255, 255, 0.7);
            font-size: 13px;
        }

        .step-text strong {
            display: block;
            color: white;
            margin-bottom: 2px;
        }

        .footer-note {
            position: relative;
            z-index: 1;
            font-size: 11px;
            color: rgba(255, 255, 255, 0.4);
        }

        .form-panel {
            flex: 1.2;
            padding: 48px;
            background: var(--bg-primary);
            overflow-y: auto;
            max-height: 90vh;
        }

        .form-header {
            margin-bottom: 28px;
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

        .input-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .input-group {
            margin-bottom: 18px;
        }

        .input-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 6px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .input-group input {
            width: 100%;
            height: 44px;
            border-radius: 12px;
            border: 1.5px solid var(--border-color);
            background: var(--bg-secondary);
            padding: 0 14px;
            font-size: 14px;
            transition: all 0.2s;
            outline: none;
            color: var(--text-primary);
        }

        .input-group input:focus {
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .section-divider {
            display: flex;
            align-items: center;
            gap: 12px;
            margin: 20px 0 16px;
        }

        .section-divider span {
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-tertiary);
            letter-spacing: 1px;
        }

        .section-divider::after {
            content: '';
            flex: 1;
            height: 1px;
            background: var(--border-color);
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

        .terms-note {
            font-size: 11px;
            color: var(--text-tertiary);
            text-align: center;
            margin-top: 16px;
        }

        .terms-note a {
            color: #3b82f6;
            text-decoration: none;
        }

        .form-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 13px;
            color: var(--text-tertiary);
        }

        .form-footer a {
            color: #3b82f6;
            text-decoration: none;
            font-weight: 600;
        }

        /* KYC Modal */
        .kyc-overlay {
            display: <?= $showKycPopup ? 'flex' : 'none' ?>;
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(6px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .kyc-modal {
            background: var(--bg-primary);
            border-radius: 28px;
            padding: 40px;
            max-width: 420px;
            width: 100%;
            text-align: center;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .kyc-icon {
            width: 70px;
            height: 70px;
            background: #fef3c7;
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }

        .kyc-icon i {
            font-size: 32px;
            color: #d97706;
        }

        .kyc-modal h2 {
            font-size: 24px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 12px;
        }

        .kyc-modal p {
            font-size: 14px;
            color: var(--text-tertiary);
            line-height: 1.6;
            margin-bottom: 28px;
        }

        .kyc-actions {
            display: flex;
            gap: 12px;
        }

        .kyc-btn {
            flex: 1;
            padding: 12px;
            border-radius: 40px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            text-align: center;
            transition: all 0.2s;
        }

        .kyc-btn-primary {
            background: #0f172a;
            color: white;
            border: none;
        }

        .kyc-btn-primary:hover {
            background: #1e293b;
        }

        body.dark .kyc-btn-primary {
            background: var(--accent);
        }

        .kyc-btn-secondary {
            background: var(--bg-secondary);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .kyc-btn-secondary:hover {
            background: var(--accent-bg);
        }

        @media (max-width: 900px) {
            .register-container {
                flex-direction: column;
                max-width: 550px;
            }
            
            .brand-panel {
                padding: 32px;
                text-align: center;
            }
            
            .brand-content h2 {
                font-size: 24px;
            }
            
            .steps-list {
                display: none;
            }
            
            .form-panel {
                padding: 32px;
                max-height: none;
            }
            
            .home-btn, .theme-toggle-btn {
                top: 16px;
                padding: 8px 16px;
                font-size: 12px;
            }
            .home-btn { left: 16px; }
            .theme-toggle-btn { right: 16px; }
        }

        @media (max-width: 520px) {
            .input-row {
                grid-template-columns: 1fr;
                gap: 0;
            }
            
            .form-panel {
                padding: 24px;
            }
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

<div class="register-container">
    <!-- Left Brand Panel -->
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
            <h2>Open an <span>account</span><br>in minutes</h2>
            <p>No branch visits. No paperwork. Get a full-service digital banking account instantly.</p>
            
            <div class="steps-list">
                <div class="step-item">
                    <div class="step-number">1</div>
                    <div class="step-text"><strong>Fill your details</strong><span>Basic personal information</span></div>
                </div>
                <div class="step-item">
                    <div class="step-number">2</div>
                    <div class="step-text"><strong>KYC verification</strong><span>Submit NID for compliance</span></div>
                </div>
                <div class="step-item">
                    <div class="step-number">3</div>
                    <div class="step-text"><strong>Account activated</strong><span>Start banking immediately</span></div>
                </div>
            </div>
        </div>
        
        <div class="footer-note">100% digital onboarding process</div>
    </div>
    
    <!-- Right Form Panel -->
    <div class="form-panel">
        <div class="form-header">
            <h1>Create your account</h1>
            <p>Takes less than 3 minutes to complete</p>
        </div>
        
        <?php if($error): ?>
        <div class="error-box">
            <i class="fas fa-exclamation-circle"></i>
            <?= htmlspecialchars($error) ?>
        </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="section-divider"><span>Personal details</span></div>
            
            <div class="input-row">
                <div class="input-group">
                    <label>First Name *</label>
                    <input type="text" name="first_name" placeholder="Arjun" value="<?= htmlspecialchars($_POST['first_name'] ?? '') ?>" required>
                </div>
                <div class="input-group">
                    <label>Last Name *</label>
                    <input type="text" name="last_name" placeholder="Kapoor" value="<?= htmlspecialchars($_POST['last_name'] ?? '') ?>" required>
                </div>
            </div>
            
            <div class="input-group">
                <label>Email Address *</label>
                <input type="email" name="email" placeholder="arjun@example.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>
            
            <div class="input-row">
                <div class="input-group">
                    <label>Phone Number *</label>
                    <input type="tel" name="phone" placeholder="01XXXXXXXXX" value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>" required>
                </div>
                <div class="input-group">
                    <label>Date of Birth</label>
                    <input type="date" name="dob" value="<?= htmlspecialchars($_POST['dob'] ?? '') ?>">
                </div>
            </div>
            
            <div class="section-divider"><span>Security & KYC</span></div>
            
            <div class="input-row">
                <div class="input-group">
                    <label>Password *</label>
                    <input type="password" name="password" placeholder="Minimum 6 characters" required>
                </div>
                <div class="input-group">
                    <label>NID Number</label>
                    <input type="text" name="nid" placeholder="National ID for KYC" value="<?= htmlspecialchars($_POST['nid'] ?? '') ?>">
                </div>
            </div>
            
            <div class="input-group">
                <label>Residential Address</label>
                <input type="text" name="address" placeholder="Your full address in Bangladesh" value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
            </div>
            
            <button type="submit" class="submit-btn">Create Account →</button>
        </form>
        
        <p class="terms-note">By creating an account, you agree to our <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></p>
        
        <div class="form-footer">
            Already have an account? <a href="login.php">Sign in →</a>
        </div>
    </div>
</div>

<!-- KYC Popup -->
<?php if($showKycPopup): ?>
<div class="kyc-overlay" id="kycOverlay">
    <div class="kyc-modal">
        <div class="kyc-icon">
            <i class="fas fa-id-card"></i>
        </div>
        <h2>Account created!</h2>
        <p>Your account is ready. To unlock full features like fund transfers and withdrawals, complete your KYC verification. Our team reviews submissions within 24 hours.</p>
        <div class="kyc-actions">
            <a href="dashboard.php" class="kyc-btn kyc-btn-secondary">Go to dashboard</a>
            <button onclick="document.getElementById('kycOverlay').style.display='none'; window.location='dashboard.php';" class="kyc-btn kyc-btn-primary">Understood →</button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // Theme Toggle for Register Page
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