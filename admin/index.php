<?php
require_once '../config/db.php';

// Check if user is admin
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    redirect('../login.php');
}

// Handle Staff Actions
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
        } elseif($sendTo == 'pending_kyc') {
            $stmt = $pdo->prepare("INSERT INTO notifications (customer_id, title, message, type) SELECT k.customer_id, ?, ?, ? FROM kyc_verifications k WHERE k.status = 'pending'");
            $stmt->execute([$title, $message, $type]);
            setToast("Notification sent to pending KYC customers", "success");
        }
        redirect('index.php');
    }
    
    if($action == 'verify_kyc' && isset($_GET['id'])) {
        $pdo->prepare("UPDATE kyc_verifications SET status = 'verified', verified_at = NOW() WHERE customer_id = ?")->execute([$_GET['id']]);
        $pdo->prepare("INSERT INTO notifications (customer_id, title, message, type) VALUES (?, 'KYC Verified', 'Your KYC has been verified! You can now access all banking features.', 'success')")->execute([$_GET['id']]);
        setToast("KYC verified", "success");
        redirect('index.php');
    }
    
    if($action == 'reject_kyc' && isset($_GET['id'])) {
        $reason = $_GET['reason'] ?? 'Invalid documents';
        $pdo->prepare("UPDATE kyc_verifications SET status = 'rejected', rejection_reason = ? WHERE customer_id = ?")->execute([$reason, $_GET['id']]);
        $pdo->prepare("INSERT INTO notifications (customer_id, title, message, type) VALUES (?, 'KYC Rejected', 'Your KYC was rejected. Reason: ' || ?, 'danger')")->execute([$_GET['id'], $reason]);
        setToast("KYC rejected", "warning");
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
    $password = password_hash($_POST['password'], PASSWORD_BCRYPT);
    $role = $_POST['role'];
    $department = $_POST['department'];
    
    $stmt = $pdo->prepare("INSERT INTO staff (first_name, last_name, email, phone, username, password_hash, role, department, join_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE())");
    $stmt->execute([$firstName, $lastName, $email, $phone, $username, $password, $role, $department]);
    setToast("New staff member added", "success");
    redirect('index.php');
}

// Get statistics
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM CUSTOMER")->fetchColumn();
$totalBalance = $pdo->query("SELECT SUM(AvailableBalance) FROM ACCOUNT")->fetchColumn();
$totalTransactions = $pdo->query("SELECT COUNT(*) FROM TRANSACTION")->fetchColumn();
$pendingKYC = $pdo->query("SELECT COUNT(*) FROM kyc_verifications WHERE status = 'pending'")->fetchColumn();
$totalStaff = $pdo->query("SELECT COUNT(*) FROM staff")->fetchColumn();

// Get pending KYC
$pendingKYCCustomers = $pdo->query("SELECT k.*, c.FirstName, c.LastName, c.Email, c.Phone FROM kyc_verifications k JOIN CUSTOMER c ON k.customer_id = c.CustomerID WHERE k.status = 'pending' ORDER BY k.submitted_at DESC")->fetchAll();

// Get all staff
$staffList = $pdo->query("SELECT * FROM staff ORDER BY created_at DESC")->fetchAll();

// Get recent notifications
$notifications = $pdo->query("SELECT n.*, c.FirstName, c.LastName FROM notifications n LEFT JOIN CUSTOMER c ON n.customer_id = c.CustomerID ORDER BY n.created_at DESC LIMIT 20")->fetchAll();

