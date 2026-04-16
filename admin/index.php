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

// Get all staff
$staffList = $pdo->query("SELECT * FROM staff ORDER BY created_at DESC")->fetchAll();

// Get all customers (for delete functionality)
$customers = $pdo->query("SELECT c.*, a.AccountNumber, a.AvailableBalance, a.AccountStatus FROM CUSTOMER c LEFT JOIN ACCOUNT a ON c.CustomerID = a.CustomerID ORDER BY c.CreatedAt DESC")->fetchAll();

// Get recent notifications
$notifications = $pdo->query("SELECT n.*, c.FirstName, c.LastName FROM notifications n LEFT JOIN CUSTOMER c ON n.customer_id = c.CustomerID ORDER BY n.created_at DESC LIMIT 20")->fetchAll();

// Get staff messages to admin
$staffMessages = $pdo->query("SELECT sm.*, s.first_name, s.last_name, s.email FROM staff_messages sm JOIN staff s ON sm.staff_id = s.staff_id ORDER BY sm.created_at DESC")->fetchAll();

// Handle staff message reply
if(isset($_POST['reply_staff_message'])) {
    $messageId = $_POST['message_id'];
    $reply = $_POST['admin_reply'];
    $status = $_POST['status'];
    
    $pdo->prepare("UPDATE staff_messages SET admin_reply = ?, status = ?, replied_at = NOW() WHERE message_id = ?")->execute([$reply, $status, $messageId]);
    setToast("Reply sent to staff", "success");
    redirect('index.php');
}

// Handle staff actions
if(isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if($action == 'delete_staff' && isset($_GET['id'])) {
        $pdo->prepare("DELETE FROM staff WHERE staff_id = ?")->execute([$_GET['id']]);
        setToast("Staff member deleted", "success");
        redirect('index.php');
    }
    
    if($action == 'delete_customer' && isset($_GET['id'])) {
        // Soft delete - just deactivate
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
        }
        redirect('index.php');
    }
}

// Add new staff with proper password hashing
if($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_staff'])) {
    $firstName = $_POST['first_name'];
    $lastName = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    $role = $_POST['role'];
    $department = $_POST['department'];
    
    // Hash the password
    $hashedPassword = password_hash($password, PASSWORD_BCRYPT);
    
    $stmt = $pdo->prepare("INSERT INTO staff (first_name, last_name, email, phone, username, password_hash, role, department, join_date, is_active) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 1)");
    $stmt->execute([$firstName, $lastName, $email, $phone, $username, $hashedPassword, $role, $department]);
    setToast("New staff member added. Username: $username, Password: $password", "success");
    redirect('index.php');
}

