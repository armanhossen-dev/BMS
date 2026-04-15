<?php
require_once '../config/db.php';

if(!isset($_SESSION['role']) || $_SESSION['role'] != 'admin') {
    redirect('../login.php');
}

$totalCustomers = $pdo->query("SELECT COUNT(*) FROM CUSTOMER")->fetchColumn();
$totalBalance = $pdo->query("SELECT SUM(AvailableBalance) FROM ACCOUNT")->fetchColumn();
$totalTransactions = $pdo->query("SELECT COUNT(*) FROM TRANSACTION")->fetchColumn();

$customers = $pdo->query("SELECT * FROM CUSTOMER ORDER BY CreatedAt DESC LIMIT 15")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin - Asha Bank</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
        :root { --bg1: #eef2f5; --bg2: #d9e2ec; --glass: rgba(255,255,255,0.45); --text: #1a2c3e; --accent: #c0392b; }
        body.dark { --bg1: #0f172a; --bg2: #1e293b; --glass: rgba(15,23,42,0.6); --text: #e2e8f0; }
        body { background: linear-gradient(135deg, var(--bg1), var(--bg2)); color: var(--text); }
        .navbar { display: flex; justify-content: space-between; padding: 1rem 5%; background: var(--glass); backdrop-filter: blur(12px); }
        .container { max-width: 1280px; margin: 0 auto; padding: 2rem; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1.5rem; margin-top: 2rem; }
        .stat-card { background: var(--glass); backdrop-filter: blur(8px); border-radius: 20px; padding: 1.5rem; text-align: center; }
        .stat-value { font-size: 2rem; font-weight: 700; }
        .glass-card { background: var(--glass); backdrop-filter: blur(12px); border-radius: 28px; padding: 1.5rem; margin-top: 2rem; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 0.75rem; text-align: left; border-bottom: 1px solid rgba(0,0,0,0.1); }
        .btn { padding: 0.5rem 1rem; border-radius: 40px; background: var(--accent); color: white; text-decoration: none; }
    </style>
</head>
<body>
    <nav class="navbar">
        <div><i class="fas fa-university"></i> Asha Bank <span style="color:#c0392b;">Admin</span></div>
        <div><button id="themeToggle">🌓</button> <a href="../logout.php" class="btn">Logout</a></div>
    </nav>
    <div class="container">
        <div class="dashboard-grid">
            <div class="stat-card"><i class="fas fa-users"></i><div class="stat-value"><?= $totalCustomers ?></div><div>Customers</div></div>
            <div class="stat-card"><i class="fas fa-money-bill"></i><div class="stat-value"><?= formatBDT($totalBalance) ?></div><div>Total Balance</div></div>
            <div class="stat-card"><i class="fas fa-exchange-alt"></i><div class="stat-value"><?= $totalTransactions ?></div><div>Transactions</div></div>
        </div>
        <div class="glass-card"><h3>All Customers</h3><table><thead><tr><th>ID</th><th>Name</th><th>Email</th><th>Phone</th></tr></thead><tbody><?php foreach($customers as $c): ?><tr><td><?= $c['CustomerID'] ?></td><td><?= $c['FirstName'] . ' ' . $c['LastName'] ?></td><td><?= $c['Email'] ?></td><td><?= $c['Phone'] ?></td></tr><?php endforeach; ?></tbody></table></div>
    </div>
    <script>
        document.getElementById('themeToggle')?.addEventListener('click',()=>{document.body.classList.toggle('dark');localStorage.setItem('theme',document.body.classList.contains('dark')?'dark':'light');});
        if(localStorage.getItem('theme')==='dark') document.body.classList.add('dark');
    </script>
</body>
</html>