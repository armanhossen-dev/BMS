<?php
require_once 'config/db.php';
requireLogin();
$pageTitle = 'Reports';

// Stats for reports
$totalCustomers   = $conn->query("SELECT COUNT(*) as c FROM customers")->fetch_assoc()['c'];
$activeCustomers  = $conn->query("SELECT COUNT(*) as c FROM customers WHERE status='active'")->fetch_assoc()['c'];
$totalAccounts    = $conn->query("SELECT COUNT(*) as c FROM accounts")->fetch_assoc()['c'];
$totalBalance     = $conn->query("SELECT COALESCE(SUM(balance),0) as t FROM accounts WHERE status='active'")->fetch_assoc()['t'];
$totalDeposits    = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM transactions WHERE type='deposit'")->fetch_assoc()['t'];
$totalWithdrawals = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM transactions WHERE type='withdrawal'")->fetch_assoc()['t'];
$totalTransfers   = $conn->query("SELECT COALESCE(SUM(amount),0) as t FROM transactions WHERE type='transfer_out'")->fetch_assoc()['t'];
$totalLoans       = $conn->query("SELECT COUNT(*) as c FROM loans")->fetch_assoc()['c'];
$activeLoans      = $conn->query("SELECT COUNT(*) as c FROM loans WHERE status='active'")->fetch_assoc()['c'];
$totalLoanAmt     = $conn->query("SELECT COALESCE(SUM(principal_amount),0) as t FROM loans WHERE status IN ('active','closed')")->fetch_assoc()['t'];

// Account type breakdown
$accBreakdown = $conn->query("SELECT account_type, COUNT(*) as cnt, SUM(balance) as total FROM accounts GROUP BY account_type");

