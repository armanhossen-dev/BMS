<?php
require_once 'config/db.php';
require_once 'config/language.php';

if(!isLoggedIn() || !isClient()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];

// Get user account
$stmt = $pdo->prepare("SELECT a.*, c.FirstName, c.LastName, c.Email, c.Phone, c.Address, c.DateOfBirth 
                       FROM ACCOUNT a 
                       JOIN CUSTOMER c ON a.CustomerID = c.CustomerID 
                       WHERE c.CustomerID = ?");
$stmt->execute([$userId]);
$account = $stmt->fetch();

if(!$account) {
    setToast("Account not found", "error");
    redirect('logout.php');
}

$balance = $account['AvailableBalance'] ?? 0;

// Get card details
$stmtCard = $pdo->prepare("SELECT * FROM CARDS WHERE CustomerID = ?");
$stmtCard->execute([$userId]);
$card = $stmtCard->fetch();

// Determine card tier based on balance with dynamic colors
function getCardTier($balance) {
    $balanceNum = floatval($balance);
    
    // Black Edition - 1,000,000+ BDT
    if($balanceNum >= 1000000) {
        return [
            'name' => 'Black Edition',
            'level' => 5,
            'bg' => 'linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 40%, #2a2a2a 100%)',
            'accent' => '#d4af37',
            'accent2' => '#ffd700',
            'text' => '#ffffff',
            'icon' => 'crown',
            'badge' => '👑'
        ];
    }
    // Platinum - 500,000 - 999,999 BDT
    elseif($balanceNum >= 500000) {
        return [
            'name' => 'Platinum',
            'level' => 4,
            'bg' => 'linear-gradient(135deg, #1a2a3a 0%, #2a3a4a 50%, #3a4a5a 100%)',
            'accent' => '#c0c0c0',
            'accent2' => '#e8e8e8',
            'text' => '#ffffff',
            'icon' => 'gem',
            'badge' => '💎'
        ];
    }
    // Gold - 100,000 - 499,999 BDT
    elseif($balanceNum >= 100000) {
        return [
            'name' => 'Gold',
            'level' => 3,
            'bg' => 'linear-gradient(135deg, #2a1a0a 0%, #3a2a1a 50%, #4a3a2a 100%)',
            'accent' => '#ffd700',
            'accent2' => '#ffed4a',
            'text' => '#ffffff',
            'icon' => 'star',
            'badge' => '⭐'
        ];
    }
    // Silver - 10,000 - 99,999 BDT
    elseif($balanceNum >= 10000) {
        return [
            'name' => 'Silver',
            'level' => 2,
            'bg' => 'linear-gradient(135deg, #1a2a2a 0%, #2a3a3a 50%, #3a4a4a 100%)',
            'accent' => '#c0c0c0',
            'accent2' => '#d8d8d8',
            'text' => '#ffffff',
            'icon' => 'shield',
            'badge' => '🛡️'
        ];
    }
    // Bronze / Classic - Below 10,000 BDT
    else {
        return [
            'name' => 'Classic',
            'level' => 1,
            'bg' => 'linear-gradient(135deg, #185FA5 0%, #0C447C 50%, #0a3a6a 100%)',
            'accent' => '#ffd700',
            'accent2' => '#ffed4a',
            'text' => '#ffffff',
            'icon' => 'credit-card',
            'badge' => '💳'
        ];
    }
}

$cardTier = getCardTier($balance);

// Create tables if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
    notification_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    type ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
    is_read TINYINT(1) DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS kyc_verifications (
    kyc_id INT AUTO_INCREMENT PRIMARY KEY,
    customer_id INT NOT NULL,
    nid_number VARCHAR(50),
    phone_number VARCHAR(20),
    verification_code VARCHAR(10),
    status ENUM('pending', 'verified', 'rejected') DEFAULT 'pending',
    rejection_reason TEXT,
    verified_at DATETIME,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Get unread notifications count
$unreadNotif = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE customer_id = ? AND is_read = 0");
$unreadNotif->execute([$userId]);
$unreadCount = $unreadNotif->fetchColumn();

// Get all notifications
$notifications = $pdo->prepare("SELECT * FROM notifications WHERE customer_id = ? ORDER BY created_at DESC LIMIT 20");
$notifications->execute([$userId]);
$notifList = $notifications->fetchAll();

// Mark notification as read via AJAX
if(isset($_GET['ajax_mark_read']) && isset($_GET['id'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE notification_id = ? AND customer_id = ?")->execute([$_GET['id'], $userId]);
    echo json_encode(['success' => true]);
    exit();
}

// Mark all as read
if(isset($_GET['mark_all_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE customer_id = ?")->execute([$userId]);
    redirect('dashboard.php');
}

// Get recent transactions
$stmtTrans = $pdo->prepare("SELECT t.*, tt.TypeName 
                            FROM TRANSACTION t 
                            JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
                            WHERE t.FromCustomerID = ? OR t.ToCustomerID = ? 
                            ORDER BY t.TransactionDate DESC LIMIT 10");
$stmtTrans->execute([$userId, $userId]);
$transactions = $stmtTrans->fetchAll();

// Get nominee
$stmtNom = $pdo->prepare("SELECT * FROM NOMINEE WHERE CustomerID = ?");
$stmtNom->execute([$userId]);
$nominee = $stmtNom->fetch();

// Get KYC status
$stmtKyc = $pdo->prepare("SELECT * FROM kyc_verifications WHERE customer_id = ?");
$stmtKyc->execute([$userId]);
$kycStatus = $stmtKyc->fetch();

// Get total sent/received
$totalSent = $pdo->prepare("SELECT COALESCE(SUM(TransactionAmount), 0) FROM TRANSACTION WHERE FromCustomerID = ? AND TransactionStatus = 'Completed'");
$totalSent->execute([$userId]);
$totalSentAmount = $totalSent->fetchColumn();

$totalReceived = $pdo->prepare("SELECT COALESCE(SUM(TransactionAmount), 0) FROM TRANSACTION WHERE ToCustomerID = ? AND TransactionStatus = 'Completed'");
$totalReceived->execute([$userId]);
$totalReceivedAmount = $totalReceived->fetchColumn();

// Next tier info
function getNextTierInfo($balance) {
    if($balance < 10000) {
        return ['name' => 'Silver', 'amount' => 10000 - $balance, 'percentage' => ($balance / 10000) * 100];
    } elseif($balance < 100000) {
        return ['name' => 'Gold', 'amount' => 100000 - $balance, 'percentage' => ($balance / 100000) * 100];
    } elseif($balance < 500000) {
        return ['name' => 'Platinum', 'amount' => 500000 - $balance, 'percentage' => ($balance / 500000) * 100];
    } elseif($balance < 1000000) {
        return ['name' => 'Black Edition', 'amount' => 1000000 - $balance, 'percentage' => ($balance / 1000000) * 100];
    } else {
        return ['name' => 'Black Edition', 'amount' => 0, 'percentage' => 100];
    }
}
$nextTier = getNextTierInfo($balance);
?>
<!DOCTYPE html>
<html lang="<?= current_lang() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= __('dashboard') ?> - Asha Bank Bangladesh</title>
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
            --success: #3B6D11;
            --success-bg: #EAF3DE;
            --danger: #A32D2D;
            --danger-bg: #FCEBEB;
            --warning: #BA7517;
            --warning-bg: #FAEEDA;
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
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
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .logo { display: flex; align-items: center; gap: 12px; }
        .logo-icon { width: 36px; height: 36px; background: var(--accent-bg); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .navbar-right { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .nav-menu { display: flex; gap: 8px; align-items: center; flex-wrap: wrap; }
        .nav-menu a { text-decoration: none; color: var(--text-secondary); font-size: 13px; padding: 8px 12px; border-radius: 8px; transition: all 0.2s; }
        .nav-menu a:hover { background: var(--bg-secondary); color: var(--accent); }
        
        /* Notification Bell */
        .notification-container { position: relative; cursor: pointer; }
        .notification-bell { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 40px; padding: 8px 12px; display: flex; align-items: center; gap: 8px; }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: var(--danger); color: white; border-radius: 50%; padding: 2px 6px; font-size: 10px; }
        .notification-dropdown { position: absolute; top: 45px; right: 0; width: 380px; background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; box-shadow: var(--shadow-lg); display: none; z-index: 200; }
        .notification-dropdown.show { display: block; }
        .notification-header { padding: 12px 16px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; font-weight: 600; }
        .notification-item { padding: 12px 16px; border-bottom: 1px solid var(--border-color); cursor: pointer; transition: background 0.2s; }
        .notification-item:hover { background: var(--bg-secondary); }
        .notification-item.unread { background: var(--accent-bg); }
        .notification-title { font-weight: 600; font-size: 14px; margin-bottom: 4px; display: flex; align-items: center; gap: 8px; }
        .notification-message { font-size: 12px; color: var(--text-secondary); margin-bottom: 4px; }
        .notification-time { font-size: 10px; color: var(--text-tertiary); }
        
        /* Notification Modal */
        .notification-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .notification-modal-content {
            background: var(--bg-primary);
            border-radius: 24px;
            padding: 28px;
            max-width: 450px;
            width: 90%;
            position: relative;
            animation: modalSlideIn 0.3s ease;
        }
        @keyframes modalSlideIn {
            from { transform: translateY(-50px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }
        .notification-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }
        .notification-modal-header h3 { font-size: 18px; }
        .close-notification-modal { cursor: pointer; font-size: 24px; transition: transform 0.2s; }
        .close-notification-modal:hover { transform: scale(1.1); }
        .notification-modal-body { margin-bottom: 20px; }
        .notification-modal-body p { line-height: 1.6; color: var(--text-secondary); font-size: 14px; }
        .notification-type-icon { width: 48px; height: 48px; border-radius: 50%; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
        .notification-type-icon.success { background: var(--success-bg); color: var(--success); }
        .notification-type-icon.warning { background: var(--warning-bg); color: var(--warning); }
        .notification-type-icon.info { background: var(--accent-bg); color: var(--accent); }
        .notification-type-icon.danger { background: var(--danger-bg); color: var(--danger); }
        .notification-type-icon i { font-size: 24px; }
        .btn-close-modal { width: 100%; padding: 12px; background: var(--accent); color: white; border: none; border-radius: 40px; font-weight: 600; cursor: pointer; transition: all 0.2s; }
        .btn-close-modal:hover { background: var(--accent-dark); transform: translateY(-2px); }
        
        /* Container */
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        
        /* Balance Card */
        .balance-card {
            background: <?= $cardTier['bg'] ?>;
            border-radius: 24px;
            padding: 24px;
            color: <?= $cardTier['text'] ?>;
            margin-bottom: 24px;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            position: relative;
            overflow: hidden;
        }
        .balance-card:hover { transform: translateY(-4px); box-shadow: var(--shadow-lg); }
        .balance-card::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -20%;
            width: 200px;
            height: 200px;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            border-radius: 50%;
        }
        .balance-label { font-size: 12px; opacity: 0.8; margin-bottom: 8px; letter-spacing: 1px; }
        .balance-amount { font-size: 36px; font-weight: 700; margin-bottom: 8px; }
        .balance-acct { font-size: 12px; opacity: 0.7; }
        .card-tier-badge {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(0,0,0,0.3);
            backdrop-filter: blur(4px);
            padding: 6px 14px;
            border-radius: 30px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 1px;
            display: flex;
            align-items: center;
            gap: 6px;
        }
        
        /* Tier Progress */
        .tier-progress {
            margin-top: 16px;
            padding-top: 12px;
            border-top: 1px solid rgba(255,255,255,0.2);
        }
        .tier-progress-label {
            font-size: 10px;
            opacity: 0.7;
            margin-bottom: 6px;
            display: flex;
            justify-content: space-between;
        }
        .tier-progress-bar {
            height: 4px;
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
            overflow: hidden;
        }
        .tier-progress-fill {
            height: 100%;
            background: <?= $cardTier['accent'] ?>;
            border-radius: 4px;
            width: <?= $nextTier['percentage'] ?>%;
            transition: width 0.5s ease;
        }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; padding: 16px; transition: all 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-label { font-size: 11px; color: var(--text-tertiary); margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .stat-value { font-size: 20px; font-weight: 700; }
        
        /* Glass Card */
        .glass-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 20px; padding: 20px; margin-bottom: 24px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color); }
        
        /* Table */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { color: var(--text-tertiary); font-weight: 500; font-size: 12px; }
        
        /* Badges */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        
        /* Quick Actions */
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(100px, 1fr)); gap: 12px; margin-top: 16px; }
        .action-btn { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 40px; padding: 10px; text-align: center; text-decoration: none; color: var(--text-primary); font-size: 12px; font-weight: 500; transition: all 0.2s; }
        .action-btn:hover { background: var(--accent); color: white; border-color: var(--accent); }
        
        /* Theme Toggle */
        .theme-toggle { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 40px; padding: 8px 16px; cursor: pointer; font-size: 13px; transition: all 0.2s; }
        .theme-toggle:hover { background: var(--accent-bg); }
        
        /* Credit Card Modal */
        .card-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.8);
            backdrop-filter: blur(12px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .card-modal-content {
            animation: cardModalSlideIn 0.4s ease;
        }
        @keyframes cardModalSlideIn {
            from { transform: scale(0.9) translateY(30px); opacity: 0; }
            to { transform: scale(1) translateY(0); opacity: 1; }
        }
        .close-card-modal {
            position: absolute;
            top: -15px;
            right: -15px;
            width: 35px;
            height: 35px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 20px;
            box-shadow: var(--shadow-md);
            z-index: 10;
            transition: transform 0.2s;
        }
        .close-card-modal:hover { transform: scale(1.1); }
        
        /* Premium Credit Card Design */
        .premium-card {
            width: 450px;
            height: 280px;
            background: <?= $cardTier['bg'] ?>;
            border-radius: 24px;
            padding: 24px;
            position: relative;
            overflow: hidden;
            box-shadow: 0 25px 40px -12px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.1);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        .premium-card:hover { transform: translateY(-5px); box-shadow: 0 30px 50px -15px rgba(0,0,0,0.6); }
        
        .card-bg-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.08) 0%, transparent 50%),
                        radial-gradient(circle at 80% 70%, rgba(<?= $cardTier['accent'] == '#ffd700' ? '255,215,0' : '192,192,192' ?>,0.08) 0%, transparent 60%);
            pointer-events: none;
        }
        
        .card-chip {
            position: absolute;
            top: 24px;
            left: 24px;
        }
        
        .card-brand {
            position: absolute;
            top: 24px;
            right: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .card-tier-name {
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 2px;
            color: <?= $cardTier['accent'] ?>;
            text-transform: uppercase;
        }
        
        .card-number {
            position: absolute;
            bottom: 100px;
            left: 24px;
            right: 24px;
            font-family: 'Courier New', monospace;
            font-size: 22px;
            font-weight: 600;
            letter-spacing: 3px;
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        .card-details {
            position: absolute;
            bottom: 40px;
            left: 24px;
            right: 24px;
            display: flex;
            justify-content: space-between;
            color: white;
        }
        
        .card-detail {
            display: flex;
            flex-direction: column;
            gap: 4px;
        }
        
        .card-detail-label {
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.6;
        }
        
        .card-detail-value {
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        
        .card-cvv {
            position: absolute;
            bottom: 100px;
            right: 24px;
            background: rgba(0,0,0,0.4);
            backdrop-filter: blur(4px);
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 11px;
            font-family: monospace;
            color: white;
        }
        
        .card-network {
            position: absolute;
            bottom: 24px;
            right: 24px;
            font-size: 14px;
            font-weight: 700;
            letter-spacing: 1px;
            color: <?= $cardTier['accent'] ?>;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        /* Level indicator */
        .level-indicator {
            display: flex;
            gap: 8px;
            margin-top: 8px;
        }
        .level-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: rgba(255,255,255,0.3);
            transition: all 0.3s;
        }
        .level-dot.active {
            background: <?= $cardTier['accent'] ?>;
            box-shadow: 0 0 8px <?= $cardTier['accent'] ?>;
        }
        
        @media (max-width: 550px) {
            .premium-card { width: 95%; height: auto; min-height: 240px; }
            .card-number { font-size: 16px; bottom: 80px; }
            .card-details { bottom: 25px; }
            .notification-dropdown { width: 320px; right: -50px; }
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
            
            <!-- Notification Bell -->
            <div class="notification-container" onclick="toggleNotifications(event)">
                <div class="notification-bell">
                    <i class="fas fa-bell"></i>
                    <span>Notif</span>
                    <?php if($unreadCount > 0): ?>
                        <span class="notification-badge"><?= $unreadCount ?></span>
                    <?php endif; ?>
                </div>
                <div class="notification-dropdown" id="notificationDropdown">
                    <div class="notification-header">
                        <span>Notifications</span>
                        <?php if($unreadCount > 0): ?>
                            <a href="?mark_all_read=1" style="font-size: 11px; color: var(--accent);">Mark all read</a>
                        <?php endif; ?>
                    </div>
                    <div style="max-height: 400px; overflow-y: auto;">
                        <?php if(empty($notifList)): ?>
                            <div style="padding: 40px; text-align: center; color: var(--text-tertiary);">No notifications</div>
                        <?php else: ?>
                            <?php foreach($notifList as $notif): ?>
                                <div class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>" onclick="event.stopPropagation(); showNotificationModal(<?= htmlspecialchars(json_encode($notif)) ?>)">
                                    <div class="notification-title">
                                        <i class="fas <?= $notif['type'] == 'success' ? 'fa-check-circle' : ($notif['type'] == 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle') ?>"></i>
                                        <?= htmlspecialchars($notif['title']) ?>
                                    </div>
                                    <div class="notification-message"><?= htmlspecialchars(substr($notif['message'], 0, 60)) ?>...</div>
                                    <div class="notification-time"><?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <span><i class="fas fa-user"></i> <?= htmlspecialchars($account['FirstName']) ?></span>
            <button class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i> Dark</button>
            <a href="logout.php" style="background: var(--danger); color: white; padding: 8px 16px; border-radius: 40px;"><?= __('logout') ?></a>
        </div>
    </nav>
    
    <div class="container">
        <!-- Clickable Balance Card with Tier Badge -->
        <div class="balance-card" id="balanceCard">
            <div class="card-tier-badge">
                <?= $cardTier['badge'] ?> <?= $cardTier['name'] ?>
            </div>
            <div class="balance-label"><?= __('current_balance') ?></div>
            <div class="balance-amount"><?= formatBDT($balance) ?></div>
            <div class="balance-acct"><?= __('account_number') ?>: <?= $account['AccountNumber'] ?></div>
            
            <!-- Tier Progress Bar -->
            <?php if($nextTier['amount'] > 0): ?>
            <div class="tier-progress">
                <div class="tier-progress-label">
                    <span>Next tier: <?= $nextTier['name'] ?></span>
                    <span>Need <?= formatBDT($nextTier['amount']) ?> more</span>
                </div>
                <div class="tier-progress-bar">
                    <div class="tier-progress-fill"></div>
                </div>
            </div>
            <?php else: ?>
            <div class="tier-progress">
                <div class="tier-progress-label">
                    <span>🎉 Maximum Tier Achieved!</span>
                    <span>Black Edition Member</span>
                </div>
                <div class="tier-progress-bar">
                    <div class="tier-progress-fill" style="width: 100%;"></div>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="level-indicator">
                <div class="level-dot <?= $cardTier['level'] >= 1 ? 'active' : '' ?>"></div>
                <div class="level-dot <?= $cardTier['level'] >= 2 ? 'active' : '' ?>"></div>
                <div class="level-dot <?= $cardTier['level'] >= 3 ? 'active' : '' ?>"></div>
                <div class="level-dot <?= $cardTier['level'] >= 4 ? 'active' : '' ?>"></div>
                <div class="level-dot <?= $cardTier['level'] >= 5 ? 'active' : '' ?>"></div>
            </div>
            
            <div class="balance-acct" style="margin-top: 12px;"><i class="fas fa-credit-card"></i> Click to view your premium card</div>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label"><?= __('total_sent') ?></div>
                <div class="stat-value" style="color: var(--danger);"><?= formatBDT($totalSentAmount) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><?= __('total_received') ?></div>
                <div class="stat-value" style="color: var(--success);"><?= formatBDT($totalReceivedAmount) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><?= __('kyc_status') ?></div>
                <div class="stat-value" style="font-size: 16px;">
                    <span class="badge <?= ($kycStatus && $kycStatus['status'] == 'verified') ? 'badge-success' : 'badge-warning' ?>">
                        <?= $kycStatus ? ($kycStatus['status'] == 'verified' ? __('verified') : __('pending')) : __('pending') ?>
                    </span>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-label"><?= __('nominee') ?></div>
                <div class="stat-value" style="font-size: 16px;"><?= $nominee ? htmlspecialchars($nominee['NomineeName']) : __('not_added') ?></div>
            </div>
        </div>
        
        <div class="glass-card">
            <div class="card-header">
                <span><i class="fas fa-bolt"></i> <?= __('quick_actions') ?></span>
            </div>
            <div class="quick-actions">
                <a href="transfer.php" class="action-btn"><i class="fas fa-exchange-alt"></i> <?= __('transfer') ?></a>
                <a href="deposit.php" class="action-btn"><i class="fas fa-plus-circle"></i> <?= __('deposit') ?></a>
                <a href="withdraw.php" class="action-btn"><i class="fas fa-minus-circle"></i> <?= __('withdraw') ?></a>
                <a href="profile.php" class="action-btn"><i class="fas fa-user-edit"></i> <?= __('profile') ?></a>
            </div>
        </div>
        
        <div class="glass-card">
            <div class="card-header">
                <span><i class="fas fa-history"></i> <?= __('recent_transactions') ?></span>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th><?= __('date') ?></th><th><?= __('type') ?></th><th><?= __('amount') ?></th><th><?= __('status') ?></th></tr>
                    </thead>
                    <tbody>
                        <?php if(empty($transactions)): ?>
                            <tr><td colspan="4" style="text-align: center;"><?= __('no_transactions') ?></td></tr>
                        <?php else: ?>
                            <?php foreach($transactions as $txn): ?>
                                <tr>
                                    <td><?= date('d M Y', strtotime($txn['TransactionDate'])) ?></td>
                                    <td><?= $txn['TypeName'] ?></td>
                                    <td class="<?= ($txn['FromCustomerID'] == $userId) ? 'text-danger' : 'text-success' ?>" style="<?= ($txn['FromCustomerID'] == $userId) ? 'color: var(--danger);' : 'color: var(--success);' ?>">
                                        <?= ($txn['FromCustomerID'] == $userId) ? '-' : '+' ?> <?= formatBDT($txn['TransactionAmount']) ?>
                                    </td>
                                    <td><span class="badge badge-success"><?= __('completed') ?></span></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Notification Modal -->
    <div id="notificationModal" class="notification-modal">
        <div class="notification-modal-content">
            <div class="notification-modal-header">
                <h3 id="notifModalTitle"></h3>
                <span class="close-notification-modal" onclick="closeNotificationModal()">&times;</span>
            </div>
            <div class="notification-modal-body">
                <div id="notifModalIcon" class="notification-type-icon"></div>
                <p id="notifModalMessage"></p>
                <p id="notifModalTime" style="font-size: 11px; color: var(--text-tertiary); margin-top: 12px;"></p>
            </div>
            <button class="btn-close-modal" onclick="closeNotificationModal()">Close</button>
        </div>
    </div>
    
    <!-- Premium Credit Card Modal -->
  <div id="cardModal" class="card-modal">
    <div class="card-modal-content">
        <div class="close-card-modal" onclick="closeCardModal()">&times;</div>
        <div class="premium-card">
            <div class="card-bg-pattern"></div>
            <div class="card-chip">
                <svg width="45" height="35" viewBox="0 0 50 40">
                    <rect x="5" y="5" width="40" height="30" rx="3" fill="#D4AF37" stroke="#B8960C" stroke-width="1"/>
                    <rect x="10" y="10" width="30" height="20" rx="2" fill="#E8C84A"/>
                    <line x1="15" y1="18" x2="35" y2="18" stroke="#B8960C" stroke-width="1.5"/>
                    <line x1="20" y1="23" x2="30" y2="23" stroke="#B8960C" stroke-width="1.5"/>
                    <circle cx="25" cy="15" r="3" fill="#D4AF37"/>
                </svg>
            </div>
            <div class="card-brand">
                <div class="card-tier-name"><?= strtoupper($cardTier['name']) ?></div>
                <i class="fas fa-<?= $cardTier['icon'] ?>" style="color: <?= $cardTier['accent'] ?>; font-size: 20px;"></i>
            </div>
            <div class="card-number">
                <?php if($card): ?>
                    <?= substr($card['CardNumber'], 0, 4) ?> **** **** <?= substr($card['CardNumber'], -4) ?>
                <?php else: ?>
                    4532 **** **** 1048
                <?php endif; ?>
            </div>
            <div class="card-details">
                <div class="card-detail">
                    <span class="card-detail-label">CARD HOLDER NAME</span>
                    <span class="card-detail-value"><?= strtoupper(htmlspecialchars($account['FirstName'] . ' ' . $account['LastName'])) ?></span>
                </div>
                <div class="card-detail">
                    <span class="card-detail-label">EXPIRES</span>
                    <span class="card-detail-value"><?= $card ? date('m/y', strtotime($card['ExpiryDate'])) : '12/28' ?></span>
                </div>
                <div class="card-detail">
                    <span class="card-detail-label">CVV</span>
                    <span class="card-detail-value">***</span>
                </div>
            </div>
            <div class="card-network">
                <i class="fab fa-cc-visa" style="font-size: 28px;"></i>
            </div>
        </div>
    </div>
</div>

<style>
/* Updated Card CSS - Fixed positioning */
.premium-card {
    width: 450px;
    height: 280px;
    background: <?= $cardTier['bg'] ?>;
    border-radius: 24px;
    padding: 24px;
    position: relative;
    overflow: hidden;
    box-shadow: 0 25px 40px -12px rgba(0,0,0,0.5), inset 0 1px 0 rgba(255,255,255,0.1);
    transition: transform 0.3s, box-shadow 0.3s;
}
.premium-card:hover { transform: translateY(-5px); }

.card-bg-pattern {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: radial-gradient(circle at 20% 30%, rgba(255,255,255,0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(255,215,0,0.06) 0%, transparent 60%);
    pointer-events: none;
}

.card-chip {
    position: absolute;
    top: 24px;
    left: 24px;
}

.card-brand {
    position: absolute;
    top: 24px;
    right: 24px;
    display: flex;
    align-items: center;
    gap: 10px;
}

.card-tier-name {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 2px;
    color: <?= $cardTier['accent'] ?>;
    text-transform: uppercase;
}

.card-number {
    position: absolute;
    bottom: 110px;
    left: 24px;
    right: 24px;
    font-family: 'Courier New', monospace;
    font-size: 22px;
    font-weight: 600;
    letter-spacing: 3px;
    color: white;
    text-shadow: 0 1px 2px rgba(0,0,0,0.3);
}

.card-details {
    position: absolute;
    bottom: 35px;
    left: 24px;
    right: 24px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    color: white;
}

.card-detail {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.card-detail-label {
    font-size: 8px;
    text-transform: uppercase;
    letter-spacing: 1px;
    opacity: 0.6;
}

.card-detail-value {
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.5px;
}

.card-network {
    position: absolute;
    bottom: 35px;
    right: 24px;
}

@media (max-width: 550px) {
    .premium-card { width: 95%; height: auto; min-height: 250px; }
    .card-number { font-size: 14px; bottom: 90px; letter-spacing: 1px; }
    .card-details { bottom: 25px; flex-wrap: wrap; gap: 10px; }
    .card-detail { min-width: 80px; }
}
</style>



    <div id="toastContainer">
        <?php $toast = getToast(); if($toast): ?>
            <div style="position: fixed; bottom: 20px; right: 20px; background: var(--bg-primary); border-left: 4px solid var(--success); padding: 12px 20px; border-radius: 12px; box-shadow: var(--shadow-lg); z-index: 1000;">
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
        
        // Notification Modal Functions
        let currentNotificationId = null;
        
        function showNotificationModal(notification) {
            const modal = document.getElementById('notificationModal');
            const title = document.getElementById('notifModalTitle');
            const message = document.getElementById('notifModalMessage');
            const time = document.getElementById('notifModalTime');
            const iconDiv = document.getElementById('notifModalIcon');
            
            title.innerHTML = notification.title;
            message.innerHTML = notification.message;
            time.innerHTML = notification.created_at;
            
            iconDiv.className = 'notification-type-icon ' + notification.type;
            if(notification.type === 'success') iconDiv.innerHTML = '<i class="fas fa-check-circle"></i>';
            else if(notification.type === 'warning') iconDiv.innerHTML = '<i class="fas fa-exclamation-triangle"></i>';
            else if(notification.type === 'danger') iconDiv.innerHTML = '<i class="fas fa-times-circle"></i>';
            else iconDiv.innerHTML = '<i class="fas fa-info-circle"></i>';
            
            modal.style.display = 'flex';
            currentNotificationId = notification.notification_id;
            
            // Mark as read via AJAX if unread
            if(!notification.is_read) {
                fetch(`?ajax_mark_read=1&id=${notification.notification_id}`)
                    .then(response => response.json())
                    .then(data => {
                        if(data.success) {
                            const items = document.querySelectorAll('.notification-item');
                            items.forEach(item => {
                                if(item.innerHTML.includes(notification.title.substring(0, 20))) {
                                    item.classList.remove('unread');
                                }
                            });
                            const badge = document.querySelector('.notification-badge');
                            if(badge) {
                                let count = parseInt(badge.textContent) - 1;
                                if(count > 0) badge.textContent = count;
                                else badge.remove();
                            }
                        }
                    });
            }
        }
        
        function closeNotificationModal() {
            document.getElementById('notificationModal').style.display = 'none';
            currentNotificationId = null;
        }
        
        function toggleNotifications(event) {
            event.stopPropagation();
            const dropdown = document.getElementById('notificationDropdown');
            dropdown.classList.toggle('show');
        }
        
        document.addEventListener('click', function() {
            const dropdown = document.getElementById('notificationDropdown');
            if(dropdown) dropdown.classList.remove('show');
        });
        
        // Card Modal
        const balanceCard = document.getElementById('balanceCard');
        const cardModal = document.getElementById('cardModal');
        
        if(balanceCard) {
            balanceCard.addEventListener('click', () => {
                cardModal.style.display = 'flex';
            });
        }
        
        function closeCardModal() {
            cardModal.style.display = 'none';
        }
        
        document.addEventListener('keydown', (e) => {
            if(e.key === 'Escape') {
                closeNotificationModal();
                closeCardModal();
            }
        });
        
        window.addEventListener('click', (e) => {
            if(e.target === cardModal) closeCardModal();
            if(e.target === document.getElementById('notificationModal')) closeNotificationModal();
        });
    </script>
</body>
</html>