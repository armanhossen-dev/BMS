<?php
require_once '../config/db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    redirect('../login.php');
}

// Get statistics
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM CUSTOMER")->fetchColumn();
$totalBalance = $pdo->query("SELECT SUM(AvailableBalance) FROM ACCOUNT")->fetchColumn();
$totalTransactions = $pdo->query("SELECT COUNT(*) FROM TRANSACTION")->fetchColumn();
$totalStaff = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();
$pendingStaffMessages = $pdo->query("SELECT COUNT(*) FROM staff_messages WHERE status = 'pending'")->fetchColumn();

// Get daily transaction data for the last 7 days
$dailyData = [];
for($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $dateLabel = date('D, M d', strtotime("-$i days"));
    
    // Get deposits for the day
    $depositStmt = $pdo->prepare("SELECT COALESCE(SUM(TransactionAmount), 0) FROM TRANSACTION WHERE TransactionTypeID = 1 AND DATE(TransactionDate) = ?");
    $depositStmt->execute([$date]);
    $deposits = $depositStmt->fetchColumn();
    
    // Get withdrawals for the day
    $withdrawStmt = $pdo->prepare("SELECT COALESCE(SUM(TransactionAmount), 0) FROM TRANSACTION WHERE TransactionTypeID = 2 AND DATE(TransactionDate) = ?");
    $withdrawStmt->execute([$date]);
    $withdrawals = $withdrawStmt->fetchColumn();
    
    // Get transfers for the day
    $transferStmt = $pdo->prepare("SELECT COALESCE(SUM(TransactionAmount), 0) FROM TRANSACTION WHERE TransactionTypeID = 3 AND DATE(TransactionDate) = ?");
    $transferStmt->execute([$date]);
    $transfers = $transferStmt->fetchColumn();
    
    // Get total transaction count
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM TRANSACTION WHERE DATE(TransactionDate) = ?");
    $countStmt->execute([$date]);
    $transactionCount = $countStmt->fetchColumn();
    
    $dailyData[] = [
        'date' => $dateLabel,
        'deposits' => floatval($deposits),
        'withdrawals' => floatval($withdrawals),
        'transfers' => floatval($transfers),
        'count' => intval($transactionCount)
    ];
}

// Get monthly data for the last 6 months
$monthlyData = [];
for($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthLabel = date('M Y', strtotime("-$i months"));
    
    $depositStmt = $pdo->prepare("SELECT COALESCE(SUM(TransactionAmount), 0) FROM TRANSACTION WHERE TransactionTypeID = 1 AND DATE_FORMAT(TransactionDate, '%Y-%m') = ?");
    $depositStmt->execute([$month]);
    $deposits = $depositStmt->fetchColumn();
    
    $withdrawStmt = $pdo->prepare("SELECT COALESCE(SUM(TransactionAmount), 0) FROM TRANSACTION WHERE TransactionTypeID = 2 AND DATE_FORMAT(TransactionDate, '%Y-%m') = ?");
    $withdrawStmt->execute([$month]);
    $withdrawals = $withdrawStmt->fetchColumn();
    
    $monthlyData[] = [
        'month' => $monthLabel,
        'deposits' => floatval($deposits),
        'withdrawals' => floatval($withdrawals)
    ];
}

// Get top 5 customers by transaction volume
$topCustomers = $pdo->query("SELECT c.CustomerID, c.FirstName, c.LastName, COUNT(t.TransactionID) as transaction_count, COALESCE(SUM(t.TransactionAmount), 0) as total_volume FROM CUSTOMER c LEFT JOIN TRANSACTION t ON c.CustomerID = t.FromCustomerID OR c.CustomerID = t.ToCustomerID GROUP BY c.CustomerID ORDER BY total_volume DESC LIMIT 5")->fetchAll();

// Get recent transactions
$recentTransactions = $pdo->query("SELECT t.*, tt.TypeName, c.FirstName, c.LastName FROM TRANSACTION t JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID LEFT JOIN CUSTOMER c ON t.FromCustomerID = c.CustomerID ORDER BY t.TransactionDate DESC LIMIT 10")->fetchAll();

