<?php
require_once 'config/db.php';
requireLogin();
$pageTitle = 'Dashboard';

// STATS
$totalCustomers = $conn->query("SELECT COUNT(*) as cnt FROM customers WHERE status='active'")->fetch_assoc()['cnt'];
$totalAccounts  = $conn->query("SELECT COUNT(*) as cnt FROM accounts WHERE status='active'")->fetch_assoc()['cnt'];
$totalBalance   = $conn->query("SELECT SUM(balance) as total FROM accounts WHERE status='active'")->fetch_assoc()['total'] ?? 0;
$totalLoans     = $conn->query("SELECT COUNT(*) as cnt FROM loans WHERE status='active'")->fetch_assoc()['cnt'];
$todayDeposit   = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE type='deposit' AND DATE(created_at)=CURDATE()")->fetch_assoc()['total'];
$todayWithdraw  = $conn->query("SELECT COALESCE(SUM(amount),0) as total FROM transactions WHERE type='withdrawal' AND DATE(created_at)=CURDATE()")->fetch_assoc()['total'];

// RECENT TRANSACTIONS
$recentTxns = $conn->query("
    SELECT t.*, a.account_number, c.full_name
    FROM transactions t
    JOIN accounts a ON t.account_id = a.id
    JOIN customers c ON a.customer_id = c.id
    ORDER BY t.created_at DESC LIMIT 8
");

// RECENT CUSTOMERS
$recentCustomers = $conn->query("SELECT * FROM customers ORDER BY created_at DESC LIMIT 5");

// MONTHLY CHART DATA - last 6 months from DB
$chartData = [];
for ($i = 5; $i >= 0; $i--) {
    $month     = date('Y-m', strtotime("-$i months"));
    $label     = date('M Y', strtotime("-$i months"));
    $deposits  = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM transactions WHERE type='deposit' AND DATE_FORMAT(created_at,'%Y-%m')='$month'")->fetch_assoc()['t'];
    $withdraws = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM transactions WHERE type='withdrawal' AND DATE_FORMAT(created_at,'%Y-%m')='$month'")->fetch_assoc()['t'];
    $transfers = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM transactions WHERE type='transfer_out' AND DATE_FORMAT(created_at,'%Y-%m')='$month'")->fetch_assoc()['t'];
    $chartData[] = [
        'label'     => $label,
        'deposits'  => (float)$deposits,
        'withdraws' => (float)$withdraws,
        'transfers' => (float)$transfers,
    ];
}
$chartLabels   = json_encode(array_column($chartData, 'label'));
$chartDeposits = json_encode(array_column($chartData, 'deposits'));
$chartWithdraw = json_encode(array_column($chartData, 'withdraws'));
$chartTransfer = json_encode(array_column($chartData, 'transfers'));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard – Apex Bank</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="page-header">
    <h2>📊 Dashboard Overview</h2>
    <p>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>. Here's what's happening today.</p>
</div>

<!-- STAT CARDS -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon gold">👤</div>
        <div class="stat-value"><?php echo number_format($totalCustomers); ?></div>
        <div class="stat-label">Active Customers</div>
        <div class="stat-change up">↑ Total registered</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">💳</div>
        <div class="stat-value"><?php echo number_format($totalAccounts); ?></div>
        <div class="stat-label">Active Accounts</div>
        <div class="stat-change up">↑ All types</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">💰</div>
        <div class="stat-value" style="font-size:1.2rem;"><?php echo formatCurrency($totalBalance); ?></div>
        <div class="stat-label">Total Balance</div>
        <div class="stat-change up">↑ All accounts</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">🏦</div>
        <div class="stat-value"><?php echo number_format($totalLoans); ?></div>
        <div class="stat-label">Active Loans</div>
        <div class="stat-change down">↓ Under review</div>
    </div>
</div>

<!-- TODAY SUMMARY -->
<div class="grid-2" style="margin-bottom:24px;">
    <div class="card">
        <div class="card-header">
            <h4>📅 Today's Summary</h4>
            <span class="text-muted"><?php echo date('d M Y'); ?></span>
        </div>
        <div class="card-body">
            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;border-bottom:1px solid rgba(212,175,55,0.1);">
                <div>
                    <div style="font-size:0.75rem;color:#888;text-transform:uppercase;">Total Deposits</div>
                    <div style="font-size:1.3rem;font-family:'Cinzel',serif;color:#2ECC71;margin-top:4px;"><?php echo formatCurrency($todayDeposit); ?></div>
                </div>
                <span style="font-size:2rem;">⬆</span>
            </div>
            <div style="display:flex;justify-content:space-between;align-items:center;padding:14px 0;">
                <div>
                    <div style="font-size:0.75rem;color:#888;text-transform:uppercase;">Total Withdrawals</div>
                    <div style="font-size:1.3rem;font-family:'Cinzel',serif;color:#E74C3C;margin-top:4px;"><?php echo formatCurrency($todayWithdraw); ?></div>
                </div>
                <span style="font-size:2rem;">⬇</span>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4>📈 Monthly Transactions</h4>
            <div style="display:flex;gap:14px;font-size:0.72rem;align-items:center;">
                <span style="color:#D4AF37;display:flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;background:#D4AF37;display:inline-block;border-radius:2px;"></span>Deposits</span>
                <span style="color:#E74C3C;display:flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;background:#E74C3C;display:inline-block;border-radius:2px;"></span>Withdrawals</span>
                <span style="color:#3498DB;display:flex;align-items:center;gap:5px;"><span style="width:10px;height:10px;background:#3498DB;display:inline-block;border-radius:2px;"></span>Transfers</span>
            </div>
        </div>
        <div class="card-body" style="padding:20px 16px 12px;">
            <div style="position:relative;height:220px;">
                <canvas id="transactionChart"></canvas>
            </div>
        </div>
    </div>
</div>

<!-- RECENT TRANSACTIONS + CUSTOMERS -->
<div class="grid-2">
    <div class="card">
        <div class="card-header">
            <h4>🔄 Recent Transactions</h4>
            <a href="transactions.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body" style="padding:8px 24px;">
            <?php if ($recentTxns->num_rows > 0):
                while ($txn = $recentTxns->fetch_assoc()):
                    $isCredit = in_array($txn['type'], ['deposit','transfer_in','loan_disbursement']);
            ?>
            <div class="txn-item">
                <div class="txn-icon <?php echo $isCredit ? 'credit' : 'debit'; ?>">
                    <?php echo $isCredit ? '⬆' : '⬇'; ?>
                </div>
                <div class="txn-info">
                    <h5><?php echo htmlspecialchars($txn['full_name']); ?></h5>
                    <span><?php echo htmlspecialchars($txn['account_number']); ?> &bull; <?php echo date('d M, H:i', strtotime($txn['created_at'])); ?></span>
                </div>
                <div class="txn-amount <?php echo $isCredit ? 'credit' : 'debit'; ?>">
                    <?php echo ($isCredit ? '+' : '-') . formatCurrency($txn['amount']); ?>
                </div>
            </div>
            <?php endwhile; else: ?>
            <p class="text-muted" style="padding:20px 0;text-align:center;">No transactions yet.</p>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header">
            <h4>👥 New Customers</h4>
            <a href="customers.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="card-body">
            <?php if ($recentCustomers->num_rows > 0):
                while ($cust = $recentCustomers->fetch_assoc()): ?>
            <div style="display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid rgba(255,255,255,0.04);">
                <div class="user-avatar" style="width:36px;height:36px;font-size:0.8rem;">
                    <?php echo strtoupper(substr($cust['full_name'], 0, 1)); ?>
                </div>
                <div style="flex:1;">
                    <div style="font-size:0.85rem;color:#fff;"><?php echo htmlspecialchars($cust['full_name']); ?></div>
                    <div style="font-size:0.73rem;color:#888;"><?php echo htmlspecialchars($cust['customer_id']); ?> &bull; <?php echo date('d M Y', strtotime($cust['created_at'])); ?></div>
                </div>
                <span class="badge badge-<?php echo $cust['status'] === 'active' ? 'success' : 'danger'; ?>">
                    <?php echo $cust['status']; ?>
                </span>
            </div>
            <?php endwhile; else: ?>
            <p class="text-muted" style="text-align:center;padding:20px 0;">No customers yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include 'includes/footer.php';
?>
    </div><!-- end main-content -->
</div><!-- end main-wrapper -->
</div><!-- end layout -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script src="js/main.js"></script>
<script>
(function() {
    const labels   = <?php echo $chartLabels; ?>;
    const deposits = <?php echo $chartDeposits; ?>;
    const withdraws= <?php echo $chartWithdraw; ?>;
    const transfers= <?php echo $chartTransfer; ?>;

    const ctx = document.getElementById('transactionChart').getContext('2d');

    // Gold gradient
    const goldGrad = ctx.createLinearGradient(0, 0, 0, 220);
    goldGrad.addColorStop(0, 'rgba(212,175,55,0.35)');
    goldGrad.addColorStop(1, 'rgba(212,175,55,0.02)');

    const redGrad = ctx.createLinearGradient(0, 0, 0, 220);
    redGrad.addColorStop(0, 'rgba(231,76,60,0.3)');
    redGrad.addColorStop(1, 'rgba(231,76,60,0.02)');

    const blueGrad = ctx.createLinearGradient(0, 0, 0, 220);
    blueGrad.addColorStop(0, 'rgba(52,152,219,0.25)');
    blueGrad.addColorStop(1, 'rgba(52,152,219,0.02)');

    new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [
                {
                    label: 'Deposits',
                    data: deposits,
                    backgroundColor: 'rgba(212,175,55,0.75)',
                    borderColor: '#D4AF37',
                    borderWidth: 1,
                    borderRadius: 5,
                    borderSkipped: false,
                },
                {
                    label: 'Withdrawals',
                    data: withdraws,
                    backgroundColor: 'rgba(231,76,60,0.75)',
                    borderColor: '#E74C3C',
                    borderWidth: 1,
                    borderRadius: 5,
                    borderSkipped: false,
                },
                {
                    label: 'Transfers',
                    data: transfers,
                    backgroundColor: 'rgba(52,152,219,0.75)',
                    borderColor: '#3498DB',
                    borderWidth: 1,
                    borderRadius: 5,
                    borderSkipped: false,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false,
            },
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#1A1A1A',
                    borderColor: 'rgba(212,175,55,0.4)',
                    borderWidth: 1,
                    titleColor: '#D4AF37',
                    bodyColor: '#ccc',
                    padding: 12,
                    callbacks: {
                        label: function(ctx) {
                            const val = ctx.parsed.y;
                            return ' ' + ctx.dataset.label + ': ৳ ' + val.toLocaleString('en-US', {minimumFractionDigits: 2});
                        }
                    }
                }
            },
            scales: {
                x: {
                    grid: {
                        color: 'rgba(212,175,55,0.06)',
                        drawBorder: false,
                    },
                    ticks: {
                        color: '#888',
                        font: { family: 'Raleway, sans-serif', size: 11 }
                    }
                },
                y: {
                    grid: {
                        color: 'rgba(212,175,55,0.07)',
                        drawBorder: false,
                    },
                    ticks: {
                        color: '#888',
                        font: { family: 'Raleway, sans-serif', size: 11 },
                        callback: function(val) {
                            if (val >= 1000000) return '৳' + (val/1000000).toFixed(1) + 'M';
                            if (val >= 1000)    return '৳' + (val/1000).toFixed(0) + 'K';
                            return '৳' + val;
                        }
                    }
                }
            }
        }
    });
})();
</script>
</body>
</html>