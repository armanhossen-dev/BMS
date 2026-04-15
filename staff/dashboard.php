<?php
require_once '../config/db.php';

// Check if user is staff
if(!isset($_SESSION['role']) || $_SESSION['role'] != 'staff') {
    redirect('../login.php');
}

// Get statistics
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM CUSTOMER")->fetchColumn();
$pendingKYC = $pdo->query("SELECT COUNT(*) FROM CUSTOMERKYC WHERE KYCStatus = 'Pending'")->fetchColumn();
$todayTransactions = $pdo->query("SELECT COUNT(*) FROM TRANSACTION WHERE DATE(TransactionDate) = CURDATE()")->fetchColumn();

// Get recent customers
$customers = $pdo->query("SELECT * FROM CUSTOMER ORDER BY CreatedAt DESC LIMIT 10")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Dashboard - Asha Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Inter', sans-serif;
        }
        :root {
            --bg-gradient-start: #eef2f5;
            --bg-gradient-end: #d9e2ec;
            --glass-bg: rgba(255, 255, 255, 0.45);
            --glass-border: rgba(255, 255, 255, 0.6);
            --text-primary: #1a2c3e;
            --accent: #e67e22;
        }
        body.dark {
            --bg-gradient-start: #0f172a;
            --bg-gradient-end: #1e293b;
            --glass-bg: rgba(15, 23, 42, 0.6);
            --text-primary: #e2e8f0;
        }
        body {
            background: linear-gradient(135deg, var(--bg-gradient-start), var(--bg-gradient-end));
            color: var(--text-primary);
        }
        .navbar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 5%;
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--glass-border);
        }
        .navbar-menu {
            display: flex;
            gap: 1rem;
            align-items: center;
        }
        .btn, .btn-danger {
            padding: 0.5rem 1rem;
            border-radius: 40px;
            text-decoration: none;
        }
        .btn { background: var(--accent); color: white; }
        .btn-danger { background: #c0392b; color: white; }
        .container { max-width: 1280px; margin: 0 auto; padding: 2rem; }
        .dashboard-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-top: 2rem;
        }
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: blur(8px);
            border-radius: 20px;
            padding: 1.5rem;
            text-align: center;
        }
        .stat-value { font-size: 2rem; font-weight: 700; }
        .glass-card {
            background: var(--glass-bg);
            backdrop-filter: blur(12px);
            border-radius: 28px;
            padding: 1.5rem;
            margin-top: 2rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid var(--glass-border);
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <div class="navbar-brand"><i class="fas fa-university"></i> Asha Bank <span style="color:#e67e22;">Staff</span></div>
        <div class="navbar-menu">
            <span><i class="fas fa-user-tie"></i> <?= htmlspecialchars($_SESSION['username']) ?></span>
            <button id="themeToggle" class="btn">🌓</button>
            <a href="../logout.php" class="btn-danger">Logout</a>
        </div>
    </nav>
    
    <div class="container">
        <div class="dashboard-grid">
            <div class="stat-card"><i class="fas fa-users"></i><div class="stat-value"><?= $totalCustomers ?></div><div>Total Customers</div></div>
            <div class="stat-card"><i class="fas fa-id-card"></i><div class="stat-value"><?= $pendingKYC ?></div><div>Pending KYC</div></div>
            <div class="stat-card"><i class="fas fa-exchange-alt"></i><div class="stat-value"><?= $todayTransactions ?></div><div>Today's Transactions</div></div>
        </div>
        
        <div class="glass-card">
            <h3>Recent Customer Registrations</h3>
            <table>
                <thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th><th>Date</th></tr></thead>
                <tbody>
                    <?php foreach($customers as $c): ?>
                    <tr><td><?= $c['CustomerID'] ?></td><td><?= $c['FirstName'] . ' ' . $c['LastName'] ?></td><td><?= $c['Email'] ?></td><td><?= $c['Phone'] ?></td><td><?= date('d M Y', strtotime($c['CreatedAt'])) ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        document.getElementById('themeToggle')?.addEventListener('click',()=>{document.body.classList.toggle('dark');localStorage.setItem('theme',document.body.classList.contains('dark')?'dark':'light');});
        if(localStorage.getItem('theme')==='dark') document.body.classList.add('dark');
    </script>
</body>
</html>