$tab = $_GET['tab'] ?? 'staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Asha Bank</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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
            --shadow-md: 0 4px 6px -1px rgba(0,0,0,0.1);
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
        }
        .stat-label { font-size: 12px; color: var(--text-tertiary); margin-bottom: 8px; }
        .stat-value { font-size: 28px; font-weight: 700; }
        
        /* Tabs */
        .admin-tabs {
            display: flex;
            gap: 8px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 60px;
            padding: 6px;
            margin-bottom: 24px;
        }
        .admin-tab {
            flex: 1;
            text-align: center;
            padding: 12px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
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
        .badge-success { background: var(--success); color: white; }
        .badge-info { background: var(--accent); color: white; }
        
        .btn { padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer; font-size: 12px; text-decoration: none; display: inline-block; transition: all 0.2s; }
        .btn-primary { background: var(--accent); color: white; }
        .btn-success { background: var(--success); color: white; }
        .btn-danger { background: var(--danger); color: white; }
        .btn-warning { background: var(--warning); color: white; }
        .btn-outline { background: transparent; border: 1px solid var(--border-color); color: var(--text-primary); }
        
        .btn-sm { padding: 4px 10px; font-size: 11px; }
        
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
            animation: modalSlideIn 0.3s ease;
        }
        @keyframes modalSlideIn {
            from { transform: translateY(-30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
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
        
        .feedback-item {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
        }
        .reply-form { display: none; margin-top: 12px; padding-top: 12px; border-top: 1px solid var(--border-color); }
        
        @media (max-width: 768px) {
            .navbar { flex-direction: column; }
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .admin-tabs { flex-wrap: wrap; border-radius: 16px; }
            .admin-tab { font-size: 12px; padding: 8px; }
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
        <div class="stats-grid">
            <div class="stat-card"><div class="stat-label">Total Customers</div><div class="stat-value"><?= $totalCustomers ?></div></div>
            <div class="stat-card"><div class="stat-label">Total Balance</div><div class="stat-value"><?= formatBDT($totalBalance) ?></div></div>
            <div class="stat-card"><div class="stat-label">Transactions</div><div class="stat-value"><?= $totalTransactions ?></div></div>
            <div class="stat-card"><div class="stat-label">Staff Members</div><div class="stat-value"><?= $totalStaff ?></div></div>
            <div class="stat-card"><div class="stat-label">Staff Messages</div><div class="stat-value"><?= $pendingStaffMessages ?></div></div>
        </div>
        
        <div class="admin-tabs">
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
                        <thead>
                            <tr><th>ID</th><th>Name</th><th>Email</th><th>Username</th><th>Role</th><th>Department</th><th>Status</th><th>Actions</th></tr>
                        </thead>
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
                                <td>
                                    <a href="?action=toggle_staff&id=<?= $s['staff_id'] ?>" class="btn btn-warning btn-sm">Toggle</a>
                                    <a href="?action=delete_staff&id=<?= $s['staff_id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this staff permanently?')">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- Customers Tab (with Delete User) -->
        <div id="customersTab" class="tab-content <?= $tab == 'customers' ? 'active' : '' ?>">
            <div class="glass-card">
                <div class="card-header">
                    <h3><i class="fas fa-user-friends"></i> All Customers</h3>
                </div>
                <div class="table-container">
                    <table>
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Phone</th>
                                <th>Account</th>
                                <th>Balance</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
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
                                        <a href="?action=delete_customer&id=<?= $c['CustomerID'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Deactivate this customer account?')">Deactivate</a>
                                    <?php else: ?>
                                        <a href="?action=activate_customer&id=<?= $c['CustomerID'] ?>" class="btn btn-success btn-sm" onclick="return confirm('Activate this customer account?')">Activate</a>
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
                        <thead><tr><th>To</th><th>Title</th><th>Message</th><th>Type</th><th>Date</th><th>Action</th></tr></thead>
                        <tbody>
                            <?php foreach($notifications as $n): ?>
                            <tr>
                                <td><?= $n['FirstName'] ? $n['FirstName'] . ' ' . $n['LastName'] : 'All Customers' ?></td>
                                <td><?= htmlspecialchars($n['title']) ?></td>
                                <td><?= htmlspecialchars(substr($n['message'], 0, 40)) ?>...</td>
                                <td><span class="badge badge-info"><?= $n['type'] ?></span></td>
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
                <div class="card-header">
                    <h3><i class="fas fa-envelope"></i> Messages from Staff</h3>
                </div>
                <?php if(empty($staffMessages)): ?>
                    <p style="text-align: center; padding: 40px;">No messages from staff</p>
                <?php else: ?>
                    <?php foreach($staffMessages as $msg): ?>
                    <div class="feedback-item">
                        <div><strong><?= htmlspecialchars($msg['subject']) ?></strong> <span class="badge badge-pending"><?= $msg['status'] ?></span></div>
                        <div><small>From: <?= $msg['first_name'] . ' ' . $msg['last_name'] ?></small></div>
                        <div class="feedback-message"><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                        <?php if($msg['admin_reply']): ?>
                            <div style="background: var(--accent-bg); padding: 12px; border-radius: 12px; margin-top: 10px;">
                                <strong>Your Reply:</strong> <?= nl2br(htmlspecialchars($msg['admin_reply'])) ?>
                            </div>
                        <?php else: ?>
                            <button class="btn btn-primary btn-sm" onclick="openReplyModal(<?= $msg['message_id'] ?>)">Reply</button>
                            <div id="replyForm-<?= $msg['message_id'] ?>" class="reply-form">
                                <form method="POST">
                                    <input type="hidden" name="message_id" value="<?= $msg['message_id'] ?>">
                                    <textarea name="admin_reply" rows="3" placeholder="Write your response..." required></textarea>
                                    <select name="status">
                                        <option value="approved">✓ Approve</option>
                                        <option value="rejected">✗ Reject</option>
                                    </select>
                                    <button type="submit" name="reply_staff_message" class="btn btn-success">Send Reply</button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Add Staff Modal -->
    <div id="staffModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-user-plus"></i> Add New Staff Member</h3>
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
                <select name="role" required>
                    <option value="manager">Manager</option>
                    <option value="officer">Officer</option>
                    <option value="teller">Teller</option>
                    <option value="support">Support</option>
                </select>
                <input type="text" name="department" placeholder="Department">
                <button type="submit" class="btn btn-primary" style="width: 100%;">Add Staff Member</button>
                <button type="button" class="btn btn-outline" style="width: 100%; margin-top: 10px;" onclick="closeStaffModal()">Cancel</button>
            </form>
        </div>
    </div>
    
    <!-- Send Notification Modal -->
    <div id="notificationModal" class="modal">
        <div class="modal-content">
            <h3><i class="fas fa-bell"></i> Send Notification</h3>
            <form method="POST" action="?action=send_notification">
                <select name="send_to" required>
                    <option value="all">All Customers</option>
                </select>
                <input type="text" name="title" placeholder="Title" required>
                <textarea name="message" rows="4" placeholder="Message" required></textarea>
                <select name="type" required>
                    <option value="info">ℹ️ Info</option>
                    <option value="success">✅ Success</option>
                    <option value="warning">⚠️ Warning</option>
                    <option value="danger">🔴 Danger</option>
                </select>
                <button type="submit" class="btn btn-primary" style="width: 100%;">Send</button>
                <button type="button" class="btn btn-outline" style="width: 100%; margin-top: 10px;" onclick="closeNotificationModal()">Cancel</button>
            </form>
        </div>
    </div>
    
    <script>
        const themeToggle = document.getElementById('themeToggle');
        if(localStorage.getItem('theme') === 'dark') {
            document.body.classList.add('dark');
            themeToggle.innerHTML = '<i class="fas fa-sun"></i> Light';
        }
        themeToggle.addEventListener('click', () => {
            document.body.classList.toggle('dark');
            localStorage.setItem('theme', document.body.classList.contains('dark') ? 'dark' : 'light');
            themeToggle.innerHTML = document.body.classList.contains('dark') ? '<i class="fas fa-sun"></i> Light' : '<i class="fas fa-moon"></i> Dark';
        });
        
        document.querySelectorAll('.admin-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                window.location.href = `?tab=${this.dataset.tab}`;
            });
        });
        
        function openStaffModal() { document.getElementById('staffModal').style.display = 'flex'; }
        function closeStaffModal() { document.getElementById('staffModal').style.display = 'none'; }
        function openNotificationModal() { document.getElementById('notificationModal').style.display = 'flex'; }
        function closeNotificationModal() { document.getElementById('notificationModal').style.display = 'none'; }
        
        function openReplyModal(id) {
            const form = document.getElementById('replyForm-' + id);
            form.style.display = 'block';
        }
        
        window.onclick = function(e) {
            if(e.target.classList.contains('modal')) e.target.style.display = 'none';
        }
    </script>
</body>
</html>