// Get all staff
$staffList = $pdo->query("SELECT * FROM staff ORDER BY created_at DESC")->fetchAll();

// Get all customers
$customers = $pdo->query("SELECT c.*, a.AccountNumber, a.AvailableBalance, a.AccountStatus FROM CUSTOMER c LEFT JOIN ACCOUNT a ON c.CustomerID = a.CustomerID ORDER BY c.CreatedAt DESC")->fetchAll();

// Get notifications
$notifications = $pdo->query("SELECT n.*, c.FirstName, c.LastName FROM notifications n LEFT JOIN CUSTOMER c ON n.customer_id = c.CustomerID ORDER BY n.created_at DESC LIMIT 20")->fetchAll();

// Get staff messages
$staffMessages = $pdo->query("SELECT sm.*, s.first_name, s.last_name, s.email FROM staff_messages sm JOIN staff s ON sm.staff_id = s.staff_id ORDER BY sm.created_at DESC")->fetchAll();

// Handle actions
if(isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if($action == 'delete_staff' && isset($_GET['id'])) {
        $pdo->prepare("DELETE FROM staff WHERE staff_id = ?")->execute([$_GET['id']]);
        setToast("Staff member deleted", "success");
        redirect('index.php');
    }
    
    if($action == 'toggle_staff' && isset($_GET['id'])) {
        $staff = $pdo->prepare("SELECT is_active FROM staff WHERE staff_id = ?");
        $staff->execute([$_GET['id']]);
        $current = $staff->fetch();
        $newStatus = $current['is_active'] ? 0 : 1;
        $pdo->prepare("UPDATE staff SET is_active = ? WHERE staff_id = ?")->execute([$newStatus, $_GET['id']]);
        setToast("Staff status updated", "success");
        redirect('index.php');
    }
    
    if($action == 'delete_customer' && isset($_GET['id'])) {
        $pdo->prepare("UPDATE CUSTOMER SET IsActive = 0 WHERE CustomerID = ?")->execute([$_GET['id']]);
        $pdo->prepare("UPDATE ACCOUNT SET AccountStatus = 'Closed' WHERE CustomerID = ?")->execute([$_GET['id']]);
        setToast("Customer account deactivated", "warning");
        redirect('index.php');
    }
    
    if($action == 'activate_customer' && isset($_GET['id'])) {
        $pdo->prepare("UPDATE CUSTOMER SET IsActive = 1 WHERE CustomerID = ?")->execute([$_GET['id']]);
        $pdo->prepare("UPDATE ACCOUNT SET AccountStatus = 'Active' WHERE CustomerID = ?")->execute([$_GET['id']]);
        setToast("Customer account activated", "success");
        redirect('index.php');
    }
    
    if($action == 'delete_notification' && isset($_GET['id'])) {
        $pdo->prepare("DELETE FROM notifications WHERE notification_id = ?")->execute([$_GET['id']]);
        redirect('index.php');
    }
    
    if($action == 'send_notification' && $_SERVER['REQUEST_METHOD'] == 'POST') {
        $title = $_POST['title'];
        $message = $_POST['message'];
        $type = $_POST['type'];
        $sendTo = $_POST['send_to'];
        
        if($sendTo == 'all') {
            $stmt = $pdo->prepare("INSERT INTO notifications (customer_id, title, message, type) SELECT CustomerID, ?, ?, ? FROM CUSTOMER WHERE IsActive = 1");
            $stmt->execute([$title, $message, $type]);
            setToast("Notification sent to all customers", "success");
        }
        redirect('index.php');
    }
}

// Add new staff
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_staff'])) {
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    $department = $_POST['department'];
    
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("INSERT INTO staff (first_name, last_name, email, phone, username, password_hash, role, department, join_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 1)");
    $stmt->execute([$firstName, $lastName, $email, $phone, $username, $hashedPassword, $role, $department]);
    setToast("New staff member added. Username: $username, Password: $password", "success");
    redirect('index.php');
}

