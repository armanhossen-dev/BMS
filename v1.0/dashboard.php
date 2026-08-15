<?php
require_once 'config/db.php';
require_once 'config/language.php';

if(!isLoggedIn() || !isClient()) {
    redirect('login.php');
}

$userId = $_SESSION['user_id'];

// Check if account is active
$statusMessage = getAccountStatusMessage($pdo, $userId);
if ($statusMessage) {
    $accountActive = false;
    setToast($statusMessage, 'warning');
} else {
    $accountActive = true;
}

// Get user account
$stmt = $pdo->prepare("SELECT a.*, c.FirstName, c.LastName, c.Email, c.Phone, c.Address, c.DateOfBirth, c.IsActive 
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

// Determine card tier
function getCardTier($balance) {
    $balanceNum = floatval($balance);
    if($balanceNum >= 1000000) {
        return [
            'name' => 'Black Edition', 'level' => 5, 'stars' => 5,
            'bg' => 'linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 40%, #2a2a2a 100%)',
            'accent' => '#d4af37', 'text' => '#ffffff', 'icon' => 'crown', 'badge' => '👑',
            'card_bg' => 'linear-gradient(145deg, #1a1a1a 0%, #2d2d2d 50%, #1a1a1a 100%)'
        ];
    } elseif($balanceNum >= 500000) {
        return [
            'name' => 'Platinum', 'level' => 4, 'stars' => 4,
            'bg' => 'linear-gradient(135deg, #1a2a3a 0%, #2a3a4a 50%, #3a4a5a 100%)',
            'accent' => '#c0c0c0', 'text' => '#ffffff', 'icon' => 'gem', 'badge' => '💎',
            'card_bg' => 'linear-gradient(145deg, #2a3a4a 0%, #3a4a5a 50%, #2a3a4a 100%)'
        ];
    } elseif($balanceNum >= 100000) {
        return [
            'name' => 'Gold', 'level' => 3, 'stars' => 3,
            'bg' => 'linear-gradient(135deg, #2a1a0a 0%, #3a2a1a 50%, #4a3a2a 100%)',
            'accent' => '#ffd700', 'text' => '#ffffff', 'icon' => 'star', 'badge' => '⭐',
            'card_bg' => 'linear-gradient(145deg, #3a2a1a 0%, #4a3a2a 50%, #3a2a1a 100%)'
        ];
    } elseif($balanceNum >= 10000) {
        return [
            'name' => 'Silver', 'level' => 2, 'stars' => 2,
            'bg' => 'linear-gradient(135deg, #1a2a2a 0%, #2a3a3a 50%, #3a4a4a 100%)',
            'accent' => '#c0c0c0', 'text' => '#ffffff', 'icon' => 'shield', 'badge' => '🛡️',
            'card_bg' => 'linear-gradient(145deg, #2a3a3a 0%, #3a4a4a 50%, #2a3a3a 100%)'
        ];
    } else {
        return [
            'name' => 'Classic', 'level' => 1, 'stars' => 1,
            'bg' => 'linear-gradient(135deg, #185FA5 0%, #0C447C 50%, #0a3a6a 100%)',
            'accent' => '#ffd700', 'text' => '#ffffff', 'icon' => 'credit-card', 'badge' => '💳',
            'card_bg' => 'linear-gradient(145deg, #185FA5 0%, #0C447C 50%, #185FA5 100%)'
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
        return ['name' => 'Silver', 'amount' => 10000 - $balance, 'percentage' => ($balance / 10000) * 100, 'stars' => 2];
    } elseif($balance < 100000) {
        return ['name' => 'Gold', 'amount' => 100000 - $balance, 'percentage' => ($balance / 100000) * 100, 'stars' => 3];
    } elseif($balance < 500000) {
        return ['name' => 'Platinum', 'amount' => 500000 - $balance, 'percentage' => ($balance / 500000) * 100, 'stars' => 4];
    } elseif($balance < 1000000) {
        return ['name' => 'Black Edition', 'amount' => 1000000 - $balance, 'percentage' => ($balance / 1000000) * 100, 'stars' => 5];
    } else {
        return ['name' => 'Black Edition', 'amount' => 0, 'percentage' => 100, 'stars' => 5];
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
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0,0,0,0.1);
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
        .notification-container {
            position: relative;
            cursor: pointer;
        }
        .notification-bell {
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 40px;
            padding: 8px 12px;
            display: flex;
            align-items: center;
            gap: 8px;
            transition: all 0.2s;
        }
        .notification-bell:hover {
            background: var(--accent-bg);
        }
        .notification-badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: bold;
            min-width: 18px;
            text-align: center;
        }
        .notification-dropdown {
            position: absolute;
            top: 45px;
            right: 0;
            width: 380px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            box-shadow: 0 10px 25px -5px rgba(0,0,0,0.2);
            display: none;
            z-index: 1000;
            max-height: 450px;
            overflow-y: auto;
        }
        .notification-dropdown.show {
            display: block;
        }
        .notification-header {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 600;
            position: sticky;
            top: 0;
            background: var(--bg-primary);
            z-index: 1;
        }
        .notification-header span {
            font-size: 14px;
        }
        .notification-header a {
            font-size: 11px;
            color: var(--accent);
            text-decoration: none;
        }
        .notification-item {
            padding: 14px 16px;
            border-bottom: 1px solid var(--border-color);
            cursor: pointer;
            transition: background 0.2s;
        }
        .notification-item:hover {
            background: var(--bg-secondary);
        }
        .notification-item.unread {
            background: var(--accent-bg);
        }
        .notification-title {
            font-weight: 600;
            font-size: 13px;
            margin-bottom: 5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .notification-title i {
            font-size: 12px;
        }
        .notification-message {
            font-size: 12px;
            color: var(--text-secondary);
            margin-bottom: 6px;
            line-height: 1.4;
        }
        .notification-time {
            font-size: 10px;
            color: var(--text-tertiary);
        }
        
        /* Notification Modal */
        .notification-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(5px);
            z-index: 1001;
            justify-content: center;
            align-items: center;
        }
        .notification-modal-content {
            background: var(--bg-primary);
            border-radius: 20px;
            padding: 24px;
            max-width: 450px;
            width: 90%;
            animation: modalFadeIn 0.3s ease;
        }
        @keyframes modalFadeIn {
            from { opacity: 0; transform: scale(0.95); }
            to { opacity: 1; transform: scale(1); }
        }
        .notification-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }
        .notification-modal-header h3 {
            font-size: 18px;
            font-weight: 600;
            margin: 0;
        }
        .close-notification-modal {
            cursor: pointer;
            font-size: 24px;
            transition: transform 0.2s;
        }
        .close-notification-modal:hover {
            transform: scale(1.1);
        }
        .notification-modal-body {
            margin-bottom: 20px;
        }
        .notification-modal-body p {
            line-height: 1.6;
            color: var(--text-secondary);
            font-size: 14px;
            margin: 0;
        }
        .notification-type-icon {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 16px;
        }
        .notification-type-icon.success {
            background: var(--success-bg);
            color: var(--success);
        }
        .notification-type-icon.warning {
            background: var(--warning-bg);
            color: var(--warning);
        }
        .notification-type-icon.info {
            background: var(--accent-bg);
            color: var(--accent);
        }
        .notification-type-icon.danger {
            background: var(--danger-bg);
            color: var(--danger);
        }
        .notification-type-icon i {
            font-size: 24px;
        }
        .notification-modal-time {
            font-size: 11px;
            color: var(--text-tertiary);
            margin-top: 12px;
        }
        .btn-close-modal {
            width: 100%;
            padding: 12px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .btn-close-modal:hover {
            background: var(--accent-dark);
            transform: translateY(-2px);
        }
        
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
        
        /* Star Rating */
        .star-rating { display: flex; gap: 8px; margin: 12px 0; }
        .star { font-size: 20px; }
        .star.filled { color: <?= $cardTier['accent'] ?>; text-shadow: 0 0 5px <?= $cardTier['accent'] ?>; }
        .star.empty { color: rgba(255,255,255,0.3); }
        
        /* Tier Progress */
        .tier-progress { margin-top: 16px; padding-top: 12px; border-top: 1px solid rgba(255,255,255,0.2); }
        .tier-progress-label { font-size: 10px; opacity: 0.7; margin-bottom: 6px; display: flex; justify-content: space-between; }
        .tier-progress-bar { height: 4px; background: rgba(255,255,255,0.2); border-radius: 4px; overflow: hidden; }
        .tier-progress-fill { height: 100%; background: <?= $cardTier['accent'] ?>; border-radius: 4px; width: <?= $nextTier['percentage'] ?>%; transition: width 0.5s ease; }
        .next-tier-stars { display: flex; gap: 4px; margin-top: 8px; font-size: 11px; align-items: center; }
        .next-tier-stars span { opacity: 0.6; margin-right: 6px; }
        .next-star { font-size: 11px; color: rgba(255,255,255,0.3); }
        .next-star.filled { color: <?= $cardTier['accent'] ?>; }
        
        /* Stats Grid */
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; padding: 16px; transition: all 0.2s; }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-label { font-size: 11px; color: var(--text-tertiary); margin-bottom: 8px; display: flex; align-items: center; gap: 6px; }
        .stat-value { font-size: 20px; font-weight: 700; }
        
        /* Glass Card */
        .glass-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
            width: 100%;
        }
        .card-header {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }
        .card-header span {
            font-size: 18px;
            font-weight: 600;
            text-align: center;
        }
        .card-header span i {
            margin-right: 8px;
            color: var(--accent);
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 16px;
            margin-top: 16px;
            width: 100%;
        }
        .action-btn {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 14px 16px;
            text-align: center;
            text-decoration: none;
            color: var(--text-primary);
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s ease;
            cursor: pointer;
            width: 100%;
            min-width: 0;
        }
        .action-btn i { font-size: 18px; transition: transform 0.2s ease; }
        .action-btn:hover { background: var(--accent); color: white; border-color: var(--accent); transform: translateY(-3px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .action-btn:hover i { transform: scale(1.1); }
        .action-btn.disabled { opacity: 0.6; cursor: not-allowed; background: var(--danger-bg); color: var(--danger); border-color: var(--danger); }
        .action-btn.disabled:hover { transform: none; box-shadow: none; background: var(--danger-bg); color: var(--danger); }
        .action-btn.contact-support { background: var(--warning-bg); border-color: var(--warning); color: var(--warning); }
        .action-btn.contact-support:hover { background: var(--warning); color: white; }
        
        /* Deactivated Alert */
        .deactivated-alert {
            background: var(--danger-bg);
            border: 1px solid var(--danger);
            border-radius: 16px;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 16px;
        }
        .deactivated-alert-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .deactivated-alert-content i {
            font-size: 24px;
            color: var(--danger);
        }
        .deactivated-alert-content p {
            color: var(--danger);
            font-weight: 500;
        }
        
        /* Table */
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { color: var(--text-tertiary); font-weight: 500; font-size: 12px; }
        
        /* Badges */
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-success { background: var(--success-bg); color: var(--success); }
        .badge-warning { background: var(--warning-bg); color: var(--warning); }
        
        /* Theme Toggle */
        .theme-toggle { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 40px; padding: 8px 16px; cursor: pointer; font-size: 13px; transition: all 0.2s; }
        .theme-toggle:hover { background: var(--accent-bg); }
        
        /* Premium Card Modal */
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
        .card-modal-content { animation: cardModalSlideIn 0.4s ease; }
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
            font-weight: bold;
        }
        .close-card-modal:hover { transform: scale(1.1); }
        
        /* Message Bubble */
        .message-bubble {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 60px;
            height: 60px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            transition: all 0.3s ease;
            z-index: 999;
            color: white;
        }
        .message-bubble:hover { transform: scale(1.1); background: var(--accent-dark); }
        .message-bubble i { font-size: 24px; }
        .message-bubble .badge {
            position: absolute;
            top: -5px;
            right: -5px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 4px 8px;
            font-size: 10px;
            font-weight: bold;
        }
        
        /* Feedback Modal */
        .feedback-modal {
            display: none;
            position: fixed;
            bottom: 100px;
            right: 30px;
            width: 380px;
            background: var(--bg-primary);
            border-radius: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.2);
            z-index: 1000;
            overflow: hidden;
            animation: slideUp 0.3s ease;
            border: 1px solid var(--border-color);
        }
        .feedback-modal.show { display: block; }
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .feedback-header {
            background: var(--accent);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .feedback-header h3 { font-size: 16px; margin: 0; }
        .close-feedback { cursor: pointer; font-size: 20px; transition: transform 0.2s; }
        .close-feedback:hover { transform: scale(1.1); }
        .feedback-body { padding: 20px; }
        .feedback-body textarea {
            width: 100%;
            height: 120px;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-family: inherit;
            resize: none;
            margin-bottom: 12px;
        }
        .feedback-body select, .feedback-body input {
            width: 100%;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            margin-bottom: 12px;
        }
        .char-counter { font-size: 11px; color: var(--text-tertiary); text-align: right; margin-bottom: 12px; }
        .send-feedback {
            width: 100%;
            padding: 12px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .send-feedback:hover { background: var(--accent-dark); transform: translateY(-2px); }
        .feedback-footer {
            padding: 12px 20px;
            border-top: 1px solid var(--border-color);
            font-size: 11px;
            color: var(--text-tertiary);
            text-align: center;
        }
        
        .my-feedback-link { text-align: center; margin-top: 10px; }
        .my-feedback-link a { color: var(--accent); text-decoration: none; font-size: 12px; }
        .feedback-history { max-height: 250px; overflow-y: auto; }
        .feedback-item {
            padding: 10px;
            border-bottom: 1px solid var(--border-color);
        }
        .feedback-item .subject { font-weight: 600; font-size: 12px; }
        .feedback-item .message { font-size: 11px; color: var(--text-secondary); margin-top: 4px; }
        .feedback-item .reply { background: var(--accent-bg); padding: 6px; border-radius: 8px; margin-top: 6px; font-size: 10px; }
        .feedback-item .status { display: inline-block; padding: 2px 6px; border-radius: 20px; font-size: 9px; margin-top: 4px; }
        .status-pending { background: var(--warning-bg); color: var(--warning); }
        .status-replied { background: var(--success-bg); color: var(--success); }
        .status-resolved { background: var(--accent-bg); color: var(--accent); }
        
        /* Reactivation Modal */
        .reactivation-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.6);
            backdrop-filter: blur(8px);
            z-index: 1001;
            justify-content: center;
            align-items: center;
        }
        .reactivation-modal-content {
            background: var(--bg-primary);
            border-radius: 24px;
            padding: 28px;
            max-width: 500px;
            width: 90%;
            animation: modalSlideIn 0.3s ease;
        }
        .reactivation-modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }
        .reactivation-modal-header h3 { font-size: 20px; font-weight: 600; }
        .close-reactivation { cursor: pointer; font-size: 24px; transition: transform 0.2s; }
        .close-reactivation:hover { transform: scale(1.1); }
        .reactivation-modal-body textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            background: var(--bg-secondary);
            color: var(--text-primary);
            font-family: inherit;
            resize: vertical;
            margin: 12px 0;
        }
        .char-counter-reactivation { font-size: 11px; color: var(--text-tertiary); text-align: right; margin-bottom: 12px; }
        .send-reactivation {
            width: 100%;
            padding: 14px;
            background: var(--accent);
            color: white;
            border: none;
            border-radius: 40px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s;
        }
        .send-reactivation:hover { background: var(--accent-dark); transform: translateY(-2px); }
        .reactivation-modal-footer {
            padding: 12px 20px;
            border-top: 1px solid var(--border-color);
            font-size: 11px;
            color: var(--text-tertiary);
            text-align: center;
            margin-top: 16px;
        }
        .request-status-badge { display: inline-block; padding: 4px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .status-pending { background: var(--warning-bg); color: var(--warning); }
        .status-approved { background: var(--success-bg); color: var(--success); }
        .status-rejected { background: var(--danger-bg); color: var(--danger); }
        
        /* Responsive */
        @media (max-width: 768px) {
            .quick-actions { grid-template-columns: repeat(2, 1fr); }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .balance-amount { font-size: 28px; }
            .notification-dropdown { width: 320px; right: -60px; }
            .container { padding: 16px; }
            .glass-card { padding: 18px; }
        }
        @media (max-width: 550px) {
            .premium-card { width: 340px; height: 210px; padding: 20px; }
            .card-number { font-size: 16px; top: 100px; left: 20px; letter-spacing: 2px; }
            .card-chip { top: 20px; left: 20px; }
            .card-chip svg { width: 35px; height: 28px; }
            .card-detail-value { font-size: 10px; }
            .notification-dropdown { width: 300px; right: -40px; }
        }
        @media (max-width: 480px) {
            .quick-actions { grid-template-columns: 1fr; }
            .container { padding: 12px; }
            .glass-card { padding: 16px; border-radius: 16px; }
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
                <div style="max-height: 350px; overflow-y: auto;">
                    <?php if(empty($notifList)): ?>
                        <div style="padding: 40px; text-align: center; color: var(--text-tertiary);">
                            <i class="fas fa-bell-slash" style="font-size: 24px; margin-bottom: 10px; display: block;"></i>
                            No notifications
                        </div>
                    <?php else: ?>
                        <?php foreach($notifList as $notif): ?>
                            <div class="notification-item <?= $notif['is_read'] ? '' : 'unread' ?>" onclick="event.stopPropagation(); showNotificationModal(<?= htmlspecialchars(json_encode($notif)) ?>)">
                                <div class="notification-title">
                                    <i class="fas <?= $notif['type'] == 'success' ? 'fa-check-circle' : ($notif['type'] == 'warning' ? 'fa-exclamation-triangle' : 'fa-info-circle') ?>"></i>
                                    <?= htmlspecialchars($notif['title']) ?>
                                </div>
                                <div class="notification-message"><?= htmlspecialchars(substr($notif['message'], 0, 80)) ?>...</div>
                                <div class="notification-time">
                                    <i class="far fa-clock"></i> <?= date('d M Y, h:i A', strtotime($notif['created_at'])) ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <span><i class="fas fa-user"></i> <?= htmlspecialchars($account['FirstName']) ?></span>
        <button class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i> Dark</button>
        <a href="logout.php" style="background: var(--danger); color: white; padding: 8px 16px; border-radius: 40px; text-decoration: none;"><?= __('logout') ?></a>
    </div>
</nav>

<div class="container">
    
    <?php if(!$accountActive): ?>
    <div class="deactivated-alert">
        <div class="deactivated-alert-content">
            <i class="fas fa-exclamation-triangle"></i>
            <p><strong>Account Deactivated</strong> - Your account has been deactivated. Please use the button below to request reactivation.</p>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Quick Actions Section -->
    <div class="glass-card">
        <div class="card-header">
            <span><i class="fas fa-bolt"></i> <?= __('quick_actions') ?></span>
        </div>
        <div class="quick-actions">
            <?php if($accountActive): ?>
                <a href="transfer.php" class="action-btn"><i class="fas fa-exchange-alt"></i> <span><?= __('transfer') ?></span></a>
                <a href="deposit.php" class="action-btn"><i class="fas fa-plus-circle"></i> <span><?= __('deposit') ?></span></a>
                <a href="withdraw.php" class="action-btn"><i class="fas fa-minus-circle"></i> <span><?= __('withdraw') ?></span></a>
                <a href="profile.php" class="action-btn"><i class="fas fa-user-edit"></i> <span><?= __('profile') ?></span></a>
            <?php else: ?>
                <div class="action-btn disabled"><i class="fas fa-lock"></i> <span>Account Deactivated</span></div>
                <button class="action-btn contact-support" onclick="openReactivationModal()"><i class="fas fa-headset"></i> <span>Request Reactivation</span></button>
                <div class="action-btn disabled" style="visibility: hidden;"></div>
                <div class="action-btn disabled" style="visibility: hidden;"></div>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Reactivation Request Modal -->
    <div id="reactivationModal" class="reactivation-modal">
        <div class="reactivation-modal-content">
            <div class="reactivation-modal-header">
                <h3><i class="fas fa-sync-alt"></i> Request Account Reactivation</h3>
                <span class="close-reactivation" onclick="closeReactivationModal()">&times;</span>
            </div>
            <div class="reactivation-modal-body">
                <p>Your account has been deactivated. Please provide a reason for reactivation request.</p>
                <textarea id="reactivationReason" rows="4" placeholder="Explain why your account should be reactivated..."></textarea>
                <div class="char-counter-reactivation"><span id="reactivationCharCount">0</span>/500 characters</div>
                <button class="send-reactivation" onclick="sendReactivationRequest()">Submit Request <i class="fas fa-paper-plane"></i></button>
                <div id="existingRequestStatus" style="display: none; margin-top: 20px; padding: 15px; background: var(--accent-bg); border-radius: 12px;">
                    <h4><i class="fas fa-clock"></i> Your Request Status</h4>
                    <div id="requestStatusInfo"></div>
                </div>
            </div>
            <div class="reactivation-modal-footer">Our team will review your request within 24-48 hours</div>
        </div>
    </div>





    <!-- Balance Card -->
    <div class="balance-card" id="balanceCard">
        <div class="card-tier-badge"><?= $cardTier['badge'] ?> <?= $cardTier['name'] ?></div>
        <div class="balance-label"><?= __('current_balance') ?></div>
        <div class="balance-amount"><?= formatBDT($balance) ?></div>
        <div class="balance-acct"><?= __('account_number') ?>: <?= $account['AccountNumber'] ?></div>
        
        <div class="star-rating">
            <?php for($i = 1; $i <= 5; $i++): ?>
                <?php if($i <= $cardTier['stars']): ?>
                    <i class="fas fa-star star filled"></i>
                <?php else: ?>
                    <i class="far fa-star star empty"></i>
                <?php endif; ?>
            <?php endfor; ?>
        </div>
        
        <?php if($nextTier['amount'] > 0): ?>
        <div class="tier-progress">
            <div class="tier-progress-label">
                <span>Next tier: <?= $nextTier['name'] ?> <i class="fas fa-arrow-right"></i></span>
                <span>Need <?= formatBDT($nextTier['amount']) ?> more</span>
            </div>
            <div class="tier-progress-bar"><div class="tier-progress-fill"></div></div>
            <div class="next-tier-stars">
                <span>Next tier rewards:</span>
                <?php for($i = 1; $i <= 5; $i++): ?>
                    <?php if($i <= $nextTier['stars']): ?>
                        <i class="fas fa-star next-star filled" style="font-size: 10px;"></i>
                    <?php else: ?>
                        <i class="far fa-star next-star" style="font-size: 10px;"></i>
                    <?php endif; ?>
                <?php endfor; ?>
            </div>
        </div>
        <?php else: ?>
        <div class="tier-progress">
            <div class="tier-progress-label"><span>🏆 Maximum Tier Achieved! 🏆</span><span>Black Edition Member</span></div>
            <div class="tier-progress-bar"><div class="tier-progress-fill" style="width: 100%;"></div></div>
            <div class="next-tier-stars">
                <span>All rewards unlocked:</span>
                <?php for($i = 1; $i <= 5; $i++): ?>
                    <i class="fas fa-star next-star filled" style="color: <?= $cardTier['accent'] ?>; font-size: 10px;"></i>
                <?php endfor; ?>
                <span style="margin-left: 8px;">✨ Premium Benefits Active ✨</span>
            </div>
        </div>
        <?php endif; ?>
        <div class="balance-acct" style="margin-top: 12px;"><i class="fas fa-credit-card"></i> Click to view your premium card</div>
    </div>
    
    <div class="stats-grid">
        <div class="stat-card"><div class="stat-label"><?= __('total_sent') ?></div><div class="stat-value" style="color: var(--danger);"><?= formatBDT($totalSentAmount) ?></div></div>
        <div class="stat-card"><div class="stat-label"><?= __('total_received') ?></div><div class="stat-value" style="color: var(--success);"><?= formatBDT($totalReceivedAmount) ?></div></div>
        <div class="stat-card"><div class="stat-label"><?= __('kyc_status') ?></div><div class="stat-value" style="font-size: 16px;"><span class="badge <?= ($kycStatus && $kycStatus['status'] == 'verified') ? 'badge-success' : 'badge-warning' ?>"><?= $kycStatus ? ($kycStatus['status'] == 'verified' ? __('verified') : __('pending')) : __('pending') ?></span></div></div>
        <div class="stat-card"><div class="stat-label"><?= __('nominee') ?></div><div class="stat-value" style="font-size: 16px;"><?= $nominee ? htmlspecialchars($nominee['NomineeName']) : __('not_added') ?></div></div>
    </div>
    
    <?php if($accountActive): ?>
    <div class="glass-card">
        <div class="card-header"><span><i class="fas fa-history"></i> <?= __('recent_transactions') ?></span></div>
        <div class="table-container">
            <table>
                <thead><tr><th><?= __('date') ?></th><th><?= __('type') ?></th><th><?= __('amount') ?></th><th><?= __('status') ?></th></tr></thead>
                <tbody>
                    <?php if(empty($transactions)): ?>
                        <tr><td colspan="4" style="text-align: center;"><?= __('no_transactions') ?></td></tr>
                    <?php else: ?>
                        <?php foreach($transactions as $txn): ?>
                        <tr>
                            <td><?= date('d M Y', strtotime($txn['TransactionDate'])) ?></td>
                            <td><?= $txn['TypeName'] ?></td>
                            <td class="<?= ($txn['FromCustomerID'] == $userId) ? 'text-danger' : 'text-success' ?>" style="<?= ($txn['FromCustomerID'] == $userId) ? 'color: var(--danger);' : 'color: var(--success);' ?>"><?= ($txn['FromCustomerID'] == $userId) ? '-' : '+' ?> <?= formatBDT($txn['TransactionAmount']) ?></td>
                            <td><span class="badge badge-success"><?= __('completed') ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>
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
                <div class="card-tier-name"><?= strtoupper($cardTier['name']) ?>
            </div>
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
                    <!-- <span class="card-detail-label">CVV</span>
                    <span class="card-detail-value">***</span> -->
                </div>
            </div>
            <div class="card-network">
                <i class="fab fa-cc-visa" style="font-size: 40px;"></i>
            </div>
        </div>
    </div>
</div>

<style>
/* Premium Realistic Credit Card Styling */
.premium-card {
    width: 450px;
    height: 280px;
    background: <?= $cardTier['bg'] ?>;
    border-radius: 20px;
    padding: 30px;
    position: relative;
    overflow: hidden;
    
    /* Layered shadows for depth: 1. Soft drop shadow, 2. Inner highlight for edge thickness */
    box-shadow: 
        0 30px 60px -12px rgba(0,0,0,0.6), 
        inset 0 1px 1px rgba(255,255,255,0.2),
        inset 0 -1px 1px rgba(0,0,0,0.3);
    
    color: white;
    transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
    user-select: none;
}

.premium-card:hover {
    transform: translateY(-10px) rotateX(5deg);
    box-shadow: 0 40px 70px -15px rgba(0,0,0,0.7);
}

/* Texture Overlay */
.card-bg-pattern {
    position: absolute;
    top: 0; left: 0; right: 0; bottom: 0;
    /* Subtle noise and light reflection */
    background: 
        radial-gradient(circle at 10% 10%, rgba(255,255,255,0.1) 0%, transparent 40%),
        linear-gradient(135deg, rgba(255,255,255,0.05) 0%, rgba(0,0,0,0.05) 100%);
    opacity: 0.8;
    pointer-events: none;
}

/* Chip Styling - Realistic Gold/Silver Mix */
.card-chip {
    position: absolute;
    top: 60px;
    left: 40px;
    width: 55px;
    height: 40px;
    background: linear-gradient(135deg, #f0d07a 0%, #b8960c 100%);
    border-radius: 8px;
    box-shadow: inset 0 0 5px rgba(0,0,0,0.2);
    overflow: hidden;
}

/* Holographic/Metallic Tier Label */
.card-tier-name {
    font-family: 'Inter', sans-serif;
    font-size: 13px;
    font-weight: 800;
    letter-spacing: 4px;
    color: <?= $cardTier['accent'] ?>;
    text-shadow: 0 1px 1px rgba(0,0,0,0.4);
    opacity: 0.9;
}

/* The "Embossed" Number Look */
.card-number {
    position: absolute;
    top: 145px;
    left: 40px;
    width: 100%;
    font-family: 'OCR A Std', 'Courier New', monospace; /* Use OCR A for realism if available */
    font-size: 26px;
    letter-spacing: 3.5px;
    word-spacing: 8px;
    color: #eee;
    /* This creates the 'embossed' shadow effect */
    text-shadow: 
        -1px -1px 1px rgba(255,255,255,0.2),
        1px 1px 1px rgba(0,0,0,0.5);
}

/* Details Section */
.card-details {
    position: absolute;
    bottom: 30px;
    left: 40px;
    right: 40px;
    display: flex;
    align-items: flex-end;
}

.card-detail-label {
    display: block;
    font-size: 9px;
    font-family: 'Arial', sans-serif;
    font-weight: 600;
    letter-spacing: 1.5px;
    margin-bottom: 4px;
    opacity: 0.7;
    text-shadow: 0 1px 1px rgba(0,0,0,0.2);
}

.card-detail-value {
    font-family: 'Courier New', monospace;
    font-size: 16px;
    font-weight: bold;
    letter-spacing: 1px;
    text-transform: uppercase;
}

.card-network {
    position: absolute;
    bottom: 30px;
    right: 40px;
    filter: drop-shadow(0 1px 2px rgba(0,0,0,0.3));
}

/* Responsive Scaling */
@media (max-width: 550px) {
    .premium-card {
        width: 340px;
        height: 210px;
        padding: 20px;
    }
    .card-number {
        font-size: 18px;
        top: 110px;
        left: 25px;
    }
    .card-chip {
        width: 45px;
        height: 32px;
        top: 45px;
        left: 25px;
    }
    .card-detail-value { font-size: 12px; }
}
</style>


<!-- Notification Modal -->
<div id="notificationModal" class="notification-modal">
    <div class="notification-modal-content">
        <div class="notification-modal-header"><h3 id="notifModalTitle"></h3><span class="close-notification-modal" onclick="closeNotificationModal()">&times;</span></div>
        <div class="notification-modal-body">
            <div id="notifModalIcon" class="notification-type-icon"></div>
            <p id="notifModalMessage"></p>
            <div class="notification-modal-time"><i class="far fa-clock"></i> <span id="notifModalTime"></span></div>
        </div>
        <button class="btn-close-modal" onclick="closeNotificationModal()">Close</button>
    </div>
</div>

<!-- Premium Card Modal -->
<div id="cardModal" class="card-modal">
    <div class="card-modal-content">
        <div class="close-card-modal" onclick="closeCardModal()">&times;</div>
        <div class="premium-card">
            <div class="card-bg-pattern"></div>
            <div class="card-chip"><svg width="45" height="35" viewBox="0 0 50 40"><rect x="5" y="5" width="40" height="30" rx="3" fill="#D4AF37" stroke="#B8960C" stroke-width="1"/><rect x="10" y="10" width="30" height="20" rx="2" fill="#E8C84A"/><line x1="15" y1="18" x2="35" y2="18" stroke="#B8960C" stroke-width="1.5"/><line x1="20" y1="23" x2="30" y2="23" stroke="#B8960C" stroke-width="1.5"/><circle cx="25" cy="15" r="3" fill="#D4AF37"/></svg></div>
            <div class="card-brand"><div class="card-tier-name"><?= strtoupper($cardTier['name']) ?></div><i class="fas fa-<?= $cardTier['icon'] ?>" style="color: <?= $cardTier['accent'] ?>; font-size: 20px;"></i></div>
            <div class="card-number"><?php if($card): ?><?= substr($card['CardNumber'], 0, 4) ?> **** **** <?= substr($card['CardNumber'], -4) ?><?php else: ?>4532 **** **** 1048<?php endif; ?></div>
            <div class="card-details"><div class="card-detail"><span class="card-detail-label">CARD HOLDER NAME</span><span class="card-detail-value"><?= strtoupper(htmlspecialchars($account['FirstName'] . ' ' . $account['LastName'])) ?></span></div><div class="card-detail"><span class="card-detail-label">EXPIRES</span><span class="card-detail-value"><?= $card ? date('m/y', strtotime($card['ExpiryDate'])) : '12/28' ?></span></div></div>
            <div class="card-network"><i class="fab fa-cc-visa" style="font-size: 40px;"></i></div>
        </div>
    </div>
</div>

<!-- Message Bubble -->
<div class="message-bubble" id="messageBubble">
    <i class="fas fa-comment-dots"></i>
    <?php
    $unreadReplies = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE customer_id = ? AND status = 'replied'");
    $unreadReplies->execute([$userId]);
    $replyCount = $unreadReplies->fetchColumn();
    if($replyCount > 0): ?>
        <span class="badge"><?= $replyCount ?></span>
    <?php endif; ?>
</div>

<!-- Feedback Modal -->
<div id="feedbackModal" class="feedback-modal">
    <div class="feedback-header">
        <h3><i class="fas fa-comment"></i> Send Feedback to Bank</h3>
        <span class="close-feedback" onclick="closeFeedbackModal()">&times;</span>
    </div>
    <div class="feedback-body">
        <select id="feedbackType">
            <option value="feedback">💬 General Feedback</option>
            <option value="complaint">⚠️ Complaint</option>
            <option value="suggestion">💡 Suggestion</option>
            <option value="issue">🔧 Technical Issue</option>
        </select>
        <input type="text" id="feedbackSubject" placeholder="Subject" required>
        <textarea id="feedbackMessage" placeholder="Write your message here... (Max 500 characters)" maxlength="500"></textarea>
        <div class="char-counter"><span id="charCount">0</span>/500 characters</div>
        <button class="send-feedback" onclick="sendFeedback()">Send Feedback <i class="fas fa-paper-plane"></i></button>
        <div class="my-feedback-link"><a href="#" onclick="toggleFeedbackHistory()">📋 View my previous feedback</a></div>
        <div id="feedbackHistory" style="display: none; margin-top: 15px;">
            <div class="feedback-history" id="feedbackHistoryList"></div>
        </div>
    </div>
    <div class="feedback-footer">Our team will respond within 24 hours</div>
</div>

<div id="toastContainer">
    <?php $toast = getToast(); if($toast): ?>
        <div class="toast-notification <?= $toast['type'] ?>">
            <i class="fas <?= $toast['type'] == 'success' ? 'fa-check-circle' : 'fa-info-circle' ?>"></i>
            <span><?= htmlspecialchars($toast['message']) ?></span>
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
    
    // Notification Functions
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
        
        if(!notification.is_read) {
            fetch(`?ajax_mark_read=1&id=${notification.notification_id}`).catch(err => console.error(err));
        }
    }
    
    function closeNotificationModal() {
        document.getElementById('notificationModal').style.display = 'none';
    }
    
    function toggleNotifications(event) {
        event.stopPropagation();
        const dropdown = document.getElementById('notificationDropdown');
        if(dropdown) dropdown.classList.toggle('show');
    }
    
    document.addEventListener('click', function() {
        const dropdown = document.getElementById('notificationDropdown');
        if(dropdown) dropdown.classList.remove('show');
    });
    
    // Card Modal
    const balanceCard = document.getElementById('balanceCard');
    const cardModal = document.getElementById('cardModal');
    if(balanceCard) {
        balanceCard.addEventListener('click', () => { cardModal.style.display = 'flex'; });
    }
    
    function closeCardModal() { cardModal.style.display = 'none'; }
    
    // Reactivation Modal Functions
    function openReactivationModal() {
        const modal = document.getElementById('reactivationModal');
        if(modal) {
            modal.style.display = 'flex';
            const reasonTextarea = document.getElementById('reactivationReason');
            if(reasonTextarea) reasonTextarea.value = '';
            const charCount = document.getElementById('reactivationCharCount');
            if(charCount) charCount.textContent = '0';
            checkExistingRequest();
        }
    }
    
    function closeReactivationModal() {
        const modal = document.getElementById('reactivationModal');
        if(modal) modal.style.display = 'none';
    }
    
    const reactivationReason = document.getElementById('reactivationReason');
    const reactivationCharCount = document.getElementById('reactivationCharCount');
    if(reactivationReason) {
        reactivationReason.addEventListener('input', function() {
            reactivationCharCount.textContent = this.value.length;
        });
    }
    
    function sendReactivationRequest() {
        const reason = document.getElementById('reactivationReason').value;
        if(!reason || reason.length < 10) {
            alert('Please provide a detailed reason (at least 10 characters)');
            return;
        }
        
        const formData = new URLSearchParams();
        formData.append('reason', reason);
        
        fetch('reactivation_request.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert(data.message);
                document.getElementById('reactivationReason').value = '';
                reactivationCharCount.textContent = '0';
                closeReactivationModal();
                checkExistingRequest();
            } else {
                alert(data.error);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error sending request. Please try again.');
        });
    }
    
    function checkExistingRequest() {
        fetch('get_reactivation_status.php')
            .then(response => response.json())
            .then(data => {
                if(data.has_request) {
                    const statusDiv = document.getElementById('existingRequestStatus');
                    const infoDiv = document.getElementById('requestStatusInfo');
                    
                    let statusText = '', statusClass = '';
                    switch(data.status) {
                        case 'pending': statusText = 'Pending Review'; statusClass = 'status-pending'; break;
                        case 'approved': statusText = 'Approved'; statusClass = 'status-approved'; break;
                        case 'rejected': statusText = 'Rejected'; statusClass = 'status-rejected'; break;
                    }
                    
                    infoDiv.innerHTML = `
                        <div style="margin-bottom: 10px;"><span class="request-status-badge ${statusClass}">${statusText}</span></div>
                        <div><strong>Your Reason:</strong> ${escapeHtml(data.reason)}</div>
                        <div><strong>Submitted:</strong> ${data.created_at}</div>
                        ${data.admin_reply ? `<div style="margin-top: 10px; padding: 10px; background: var(--bg-secondary); border-radius: 8px;"><strong>Admin Response:</strong> ${escapeHtml(data.admin_reply)}</div>` : ''}
                        ${data.estimated_timeframe ? `<div><strong>Estimated Timeframe:</strong> ${escapeHtml(data.estimated_timeframe)}</div>` : ''}
                    `;
                    statusDiv.style.display = 'block';
                } else {
                    document.getElementById('existingRequestStatus').style.display = 'none';
                }
            })
            .catch(error => console.error('Error:', error));
    }
    
    // Feedback Functions
    const messageInputFB = document.getElementById('feedbackMessage');
    const charCountSpan = document.getElementById('charCount');
    if(messageInputFB) {
        messageInputFB.addEventListener('input', function() {
            charCountSpan.textContent = this.value.length;
        });
    }
    
    const messageBubble = document.getElementById('messageBubble');
    const feedbackModal = document.getElementById('feedbackModal');
    if(messageBubble) {
        messageBubble.addEventListener('click', function(e) {
            e.stopPropagation();
            feedbackModal.classList.toggle('show');
        });
    }
    
    function closeFeedbackModal() {
        feedbackModal.classList.remove('show');
    }
    
    document.addEventListener('click', function(e) {
        if(feedbackModal && !feedbackModal.contains(e.target) && messageBubble && !messageBubble.contains(e.target)) {
            feedbackModal.classList.remove('show');
        }
        if(reactivationModal && reactivationModal.style.display === 'flex' && e.target === reactivationModal) {
            closeReactivationModal();
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if(e.key === 'Escape') {
            closeNotificationModal();
            closeCardModal();
            closeReactivationModal();
        }
    });
    
    function sendFeedback() {
        const type = document.getElementById('feedbackType').value;
        const subject = document.getElementById('feedbackSubject').value;
        const message = document.getElementById('feedbackMessage').value;
        
        if(!subject || !message) {
            alert('Please fill in both subject and message');
            return;
        }
        
        const formData = new URLSearchParams();
        formData.append('type', type);
        formData.append('subject', subject);
        formData.append('message', message);
        
        fetch('send_feedback.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: formData.toString()
        })
        .then(response => response.json())
        .then(data => {
            if(data.success) {
                alert('✓ Feedback sent successfully! Our team will respond within 24 hours.');
                document.getElementById('feedbackSubject').value = '';
                document.getElementById('feedbackMessage').value = '';
                if(charCountSpan) charCountSpan.textContent = '0';
                closeFeedbackModal();
            } else {
                alert('Error: ' + (data.error || 'Something went wrong'));
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error sending feedback. Please try again.');
        });
    }
    
    function loadFeedbackHistory() {
        fetch('get_feedback.php')
            .then(response => response.json())
            .then(data => {
                const historyDiv = document.getElementById('feedbackHistoryList');
                if(historyDiv) {
                    if(data.length === 0 || data.error) {
                        historyDiv.innerHTML = '<div style="padding:20px; text-align:center; color:var(--text-tertiary);">No feedback yet</div>';
                    } else {
                        historyDiv.innerHTML = data.map(f => `
                            <div class="feedback-item">
                                <div class="subject">${escapeHtml(f.subject)}</div>
                                <div class="message">${escapeHtml(f.message.substring(0, 80))}${f.message.length > 80 ? '...' : ''}</div>
                                <span class="status status-${f.status}">${f.status.toUpperCase()}</span>
                                ${f.staff_reply ? `<div class="reply"><strong>Reply:</strong> ${escapeHtml(f.staff_reply.substring(0, 100))}</div>` : ''}
                                <small style="font-size:9px; color:var(--text-tertiary);">${f.created_at}</small>
                            </div>
                        `).join('');
                    }
                }
            })
            .catch(error => console.error('Error loading feedback:', error));
    }
    
    function toggleFeedbackHistory() {
        const historyDiv = document.getElementById('feedbackHistory');
        if(historyDiv) {
            if(historyDiv.style.display === 'none') {
                historyDiv.style.display = 'block';
                loadFeedbackHistory();
            } else {
                historyDiv.style.display = 'none';
            }
        }
    }
    
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
</script>

</body>
</html>