// Get all customers for quick view
$customers = $pdo->query("SELECT c.*, a.AccountNumber, a.AvailableBalance FROM CUSTOMER c LEFT JOIN ACCOUNT a ON c.CustomerID = a.CustomerID ORDER BY c.CreatedAt DESC LIMIT 15")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Asha Bank</title>
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
            --accent-bg: #E6F1FB;
            --success: #3B6D11;
            --danger: #A32D2D;
            --warning: #BA7517;
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        body.dark {
            --bg-primary: #0F172A;
            --bg-secondary: #1E293B;
            --text-primary: #F1F5F9;
            --text-secondary: #CBD5E1;
            --border-color: #334155;
            --accent: #3B82F6;
        }
        
        body { font-family: var(--font-sans); background: var(--bg-secondary); color: var(--text-primary); }
        
        .navbar {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            padding: 16px 5%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .logo { display: flex; align-items: center; gap: 12px; }
        .logo-icon { width: 36px; height: 36px; background: var(--accent-bg); border-radius: 10px; display: flex; align-items: center; justify-content: center; }
        .logo h1 { font-size: 20px; }
        .logo span { color: var(--accent); }
        
        .nav-links { display: flex; gap: 24px; align-items: center; }
        .nav-links a { text-decoration: none; color: var(--text-secondary); font-weight: 500; }
        .nav-links a:hover { color: var(--accent); }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 24px; }
        
        .stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; }
        .stat-label { font-size: 12px; color: var(--text-tertiary); margin-bottom: 8px; }
        .stat-value { font-size: 28px; font-weight: 700; }
        
        .glass-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 20px; padding: 24px; margin-bottom: 24px; }
        .card-title { font-size: 18px; font-weight: 600; margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center; }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { color: var(--text-tertiary); font-weight: 500; font-size: 12px; }
        
        .badge { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; display: inline-block; }
        .badge-pending { background: var(--warning); color: white; }
        .badge-verified { background: var(--success); color: white; }
        .badge-active { background: var(--success); color: white; }
        .badge-inactive { background: var(--danger); color: white; }
        
        .btn { padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer; font-size: 12px; font-weight: 500; text-decoration: none; display: inline-block; }
        .btn-sm { padding: 4px 8px; font-size: 11px; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-primary); }
        
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; justify-content: center; align-items: center; }
        .modal-content { background: var(--bg-primary); border-radius: 20px; padding: 24px; max-width: 500px; width: 90%; }
        .modal-header { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .close-modal { cursor: pointer; font-size: 20px; }
        
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid var(--border-color); border-radius: 8px; background: var(--bg-secondary); color: var(--text-primary); margin-bottom: 12px; }
        
        .flex-between { display: flex; justify-content: space-between; align-items: center; }
        .gap-2 { gap: 8px; }
        
        @media (max-width: 768px) {
            .navbar { flex-direction: column; gap: 12px; }
            .nav-links { flex-wrap: wrap; justify-content: center; }
            .stat-row { grid-template-columns: repeat(2, 1fr); }
            table { font-size: 12px; }
            th, td { padding: 8px; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo">
            <div class="logo-icon"><i class="fas fa-university"></i></div>
            <h1>Asha Bank <span>Admin</span></h1>
        </div>
        <div class="nav-links">
            <a href="index.php">Dashboard</a>
            <a href="#" onclick="showModal('staffModal')">Add Staff</a>
            <a href="#" onclick="showModal('notificationModal')">Send Notification</a>
            <button id="themeToggle" class="btn btn-outline"><i class="fas fa-moon"></i> Theme</button>
            <a href="../logout.php" class="btn btn-danger"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <!-- Stats -->
        <div class="stat-row">
            <div class="stat-card"><div class="stat-label">Total Customers</div><div class="stat-value"><?= $totalCustomers ?></div></div>
            <div class="stat-card"><div class="stat-label">Total Balance</div><div class="stat-value"><?= formatBDT($totalBalance) ?></div></div>
            <div class="stat-card"><div class="stat-label">Transactions</div><div class="stat-value"><?= $totalTransactions ?></div></div>
            <div class="stat-card"><div class="stat-label">Pending KYC</div><div class="stat-value"><?= $pendingKYC ?></div></div>
            <div class="stat-card"><div class="stat-label">Staff Members</div><div class="stat-value"><?= $totalStaff ?></div></div>
        </div>
        
        <!-- Pending KYC Section -->
        <div class="glass-card">
            <div class="card-title"><span><i class="fas fa-id-card"></i> Pending KYC Verification</span></div>
            <?php if(empty($pendingKYCCustomers)): ?>
                <p>No pending KYC requests</p>
            <?php else: ?>
                <div style="overflow-x: auto">
                    <table>
                        <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>NID</th><th>Submitted</th><th>Actions</th></tr></thead>
                        <tbody>
                            <?php foreach($pendingKYCCustomers as $k): ?>
                            <tr>
                                <td><?= $k['customer_id'] ?></td>
                                <td><?= $k['FirstName'] . ' ' . $k['LastName'] ?></td>
                                <td><?= $k['Email'] ?></td>
                                <td><?= $k['Phone'] ?></td>
                                <td><?= $k['nid_number'] ?? 'N/A' ?></td>
                                <td><?= date('d M Y', strtotime($k['submitted_at'])) ?></td>
                                <td class="gap-2">
                                    <a href="?action=verify_kyc&id=<?= $k['customer_id'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Verify this KYC?')">Verify</a>
                                    <a href="#" onclick="showRejectModal(<?= $k['customer_id'] ?>)" class="btn btn-danger btn-sm">Reject</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- Staff Management -->
        <div class="glass-card">
            <div class="card-title"><span><i class="fas fa-users"></i> Staff Management</span><button onclick="showModal('staffModal')" class="btn btn-primary">+ Add Staff</button></div>
            <div style="overflow-x: auto">
                <table>
                    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Username</th><th>Role</th><th>Department</th><th>Status</th><th>Actions</th></tr></thead>
                    <tbody>
                        <?php foreach($staffList as $s): ?>
                        <tr>
                            <td><?= $s['staff_id'] ?></td>
                            <td><?= $s['first_name'] . ' ' . $s['last_name'] ?></td>
                            <td><?= $s['email'] ?></td>
                            <td><?= $s['username'] ?></td>
                            <td><span class="badge badge-active"><?= ucfirst($s['role']) ?></span></td>
                            <td><?= $s['department'] ?></td>
                            <td><span class="badge <?= $s['is_active'] ? 'badge-active' : 'badge-inactive' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                            <td class="gap-2">
                                <a href="?action=toggle_staff&id=<?= $s['staff_id'] ?>" class="btn btn-warning btn-sm">Toggle</a>
                                <a href="?action=delete_staff&id=<?= $s['staff_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this staff?')">Delete</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Recent Notifications -->
        <div class="glass-card">
            <div class="card-title"><span><i class="fas fa-bell"></i> Recent Notifications</span></div>
            <div style="overflow-x: auto">
                <table>
                    <thead><tr><th>To</th><th>Title</th><th>Message</th><th>Type</th><th>Date</th></tr></thead>
                    <tbody>
                        <?php foreach($notifications as $n): ?>
                        <tr>
                            <td><?= $n['FirstName'] ? $n['FirstName'] . ' ' . $n['LastName'] : 'All Customers' ?></td>
                            <td><?= htmlspecialchars($n['title']) ?></td>
                            <td><?= htmlspecialchars(substr($n['message'], 0, 50)) ?>...</td>
                            <td><span class="badge <?= $n['type'] == 'success' ? 'badge-verified' : 'badge-pending' ?>"><?= $n['type'] ?></span></td>
                            <td><?= date('d M Y', strtotime($n['created_at'])) ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- All Customers -->
        <div class="glass-card">
            <div class="card-title"><span><i class="fas fa-users"></i> Recent Customers</span></div>
            <div style="overflow-x: auto">
                <table>
                    <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Account</th><th>Balance</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach($customers as $c): ?>
                        <tr>
                            <td><?= $c['CustomerID'] ?></td>
                            <td><?= $c['FirstName'] . ' ' . $c['LastName'] ?></td>
                            <td><?= $c['Email'] ?></td>
                            <td><?= $c['Phone'] ?></td>
                            <td><?= $c['AccountNumber'] ?? 'N/A' ?></td>
                            <td><?= formatBDT($c['AvailableBalance'] ?? 0) ?></td>
                            <td><span class="badge badge-active">Active</span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <!-- Add Staff Modal -->
    <div id="staffModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Add New Staff Member</h3><span class="close-modal" onclick="closeModal('staffModal')">&times;</span></div>
            <form method="POST">
                <input type="hidden" name="add_staff" value="1">
                <div class="flex-between gap-2">
                    <input type="text" name="first_name" placeholder="First Name" required style="flex:1">
                    <input type="text" name="last_name" placeholder="Last Name" required style="flex:1">
                </div>
                <input type="email" name="email" placeholder="Email" required>
                <input type="text" name="phone" placeholder="Phone">
                <input type="text" name="username" placeholder="Username" required>
                <input type="password" name="password" placeholder="Password" required>
                <select name="role" required>
                    <option value="manager">Manager</option>
                    <option value="officer">Officer</option>
                    <option value="teller">Teller</option>
                    <option value="support">Support</option>
                </select>
                <input type="text" name="department" placeholder="Department">
                <button type="submit" class="btn btn-primary" style="width:100%">Add Staff</button>
            </form>
        </div>
    </div>
    
    <!-- Send Notification Modal -->
    <div id="notificationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Send Notification to Customers</h3><span class="close-modal" onclick="closeModal('notificationModal')">&times;</span></div>
            <form method="POST" action="?action=send_notification">
                <select name="send_to" required>
                    <option value="all">All Customers</option>
                    <option value="pending_kyc">Customers with Pending KYC</option>
                </select>
                <input type="text" name="title" placeholder="Notification Title" required>
                <textarea name="message" rows="4" placeholder="Notification Message" required></textarea>
                <select name="type" required>
                    <option value="info">Info</option>
                    <option value="success">Success</option>
                    <option value="warning">Warning</option>
                    <option value="danger">Danger</option>
                </select>
                <button type="submit" class="btn btn-primary" style="width:100%">Send Notification</button>
            </form>
        </div>
    </div>
    
    <!-- Reject KYC Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header"><h3>Reject KYC</h3><span class="close-modal" onclick="closeModal('rejectModal')">&times;</span></div>
            <form id="rejectForm" method="GET">
                <input type="hidden" name="action" value="reject_kyc">
                <input type="hidden" name="id" id="rejectId">
                <input type="text" name="reason" placeholder="Rejection Reason" required>
                <button type="submit" class="btn btn-danger" style="width:100%">Reject KYC</button>
            </form>
        </div>
    </div>
    
    <script>
        function showModal(id) { document.getElementById(id).style.display = 'flex'; }
        function closeModal(id) { document.getElementById(id).style.display = 'none'; }
        
        function showRejectModal(customerId) {
            document.getElementById('rejectId').value = customerId;
            document.getElementById('rejectModal').style.display = 'flex';
        }
        
        // Theme Toggle
        const themeToggle = document.getElementById('themeToggle');
        const savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark') { document.body.classList.add('dark'); themeToggle.innerHTML = '<i class="fas fa-sun"></i> Light'; }
        themeToggle.addEventListener('click', () => {
            if (document.body.classList.contains('dark')) {
                document.body.classList.remove('dark'); localStorage.setItem('theme', 'light');
                themeToggle.innerHTML = '<i class="fas fa-moon"></i> Dark';
            } else {
                document.body.classList.add('dark'); localStorage.setItem('theme', 'dark');
                themeToggle.innerHTML = '<i class="fas fa-sun"></i> Light';
            }
        });
        
        window.onclick = function(e) { if (e.target.classList.contains('modal')) e.target.style.display = 'none'; }
    </script>
</body>
</html>