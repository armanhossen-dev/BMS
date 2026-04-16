<?php
require_once '../config/db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'staff') {
    redirect('../login.php');
}

$staffId = $_SESSION['user_id'];

// Get pending KYC
$pendingKYC = $pdo->query("SELECT COUNT(*) FROM kyc_verifications WHERE status = 'pending'")->fetchColumn();
$todayTrans = $pdo->query("SELECT COUNT(*) FROM TRANSACTION WHERE DATE(TransactionDate) = CURDATE()")->fetchColumn();

// Get pending KYC customers
$pendingKYCCustomers = $pdo->query("SELECT k.*, c.FirstName, c.LastName, c.Email, c.Phone FROM kyc_verifications k JOIN CUSTOMER c ON k.customer_id = c.CustomerID WHERE k.status = 'pending' LIMIT 20")->fetchAll();

// Get pending feedback
$pendingFeedback = $pdo->query("SELECT f.*, c.FirstName, c.LastName, c.Email, c.Phone FROM feedback f JOIN CUSTOMER c ON f.customer_id = c.CustomerID WHERE f.status IN ('pending', 'read') ORDER BY f.created_at DESC")->fetchAll();

// Get staff messages to admin
$staffMessages = $pdo->prepare("SELECT * FROM staff_messages WHERE staff_id = ? ORDER BY created_at DESC");
$staffMessages->execute([$staffId]);
$myMessages = $staffMessages->fetchAll();

// Handle KYC verification
if(isset($_GET['verify'])) {
    $pdo->prepare("UPDATE kyc_verifications SET status = 'verified', verified_at = NOW() WHERE customer_id = ?")->execute([$_GET['verify']]);
    $pdo->prepare("INSERT INTO notifications (customer_id, title, message, type) VALUES (?, 'KYC Verified', 'Your KYC has been verified! You can now access all banking features.', 'success')")->execute([$_GET['verify']]);
    setToast("KYC verified successfully", "success");
    redirect('dashboard.php');
}

// Handle feedback reply
if(isset($_POST['reply_feedback'])) {
    $feedbackId = $_POST['feedback_id'];
    $reply = $_POST['reply_message'];
    
    $pdo->prepare("UPDATE feedback SET staff_reply = ?, status = 'replied', replied_by = ?, replied_at = NOW() WHERE feedback_id = ?")->execute([$reply, $staffId, $feedbackId]);
    
    // Get customer ID for notification
    $fb = $pdo->prepare("SELECT customer_id FROM feedback WHERE feedback_id = ?");
    $fb->execute([$feedbackId]);
    $customerId = $fb->fetchColumn();
    
    $pdo->prepare("INSERT INTO notifications (customer_id, title, message, type) VALUES (?, 'Response to your feedback', 'Staff has responded to your feedback: ' || ?, 'info')")->execute([$customerId, substr($reply, 0, 100)]);
    
    setToast("Reply sent to customer", "success");
    redirect('dashboard.php');
}

// Handle message to admin
if(isset($_POST['send_to_admin'])) {
    $subject = $_POST['subject'];
    $message = $_POST['message'];
    $type = $_POST['type'];
    
    $pdo->prepare("INSERT INTO staff_messages (staff_id, subject, message, type, status) VALUES (?, ?, ?, ?, 'pending')")->execute([$staffId, $subject, $message, $type]);
    setToast("Message sent to admin", "success");
    redirect('dashboard.php');
}

