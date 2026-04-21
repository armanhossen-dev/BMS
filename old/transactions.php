<?php
require_once 'config/db.php';
requireLogin();
$pageTitle = 'Transactions';

// FILTERS
$type   = isset($_GET['type']) ? $conn->real_escape_string($_GET['type']) : '';
$date   = isset($_GET['date']) ? $conn->real_escape_string($_GET['date']) : '';
$search = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';

$where = "WHERE 1=1";
if ($type)   $where .= " AND t.type = '$type'";
if ($date)   $where .= " AND DATE(t.created_at) = '$date'";
if ($search) $where .= " AND (t.transaction_id LIKE '%$search%' OR c.full_name LIKE '%$search%' OR a.account_number LIKE '%$search%')";

$transactions = $conn->query("
    SELECT t.*, a.account_number, c.full_name
    FROM transactions t
    JOIN accounts a ON t.account_id = a.id
    JOIN customers c ON a.customer_id = c.id
    $where
    ORDER BY t.created_at DESC
    LIMIT 200
");

$typeLabels = [
    'deposit' => ['label'=>'Deposit', 'badge'=>'success', 'sign'=>'+'],
    'withdrawal' => ['label'=>'Withdrawal', 'badge'=>'danger', 'sign'=>'-'],
    'transfer_in' => ['label'=>'Transfer In', 'badge'=>'info', 'sign'=>'+'],
    'transfer_out' => ['label'=>'Transfer Out', 'badge'=>'warning', 'sign'=>'-'],
    'loan_disbursement' => ['label'=>'Loan Disbursed', 'badge'=>'gold', 'sign'=>'+'],
    'loan_repayment' => ['label'=>'Loan Repayment', 'badge'=>'gray', 'sign'=>'-'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions – Apex Bank</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="page-header">
    <h2>≡ Transaction History</h2>
    <p>Complete record of all financial transactions.</p>
</div>

<!-- FILTERS -->
<form method="GET">
    <div class="filter-bar">
        <div class="search-box" style="flex:1;">
            <span class="search-icon">🔍</span>
            <input type="text" name="search" placeholder="Search TXN ID, name, account..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <select name="type" class="form-control" style="width:180px;">
            <option value="">All Types</option>
            <option value="deposit" <?php echo $type==='deposit'?'selected':''; ?>>Deposit</option>
            <option value="withdrawal" <?php echo $type==='withdrawal'?'selected':''; ?>>Withdrawal</option>
            <option value="transfer_in" <?php echo $type==='transfer_in'?'selected':''; ?>>Transfer In</option>
            <option value="transfer_out" <?php echo $type==='transfer_out'?'selected':''; ?>>Transfer Out</option>
            <option value="loan_disbursement" <?php echo $type==='loan_disbursement'?'selected':''; ?>>Loan Disbursement</option>
            <option value="loan_repayment" <?php echo $type==='loan_repayment'?'selected':''; ?>>Loan Repayment</option>
        </select>
        <input type="date" name="date" class="form-control" style="width:160px;" value="<?php echo htmlspecialchars($date); ?>">
        <button type="submit" class="btn btn-gold">Filter</button>
        <a href="transactions.php" class="btn btn-outline">Reset</a>
        <button type="button" class="btn btn-outline" id="printReport">🖨 Print</button>
    </div>
</form>

<div class="card">
    <div class="card-header">
        <h4>All Transactions</h4>
        <span class="text-muted"><?php echo $transactions->num_rows; ?> record(s)</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Transaction ID</th>
                    <th>Date & Time</th>
                    <th>Account</th>
                    <th>Customer</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Balance After</th>
                    <th>Description</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($transactions->num_rows > 0):
                    $i = 1;
                    while ($t = $transactions->fetch_assoc()):
                        $tinfo = $typeLabels[$t['type']] ?? ['label'=>$t['type'],'badge'=>'gray','sign'=>''];
                        $isCredit = in_array($t['type'], ['deposit','transfer_in','loan_disbursement']);
                ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td style="font-size:0.73rem;color:#D4AF37;font-family:'Courier New',monospace;"><?php echo $t['transaction_id']; ?></td>
                    <td style="font-size:0.8rem;">
                        <div><?php echo date('d M Y', strtotime($t['created_at'])); ?></div>
                        <div style="color:#888;"><?php echo date('H:i:s', strtotime($t['created_at'])); ?></div>
                    </td>
                    <td style="font-size:0.82rem;color:#aaa;"><?php echo $t['account_number']; ?></td>
                    <td><?php echo htmlspecialchars($t['full_name']); ?></td>
                    <td><span class="badge badge-<?php echo $tinfo['badge']; ?>"><?php echo $tinfo['label']; ?></span></td>
                    <td style="font-family:'Cinzel',serif;color:<?php echo $isCredit ? '#2ECC71' : '#E74C3C'; ?>;">
                        <?php echo $tinfo['sign'] . formatCurrency($t['amount']); ?>
                    </td>
                    <td style="font-family:'Cinzel',serif;color:#D4AF37;"><?php echo formatCurrency($t['balance_after']); ?></td>
                    <td style="font-size:0.8rem;color:#888;max-width:200px;"><?php echo htmlspecialchars($t['description'] ?? '—'); ?></td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="9" style="text-align:center;padding:40px;color:#888;">No transactions found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

    </div>
</div>
</div>
<script src="js/main.js"></script>
</body>
</html>