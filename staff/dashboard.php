<?php require_once '../config/db.php';
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'staff') redirect('../login.php');

$pendingKYC = $pdo->query("SELECT COUNT(*) FROM kyc_verifications WHERE status = 'pending'")->fetchColumn();
$todayTrans = $pdo->query("SELECT COUNT(*) FROM TRANSACTION WHERE DATE(TransactionDate) = CURDATE()")->fetchColumn();

$pendingKYCCustomers = $pdo->query("SELECT k.*, c.FirstName, c.LastName, c.Email, c.Phone FROM kyc_verifications k JOIN CUSTOMER c ON k.customer_id = c.CustomerID WHERE k.status = 'pending' LIMIT 20")->fetchAll();

if(isset($_GET['verify'])) {
    $pdo->prepare("UPDATE kyc_verifications SET status = 'verified', verified_at = NOW() WHERE customer_id = ?")->execute([$_GET['verify']]);
    $pdo->prepare("INSERT INTO notifications (customer_id, title, message, type) VALUES (?, 'KYC Verified', 'Your KYC has been verified! You can now access all banking features.', 'success')")->execute([$_GET['verify']]);
    setToast("KYC verified successfully", "success");
    redirect('dashboard.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Staff Dashboard - Asha Bank</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        :root { --font-sans: 'Inter', sans-serif; --bg-primary: #FFFFFF; --bg-secondary: #F8FAFC; --text-primary: #0F172A; --text-secondary: #475569; --border-color: #E2E8F0; --accent: #185FA5; --accent-bg: #E6F1FB; --success: #3B6D11; --warning: #BA7517; }
        body.dark { --bg-primary: #0F172A; --bg-secondary: #1E293B; --text-primary: #F1F5F9; --border-color: #334155; --accent: #3B82F6; }
        body { font-family: var(--font-sans); background: var(--bg-secondary); color: var(--text-primary); }
        .navbar { background: var(--bg-primary); border-bottom: 1px solid var(--border-color); padding: 16px 5%; display: flex; justify-content: space-between; }
        .container { max-width: 1200px; margin: 0 auto; padding: 24px; }
        .stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; margin-bottom: 24px; }
        .stat-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 16px; padding: 20px; }
        .stat-value { font-size: 28px; font-weight: 700; }
        .glass-card { background: var(--bg-primary); border: 1px solid var(--border-color); border-radius: 20px; padding: 24px; margin-bottom: 24px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid var(--border-color); }
        .btn { padding: 6px 12px; border-radius: 8px; border: none; cursor: pointer; text-decoration: none; display: inline-block; background: var(--success); color: white; }
        .btn-danger { background: #A32D2D; color: white; }
        @media (max-width: 768px) { .stat-row { grid-template-columns: 1fr; } }
    </style>
</head>
<body>
    <nav class="navbar">
        <div><i class="fas fa-university"></i> Asha Bank <span style="color:var(--warning)">Staff Portal</span></div>
        
        <div><button id="themeToggle" class="btn">🌓</button> <a href="../logout.php" class="btn-danger" style="padding: 6px 12px; border-radius: 8px;">Logout</a></div>
    </nav>
    <div class="container">
        <div class="stat-row">
            <div class="stat-card"><div class="stat-label">Pending KYC</div><div class="stat-value"><?= $pendingKYC ?></div></div>
            <div class="stat-card"><div class="stat-label">Today's Transactions</div><div class="stat-value"><?= $todayTrans ?></div></div>
            <div class="stat-card"><div class="stat-label">Active Customers</div><div class="stat-value"><?= $pdo->query("SELECT COUNT(*) FROM CUSTOMER WHERE IsActive=1")->fetchColumn() ?></div></div>
        </div>
        <div class="glass-card">
            <h3><i class="fas fa-id-card"></i> Pending KYC Verification</h3>
            <?php if(empty($pendingKYCCustomers)): ?><p>No pending KYC requests</p>
            <?php else: ?>
                <table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>NID</th><th>Action</th></tr></thead>
                <tbody><?php foreach($pendingKYCCustomers as $c): ?>
                    <tr><td><?= $c['customer_id'] ?></td><td><?= $c['FirstName'].' '.$c['LastName'] ?></td><td><?= $c['Email'] ?></td><td><?= $c['Phone'] ?></td><td><?= $c['nid_number'] ?? 'N/A' ?></td><td><a href="?verify=<?= $c['customer_id'] ?>" class="btn" onclick="return confirm('Verify KYC?')">Verify</a></td></tr>
                <?php endforeach; ?></tbody></table>
            <?php endif; ?>
        </div>
    </div>
    <script>let isDark=localStorage.getItem('theme')==='dark';function toggleTheme(){isDark=!isDark;if(isDark)document.body.classList.add('dark');else document.body.classList.remove('dark');localStorage.setItem('theme',isDark?'dark':'light');}document.getElementById('themeToggle').addEventListener('click',toggleTheme);if(localStorage.getItem('theme')==='dark')document.body.classList.add('dark');</script>
</body>
</html>