// Handle admin reply check
$adminReplies = $pdo->prepare("SELECT * FROM staff_messages WHERE staff_id = ? AND status IN ('approved', 'rejected') AND admin_reply IS NOT NULL");
$adminReplies->execute([$staffId]);
$adminMessages = $adminReplies->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Asha Bank</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --font-sans: 'Inter', sans-serif; --bg-primary: #FFFFFF; --bg-secondary: #F8FAFC; --text-primary: #0F172A; --text-secondary: #475569; --border-color: #E2E8F0; --accent: #185FA5; --accent-bg: #E6F1FB; --success: #3B6D11; --warning: #BA7517; --danger: #A32D2D; }
        body.dark { --bg-primary: #0F172A; --bg-secondary: #1E293B; --text-primary: #F1F5F9; --border-color: #334155; --accent: #3B82F6; }
        body { font-family: var(--font-sans); background: var(--bg-secondary); color: var(--text-primary); transition: all 0.3s ease; }
        
        .navbar { background: var(--bg-primary); border-bottom: 1px solid var(--border-color); padding: 16px 5%; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; }
        .logo { display: flex; align-items: center; gap: 12px; }
        .navbar-right { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
        .theme-toggle { background: var(--bg-secondary); border: 1px solid var(--border-color); border-radius: 40px; padding: 8px 16px; cursor: pointer; }
        
        .container { max-width: 1400px; margin: 0 auto; padding: 24px; }
        
        /* Tabs */
        .staff-tabs {
            display: flex;
            gap: 8px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 60px;
            padding: 6px;
            margin-bottom: 24px;
        }
        .staff-tab {
            flex: 1;
            text-align: center;
            padding: 10px;
            border-radius: 40px;
            cursor: pointer;
            font-weight: 500;
            font-size: 14px;
            color: var(--text-secondary);
        }
        .staff-tab.active {
            background: var(--accent);
            color: white;
        }
        
        .tab-content { display: none; }
        .tab-content.active { display: block; }
        
        .stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; }
        .stat-value { font-size: 28px; font-weight: 700; }
        
        .glass-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 20px; padding: 24px; margin-bottom: 24px; }
        .card-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color); }
        
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); }
        th { color: var(--text-tertiary); font-weight: 500; font-size: 12px; }
        
        .btn { padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-success { background: var(--success); color: white; }
        .btn-primary { background: var(--accent); color: white; }
        
        .feedback-item { padding: 12px; border-bottom: 1px solid var(--border-color); }
        .feedback-subject { font-weight: 600; }
        .feedback-reply { margin-top: 8px; display: none; }
        .feedback-reply textarea { width: 100%; padding: 8px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--bg-secondary); margin-top: 8px; }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
        }
        .modal-content {
            background: var(--bg-primary);
            border-radius: 20px;
            padding: 24px;
            max-width: 500px;
            width: 90%;
        }
        .modal-content input, .modal-content textarea, .modal-content select {
            width: 100%;
            padding: 10px;
            margin-bottom: 12px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background: var(--bg-secondary);
            color: var(--text-primary);
        }
        
        @media (max-width: 768px) {
            .navbar { flex-direction: column; }
            .stat-row { grid-template-columns: 1fr; }
            .staff-tabs { flex-wrap: wrap; border-radius: 16px; }
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="logo"><div class="logo-icon"><svg width="20" height="20" viewBox="0 0 16 16"><rect x="1" y="6" width="14" height="9" rx="1.5" fill="none" stroke="var(--accent)" stroke-width="1.2"/><path d="M4 6V4a4 4 0 0 1 8 0v2" stroke="var(--accent)" stroke-width="1.2"/><circle cx="8" cy="10.5" r="1.5" fill="var(--accent)"/></svg></div><h2>Asha Bank Staff</h2></div>
        <div class="navbar-right">
            <button class="theme-toggle" id="themeToggle"><i class="fas fa-moon"></i> Dark</button>
            <a href="../logout.php" class="btn" style="background: var(--danger); color: white; padding: 8px 16px; border-radius: 40px; text-decoration: none;">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="stat-row">
            <div class="stat-card"><div class="stat-label">Pending KYC</div><div class="stat-value"><?= $pendingKYC ?></div></div>
            <div class="stat-card"><div class="stat-label">Today's Transactions</div><div class="stat-value"><?= $todayTrans ?></div></div>
            <div class="stat-card"><div class="stat-label">Pending Feedback</div><div class="stat-value"><?= count($pendingFeedback) ?></div></div>
        </div>
        
        <div class="staff-tabs">
            <div class="staff-tab active" data-tab="kyc">📋 KYC Verification</div>
            <div class="staff-tab" data-tab="feedback">💬 Customer Feedback</div>
            <div class="staff-tab" data-tab="messages">📨 Messages to Admin</div>
        </div>
        
        <!-- KYC Tab -->
        <div id="kycTab" class="tab-content active">
            <div class="glass-card">
                <h3><i class="fas fa-id-card"></i> Pending KYC Verification</h3>
                <?php if(empty($pendingKYCCustomers)): ?>
                    <p>No pending KYC requests</p>
                <?php else: ?>
                    <div style="overflow-x: auto">
                        <table>
                            <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>NID</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php foreach($pendingKYCCustomers as $c): ?>
                                <tr>
                                    <td><?= $c['customer_id'] ?></td>
                                    <td><?= $c['FirstName'].' '.$c['LastName'] ?></td>
                                    <td><?= $c['Email'] ?></td>
                                    <td><?= $c['Phone'] ?></td>
                                    <td><?= $c['nid_number'] ?? 'N/A' ?></td>
                                    <td><a href="?verify=<?= $c['customer_id'] ?>" class="btn btn-success" onclick="return confirm('Verify KYC?')">Verify</a></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Feedback Tab -->
        <div id="feedbackTab" class="tab-content">
            <div class="glass-card">
                <h3><i class="fas fa-comments"></i> Customer Feedback & Issues</h3>
                <?php if(empty($pendingFeedback)): ?>
                    <p>No pending feedback</p>
                <?php else: ?>
                    <?php foreach($pendingFeedback as $fb): ?>
                    <div class="feedback-item">
                        <div class="feedback-subject">
                            <strong><?= htmlspecialchars($fb['subject']) ?></strong>
                            <span style="float:right; font-size:11px;"><?= $fb['created_at'] ?></span>
                        </div>
                        <div class="feedback-message"><?= nl2br(htmlspecialchars($fb['message'])) ?></div>
                        <div><small>From: <?= $fb['FirstName'].' '.$fb['LastName'] ?> (<?= $fb['type'] ?>)</small></div>
                        <button class="btn btn-primary" onclick="toggleReplyForm(<?= $fb['feedback_id'] ?>)">Reply</button>
                        <div id="replyForm-<?= $fb['feedback_id'] ?>" class="feedback-reply">
                            <form method="POST">
                                <input type="hidden" name="feedback_id" value="<?= $fb['feedback_id'] ?>">
                                <textarea name="reply_message" rows="3" placeholder="Write your response..." required></textarea>
                                <button type="submit" name="reply_feedback" class="btn btn-success">Send Reply</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Messages to Admin Tab -->
        <div id="messagesTab" class="tab-content">
            <div class="glass-card">
                <h3><i class="fas fa-envelope"></i> Messages to Admin</h3>
                <button class="btn btn-primary" onclick="openMessageModal()">+ New Message to Admin</button>
                
                <div style="margin-top: 20px;">
                    <h4>Sent Messages</h4>
                    <?php if(empty($myMessages)): ?>
                        <p>No messages sent</p>
                    <?php else: ?>
                        <?php foreach($myMessages as $msg): ?>
                        <div class="feedback-item">
                            <div><strong><?= htmlspecialchars($msg['subject']) ?></strong> <span style="font-size:11px;">(<?= $msg['status'] ?>)</span></div>
                            <div><?= nl2br(htmlspecialchars($msg['message'])) ?></div>
                            <?php if($msg['admin_reply']): ?>
                                <div class="reply" style="background:var(--accent-bg); padding:8px; border-radius:8px; margin-top:8px;">
                                    <strong>Admin Reply:</strong> <?= nl2br(htmlspecialchars($msg['admin_reply'])) ?>
                                </div>
                            <?php endif; ?>
                            <small><?= $msg['created_at'] ?></small>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Message Modal -->
    <div id="messageModal" class="modal">
        <div class="modal-content">
            <h3>Send Message to Admin</h3>
            <form method="POST">
                <select name="type" required>
                    <option value="suggestion">💡 Suggestion</option>
                    <option value="request">📋 Request</option>
                    <option value="report">📊 Report</option>
                    <option value="issue">⚠️ Issue</option>
                </select>
                <input type="text" name="subject" placeholder="Subject" required>
                <textarea name="message" rows="5" placeholder="Your message..." required></textarea>
                <button type="submit" name="send_to_admin" class="btn btn-primary">Send to Admin</button>
                <button type="button" class="btn" style="margin-top:8px;" onclick="closeMessageModal()">Cancel</button>
            </form>
        </div>
    </div>
    
    <script>
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
        });
        
        // Tab switching
        document.querySelectorAll('.staff-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                document.querySelectorAll('.staff-tab').forEach(t => t.classList.remove('active'));
                this.classList.add('active');
                const tabName = this.dataset.tab;
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.getElementById(tabName + 'Tab').classList.add('active');
            });
        });
        
        function toggleReplyForm(id) {
            const form = document.getElementById('replyForm-' + id);
            form.style.display = form.style.display === 'none' ? 'block' : 'none';
        }
        
        function openMessageModal() {
            document.getElementById('messageModal').style.display = 'flex';
        }
        
        function closeMessageModal() {
            document.getElementById('messageModal').style.display = 'none';
        }
        
        window.onclick = function(e) {
            if(e.target === document.getElementById('messageModal')) closeMessageModal();
        }
    </script>
</body>
</html>