// Monthly transaction summary
$monthlyData = $conn->query("
    SELECT
        DATE_FORMAT(created_at, '%b %Y') as month,
        SUM(CASE WHEN type='deposit' THEN amount ELSE 0 END) as deposits,
        SUM(CASE WHEN type='withdrawal' THEN amount ELSE 0 END) as withdrawals,
        COUNT(*) as total_txns
    FROM transactions
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY created_at DESC
    LIMIT 6
");

// Top accounts by balance
$topAccounts = $conn->query("
    SELECT a.account_number, a.balance, a.account_type, c.full_name
    FROM accounts a
    JOIN customers c ON a.customer_id = c.id
    WHERE a.status = 'active'
    ORDER BY a.balance DESC
    LIMIT 10
");

// Recent loan applications
$recentLoans = $conn->query("
    SELECT l.*, c.full_name FROM loans l
    JOIN customers c ON l.customer_id = c.id
    ORDER BY l.applied_date DESC LIMIT 5
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports – Apex Bank</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="page-header" style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:10px;">
    <div>
        <h2>📊 Reports & Analytics</h2>
        <p>Comprehensive financial reports and data insights.</p>
    </div>
    <button class="btn btn-outline" id="printReport">🖨 Print Report</button>
</div>

<!-- SUMMARY STATS -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-icon gold">👥</div>
        <div class="stat-value"><?php echo $totalCustomers; ?></div>
        <div class="stat-label">Total Customers</div>
        <div class="stat-change up">↑ <?php echo $activeCustomers; ?> active</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon green">⬆</div>
        <div class="stat-value" style="font-size:1rem;"><?php echo formatCurrency($totalDeposits); ?></div>
        <div class="stat-label">Total Deposits</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon red">⬇</div>
        <div class="stat-value" style="font-size:1rem;"><?php echo formatCurrency($totalWithdrawals); ?></div>
        <div class="stat-label">Total Withdrawals</div>
    </div>
    <div class="stat-card">
        <div class="stat-icon blue">🏦</div>
        <div class="stat-value" style="font-size:1rem;"><?php echo formatCurrency($totalLoanAmt); ?></div>
        <div class="stat-label">Total Loans Issued</div>
        <div class="stat-change up">↑ <?php echo $activeLoans; ?> active</div>
    </div>
</div>

<div class="grid-2">
    <!-- ACCOUNT BREAKDOWN -->
    <div class="card">
        <div class="card-header"><h4>Account Type Breakdown</h4></div>
        <div class="card-body">
            <?php
            $colors = ['savings'=>'#D4AF37','current'=>'#3498DB','fixed_deposit'=>'#2ECC71','loan'=>'#E74C3C'];
            while ($row = $accBreakdown->fetch_assoc()):
                $color = $colors[$row['account_type']] ?? '#888';
                $pct = $totalAccounts > 0 ? round(($row['cnt']/$totalAccounts)*100) : 0;
            ?>
            <div style="margin-bottom:16px;">
                <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                    <span style="font-size:0.83rem;text-transform:capitalize;"><?php echo str_replace('_',' ',$row['account_type']); ?></span>
                    <span style="font-size:0.83rem;color:#888;"><?php echo $row['cnt']; ?> accs · <?php echo formatCurrency($row['total']); ?></span>
                </div>
                <div style="height:8px;background:var(--black-5);border-radius:10px;overflow:hidden;">
                    <div style="height:100%;width:<?php echo $pct; ?>%;background:<?php echo $color; ?>;border-radius:10px;transition:width 0.5s;"></div>
                </div>
                <div style="font-size:0.7rem;color:#666;margin-top:3px;"><?php echo $pct; ?>%</div>
            </div>
            <?php endwhile; ?>
        </div>
    </div>

    <!-- MONTHLY SUMMARY TABLE -->
    <div class="card">
        <div class="card-header"><h4>Monthly Summary</h4></div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr>
                        <th>Month</th>
                        <th>Deposits</th>
                        <th>Withdrawals</th>
                        <th>Transactions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($m = $monthlyData->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo $m['month']; ?></td>
                        <td style="color:#2ECC71;font-family:'Cinzel',serif;font-size:0.82rem;"><?php echo formatCurrency($m['deposits']); ?></td>
                        <td style="color:#E74C3C;font-family:'Cinzel',serif;font-size:0.82rem;"><?php echo formatCurrency($m['withdrawals']); ?></td>
                        <td><?php echo $m['total_txns']; ?></td>
                    </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<div class="grid-2">
    <!-- TOP ACCOUNTS -->
    <div class="card">
        <div class="card-header"><h4>🏆 Top Accounts by Balance</h4></div>
        <div class="table-wrapper">
            <table>
                <thead>
                    <tr><th>Rank</th><th>Account</th><th>Customer</th><th>Balance</th></tr>
                </thead>
                <tbody>
                    <?php $rank = 1; while ($a = $topAccounts->fetch_assoc()): ?>
                    <tr>
                        <td>
                            <span style="color:<?php echo $rank<=3?'#D4AF37':'#888'; ?>;font-family:'Cinzel',serif;">
                                <?php echo $rank <= 3 ? ['🥇','🥈','🥉'][$rank-1] : "#$rank"; ?>
                            </span>
                        </td>
                        <td style="font-size:0.8rem;color:#D4AF37;"><?php echo $a['account_number']; ?></td>
                        <td style="font-size:0.85rem;"><?php echo htmlspecialchars($a['full_name']); ?></td>
                        <td style="font-family:'Cinzel',serif;color:#D4AF37;font-size:0.85rem;"><?php echo formatCurrency($a['balance']); ?></td>
                    </tr>
                    <?php $rank++; endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- FINANCIAL OVERVIEW -->
    <div class="card">
        <div class="card-header"><h4>💰 Financial Overview</h4></div>
        <div class="card-body">
            <?php
            $items = [
                ['label'=>'Total Deposits', 'value'=>formatCurrency($totalDeposits), 'color'=>'#2ECC71'],
                ['label'=>'Total Withdrawals', 'value'=>formatCurrency($totalWithdrawals), 'color'=>'#E74C3C'],
                ['label'=>'Total Transfers', 'value'=>formatCurrency($totalTransfers), 'color'=>'#3498DB'],
                ['label'=>'Total Loan Portfolio', 'value'=>formatCurrency($totalLoanAmt), 'color'=>'#D4AF37'],
                ['label'=>'Net Deposits', 'value'=>formatCurrency($totalDeposits - $totalWithdrawals), 'color'=>'#F5D76E'],
                ['label'=>'Total Funds Under Management', 'value'=>formatCurrency($totalBalance), 'color'=>'#D4AF37'],
            ];
            foreach ($items as $item): ?>
            <div style="display:flex;justify-content:space-between;padding:11px 0;border-bottom:1px solid rgba(212,175,55,0.08);">
                <span style="font-size:0.82rem;color:#888;"><?php echo $item['label']; ?></span>
                <span style="font-family:'Cinzel',serif;font-size:0.88rem;color:<?php echo $item['color']; ?>;"><?php echo $item['value']; ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

    </div>
</div>
</div>
<script src="js/main.js"></script>
</body>
</html>