$tab = $_GET['tab'] ?? 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Asha Bank</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
            --accent-bg: #E6F1FB;
            --success: #3B6D11;
            --danger: #A32D2D;
            --warning: #BA7517;
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
            --accent-bg: #1E3A5F;
        }
        
        body { font-family: var(--font-sans); background: var(--bg-secondary); color: var(--text-primary); transition: all 0.3s ease; }
        
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
        
        .theme-toggle { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 40px; padding: 8px 16px; cursor: pointer; font-size: 13px; }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 24px; }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .stat-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 20px;
            transition: all 0.2s;
        }
        .stat-card:hover { transform: translateY(-2px); box-shadow: var(--shadow-md); }
        .stat-label { font-size: 12px; color: var(--text-tertiary); margin-bottom: 8px; }
        .stat-value { font-size: 28px; font-weight: 700; }
        .stat-change { font-size: 11px; margin-top: 4px; }
        .stat-change.up { color: var(--success); }
        .stat-change.down { color: var(--danger); }
        
        /* Charts Grid */
        .charts-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 24px;
        }
        .chart-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 20px;
        }
        .chart-title {
            font-size: 16px;
            font-weight: 600;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .chart-title i { color: var(--accent); }
        .chart-container { height: 300px; position: relative; }
        
        /* Tabs */
        .admin-tabs {
            display: flex;
            gap: 8px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 60px;
            padding: 6px;
            margin-bottom: 24px;
            flex-wrap: wrap;
        }
        .admin-tab {
            padding: 10px 20px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 500;
            font-size: 13px;
            color: var(--text-secondary);
            transition: all 0.2s;
        }
        .admin-tab.active {
            background: var(--accent);
            color: white;
        }
        .admin-tab i { margin-right: 8px; }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        /* Tables */
        .glass-card {
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 24px;
            margin-bottom: 24px;
        }
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 1px solid var(--border-color);
        }
        .card-header h3 { font-size: 18px; font-weight: 600; }
        
        .table-container { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { color: var(--text-tertiary); font-weight: 500; font-size: 12px; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-active { background: var(--success); color: white; }
        .badge-inactive { background: var(--danger); color: white; }
        .badge-pending { background: var(--warning); color: white; }
        
        .btn { padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; transition: all 0.2s; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: var(--bg-primary);
            border-radius: 20px;
            padding: 28px;
            max-width: 500px;
            width: 90%;
        }
        .modal-content input, .modal-content textarea, .modal-content select {
            width: 100%;
            padding: 12px;
            margin-bottom: 12px;
            border: 1px solid var(--border-color);
            border-radius: 10px;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        @media (max-width: 1000px) {
            .charts-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 768px) {
            .navbar { flex-direction: column; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .admin-tab { padding: 8px 12px; font-size: 11px; }
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
            <h2>Asha Bank <span style="color: var(--accent);">Admin</span></h2>
        </div>
        <div class="navbar-right">
            <button class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i> Dark</button>
            <a href="../logout.php" class="btn btn-danger">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <!-- Statistics Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Customers</div>
                <div class="stat-value"><?= number_format($totalCustomers) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Bank Reserve</div>
                <div class="stat-value"><?= formatBDT($totalBalance) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Transactions</div>
                <div class="stat-value"><?= number_format($totalTransactions) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Staff Members</div>
                <div class="stat-value"><?= $totalStaff ?></div>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="charts-grid">
            <!-- Daily Transaction Chart -->
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-chart-line"></i> Daily Transactions (Last 7 Days)
                </div>
                <div class="chart-container">
                    <canvas id="dailyChart"></canvas>
                </div>
            </div>
            
            <!-- Monthly Comparison Chart -->
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-chart-bar"></i> Monthly Deposits vs Withdrawals
                </div>
                <div class="chart-container">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Top Customers & Recent Transactions -->
        <div class="charts-grid">
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-trophy"></i> Top Customers by Volume
                </div>
                <div class="table-container">
                    <table style="width: 100%">
                        <thead>
                            <tr><th>Customer</th><th>Transactions</th><th>Total Volume</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($topCustomers as $tc): ?>
                            <tr>
                                <td><?= $tc['FirstName'] . ' ' . $tc['LastName'] ?> \(cid=<?= $tc['CustomerID'] ?>)</td>
                                <td><?= $tc['transaction_count'] ?></td>
                                <td><?= formatBDT($tc['total_volume']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <div class="chart-card">
                <div class="chart-title">
                    <i class="fas fa-clock"></i> Recent Transactions
                </div>
                <div class="table-container">
                    <table style="width: 100%">
                        <thead>
                            <tr><th>Date</th><th>Type</th><th>Amount</th><th>Customer</th></tr>
                        </thead>
                        <tbody>
                            <?php foreach($recentTransactions as $rt): ?>
                            <tr>
                                <td><?= date('d M H:i', strtotime($rt['TransactionDate'])) ?></td>
                                <td><?= $rt['TypeName'] ?></td>
                                <td><?= formatBDT($rt['TransactionAmount']) ?></td>
                                <td><?= $rt['FirstName'] ?? 'System' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Tabs for Management -->
        <div class="admin-tabs">
            <div class="admin-tab <?= $tab == 'dashboard' ? 'active' : '' ?>" data-tab="dashboard"><i class="fas fa-chart-pie"></i> Dashboard</div>
            <div class="admin-tab <?= $tab == 'staff' ? 'active' : '' ?>" data-tab="staff"><i class="fas fa-users"></i> Staff Management</div>
            <div class="admin-tab <?= $tab == 'customers' ? 'active' : '' ?>" data-tab="customers"><i class="fas fa-user-friends"></i> Customers</div>
            <div class="admin-tab <?= $tab == 'notifications' ? 'active' : '' ?>" data-tab="notifications"><i class="fas fa-bell"></i> Notifications</div>
            <div class="admin-tab <?= $tab == 'staff_messages' ? 'active' : '' ?>" data-tab="staff_messages"><i class="fas fa-envelope"></i> Staff Messages</div>
        </div>
        
        <!-- Staff Management Tab -->
        <div id="staffTab" class="tab-content <?= $tab == 'staff' ? 'active' : '' ?>">
            <div class="glass-card">
                <div class="card-header">
                    <h3><i class="fas fa-users"></i> Staff Management</h3>
                    <button class="btn btn-primary" onclick="openStaffModal()">+ Add Staff</button>
                </div>
                <div class="table-container">
                    <table>
                        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Username</th><th>Role</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach($staffList as $s): ?>
                            <tr>
                                <td><?= $s['staff_id'] ?></td>
                                <td><?= $s['first_name'] . ' ' . $s['last_name'] ?></td>
                                <td><?= $s['email'] ?></td>
                                <td><?= $s['username'] ?></td>
                                <td><span class="badge badge-active"><?= ucfirst($s['role']) ?></span></td>
                                <td><span class="badge <?= $s['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                                <td>
                                    <a href="?action=toggle_staff&id=<?= $s['staff_id'] ?>" class="btn btn-warning btn-sm">Toggle</a>
                                    <a href="?action=delete_staff&id=<?= $s['staff_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this staff?')">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Customers Tab -->
        <div id="customersTab" class="tab-content <?= $tab == 'customers' ? 'active' : '' ?>">
            <div class="glass-card">
                <div class="card-header"><h3><i class="fas fa-user-friends"></i> All Customers</h3></div>
                <div class="table-container">
                    <table>
                        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Account</th><th>Balance</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach($customers as $c): ?>
                            <tr>
                                <td><?= $c['CustomerID'] ?></td>
                                <td><?= $c['FirstName'] . ' ' . $c['LastName'] ?></td>
                                <td><?= $c['Email'] ?></td>
                                <td><?= $c['Phone'] ?></td>
                                <td><?= $c['AccountNumber'] ?? 'N/A' ?></td>
                                <td><?= formatBDT($c['AvailableBalance'] ?? 0) ?></td>
                                <td><span class="badge <?= $c['IsActive'] ? 'badge-active' : 'badge-inactive' ?>"><?= $c['IsActive'] ? 'Active' : 'Inactive' ?></span></td>
                                <td>
                                    <?php if($c['IsActive']): ?>
                                        <a href="?action=delete_customer&id=<?= $c['CustomerID'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Deactivate this customer?')">Deactivate</a>
                                    <?php else: ?>
                                        <a href="?action=activate_customer&id=<?= $c['CustomerID'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Activate this customer?')">Activate</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Notifications Tab -->
        <div id="notificationsTab" class="tab-content <?= $tab == 'notifications' ? 'active' : '' ?>">
            <div class="glass-card">
                <div class="card-header">
                    <h3><i class="fas fa-bell"></i> Notifications</h3>
                    <button class="btn btn-primary" onclick="openNotificationModal()">+ Send New</button>
                </div>
                <div class="table-container">
                    <table>
                        <thead><tr><th>To</th><th>Title</th><th>Message</th><th>Date</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach($notifications as $n): ?>
                            <tr>
                                <td><?= $n['FirstName'] ? $n['FirstName'] . ' ' . $n['LastName'] : 'All Customers' ?></td>
                                <td><?= htmlspecialchars($n['title']) ?></td>
                                <td><?= htmlspecialchars(substr($n['message'], 0, 40)) ?>...</td>
                                <td><?= date('d M Y', strtotime($n['created_at'])) ?></td>
                                <td><a href="?action=delete_notification&id=<?= $n['notification_id'] ?>" class="btn btn-danger btn-sm">Delete</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Staff Messages Tab -->
        <div id="staff_messagesTab" class="tab-content <?= $tab == 'staff_messages' ? 'active' : '' ?>">
            <div class="glass-card">
                <div class="card-header"><h3><i class="fas fa-envelope"></i> Messages from Staff</h3></div>
                <?php foreach($staffMessages as $msg): ?>
                <div class="feedback-item" style="padding: 16px; border-bottom: 1px solid var(--border-color);">
                    <div><strong><?= htmlspecialchars($msg['subject']) ?></strong> <span class="badge badge-pending"><?= $msg['status'] ?></span></div>
                    <div><small>From: <?= $msg['first_name'] . ' ' . $msg['last_name'] ?></small></div>
                    <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                    <?php if($msg['admin_reply']): ?>
                        <div style="background: var(--accent-bg); padding: 12px; border-radius: 12px; margin-top: 10px;"><strong>Reply:</strong> <?= nl2br(htmlspecialchars($msg['admin_reply'])) ?></div>
                    <?php else: ?>
                        <button class="btn btn-primary btn-sm" onclick="openReplyModal(<?= $msg['message_id'] ?>)">Reply</button>
                        <div id="replyForm-<?= $msg['message_id'] ?>" style="display: none; margin-top: 12px;">
                            <form method="POST">
                                <input type="hidden" name="message_id" value="<?= $msg['message_id'] ?>">
                                <textarea name="admin_reply" rows="3" style="width:100%; padding:10px; border-radius:10px;"></textarea>
                                <select name="status"><option value="approved">Approve</option><option value="rejected">Reject</option></select>
                                <button type="submit" name="reply_staff_message" class="btn btn-success">Send</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Staff Modal -->
    <div id="staffModal" class="modal">
        <div class="modal-content">
            <h3>Add New Staff</h3>
            <form method="POST">
                <input type="hidden" name="add_staff" value="1">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
                    <input type="text" name="first_name" placeholder="First Name" required>
                    <input type="text" name="last_name" placeholder="Last Name" required>
                </div>
                <input type="email" name="email" placeholder="Email" required>
                <input type="text" name="phone" placeholder="Phone">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <select name="role"><option value="manager">Manager</option><option value="officer">Officer</option><option value="teller">Teller</option><option value="support">Support</option></select>
                <input type="text" name="department" placeholder="Department">
                <button type="submit" class="btn btn-primary" style="width:100%">Add Staff</button>
                <button type="button" class="btn btn-outline" style="width:100%; margin-top:10px;" onclick="closeStaffModal()">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Send Notification Modal -->
    <div id="notificationModal" class="modal">
        <div class="modal-content">
            <h3>Send Notification</h3>
            <form method="POST" action="?action=send_notification">
                <select name="send_to"><option value="all">All Customers</option></select>
                <input type="text" name="title" placeholder="Title" required>
                <textarea name="message" rows="4" placeholder="Message" required></textarea>
                <select name="type"><option value="info">Info</option><option value="success">Success</option><option value="warning">Warning</option></select>
                <button type="submit" class="btn btn-primary" style="width:100%">Send</button>
                <button type="button" class="btn btn-outline" style="width:100%; margin-top:10px;" onclick="closeNotificationModal()">Cancel</button>
            </form>
        </div>
    </div>
    
    <script>
        // Chart Data from PHP
        const dailyLabels = <?= json_encode(array_column($dailyData, 'date')) ?>;
        const dailyDeposits = <?= json_encode(array_column($dailyData, 'deposits')) ?>;
        const dailyWithdrawals = <?= json_encode(array_column($dailyData, 'withdrawals')) ?>;
        const dailyTransfers = <?= json_encode(array_column($dailyData, 'transfers')) ?>;
        
        const monthlyLabels = <?= json_encode(array_column($monthlyData, 'month')) ?>;
        const monthlyDeposits = <?= json_encode(array_column($monthlyData, 'deposits')) ?>;
        const monthlyWithdrawals = <?= json_encode(array_column($monthlyData, 'withdrawals')) ?>;
        
        // Format currency for charts
        function formatCurrency(value) {
            return '৳ ' + (value / 1000).toFixed(1) + 'K';
        }
        
        // Daily Line Chart
        const dailyCtx = document.getElementById('dailyChart').getContext('2d');
        new Chart(dailyCtx, {
            type: 'line',
            data: {
                labels: dailyLabels,
                datasets: [
                    {
                        label: 'Deposits (BDT)',
                        data: dailyDeposits,
                        borderColor: '#3B6D11',
                        backgroundColor: 'rgba(59, 109, 17, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Withdrawals (BDT)',
                        data: dailyWithdrawals,
                        borderColor: '#A32D2D',
                        backgroundColor: 'rgba(163, 45, 45, 0.1)',
                        tension: 0.4,
                        fill: true
                    },
                    {
                        label: 'Transfers (BDT)',
                        data: dailyTransfers,
                        borderColor: '#185FA5',
                        backgroundColor: 'rgba(24, 95, 165, 0.1)',
                        tension: 0.4,
                        fill: true
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': ' + formatCurrency(context.raw); } } }
                }
            }
        });
        
        // Monthly Bar Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        new Chart(monthlyCtx, {
            type: 'bar',
            data: {
                labels: monthlyLabels,
                datasets: [
                    {
                        label: 'Deposits',
                        data: monthlyDeposits,
                        backgroundColor: '#3B6D11',
                        borderRadius: 8
                    },
                    {
                        label: 'Withdrawals',
                        data: monthlyWithdrawals,
                        backgroundColor: '#A32D2D',
                        borderRadius: 8
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top' },
                    tooltip: { callbacks: { label: function(context) { return context.dataset.label + ': ' + formatCurrency(context.raw); } } }
                }
            }
        });
        
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        if(localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i> Light';
        }
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark');
            localStorage.setItem('theme', document.body.classList.contains('dark') ? 'dark' : 'light');
            themeToggle.innerHTML = document.body.classList.contains('dark') ? '<i class="fas fa-sun"></i> Light' : '<i class="fas fa-moon"></i> Dark';
            setTimeout(() => location.reload(), 100);
        });
        
        // Tab switching
        document.querySelectorAll('.admin-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                window.location.href = `?tab=${this.dataset.tab}`;
            });
        });
        
        function openStaffModal() { document.getElementById('staffModal').style.display = 'flex'; }
        function closeStaffModal() { document.getElementById('staffModal').style.display = 'none'; }
        function openNotificationModal() { document.getElementById('notificationModal').style.display = 'flex'; }
        function closeNotificationModal() { document.getElementById('notificationModal').style.display = 'none'; }
        function openReplyModal(id) { document.getElementById('replyForm-' + id).style.display = 'block'; }
        
        window.onclick = function(e) { if(e.target.classList.contains('modal')) e.target.style.display = 'none'; }
    </script>
</body>
</html>