<?php
require_once 'config/db.php';
requireAuth('client');

$userId  = $_SESSION['user_id'];
$account = getUserAccount($pdo, $userId);
if (!$account) redirect('logout.php');

$pageTitle  = 'Transaction History';
$activePage = 'dashboard';

$typeFilter = sanitize($_GET['type'] ?? 'all');
$q          = sanitize($_GET['q'] ?? '');

$sql = "SELECT t.*, tt.TypeName 
        FROM TRANSACTION t
        JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
        WHERE (t.FromCustomerID = ? OR t.ToCustomerID = ?)";
$params = [$userId, $userId];

if ($typeFilter === 'credit') {
    $sql .= " AND t.ToCustomerID = ?";
    $params[] = $userId;
} elseif ($typeFilter === 'debit') {
    $sql .= " AND t.FromCustomerID = ?";
    $params[] = $userId;
}

if ($q) {
    $sql .= " AND (t.Description LIKE ? OR t.ReferenceNumber LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}

$sql .= " ORDER BY t.TransactionDate DESC LIMIT 100";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

include 'includes/header.php';
?>

<div class="page-content">

    <div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
            <h1>Transaction History</h1>
            <p>Complete statement of your account deposits, transfers, bill payments, and fees.</p>
        </div>
        <div style="display:flex;gap:10px;">
            <a href="dashboard.php" class="btn btn-outline btn-sm"><i class='bx bx-arrow-back'></i> Dashboard</a>
            <button onclick="window.print()" class="btn btn-secondary btn-sm"><i class='bx bx-printer'></i> Print Statement</button>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px;padding:16px 20px;">
        <form method="GET" action="transactions.php" style="display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:0;">
            <div style="flex:1;min-width:220px;">
                <input type="text" name="q" value="<?= e($q) ?>" class="form-control" placeholder="Search by description or reference #">
            </div>
            <div style="width:160px;">
                <select name="type" class="form-control" onchange="this.form.submit()">
                    <option value="all" <?= $typeFilter === 'all' ? 'selected' : '' ?>>All Transactions</option>
                    <option value="credit" <?= $typeFilter === 'credit' ? 'selected' : '' ?>>Money In (+)</option>
                    <option value="debit" <?= $typeFilter === 'debit' ? 'selected' : '' ?>>Money Out (-)</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-sm">Filter</button>
            <?php if ($q || $typeFilter !== 'all'): ?>
            <a href="transactions.php" class="btn btn-ghost btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Transaction List Card -->
    <div class="card reveal">
        <div class="card-header">
            <h4>Transactions (<?= count($transactions) ?>)</h4>
            <span style="font-size:12px;color:var(--text-muted);">Account: <?= e($account['AccountNumber']) ?></span>
        </div>
        <div class="card-body" style="padding:0 24px;">
            <?php if (empty($transactions)): ?>
            <div class="empty-state" style="padding:48px 0;">
                <i class='bx bx-transfer' style="font-size:44px;color:var(--text-muted);"></i>
                <h3>No transactions found</h3>
                <p>Try adjusting your search query or filter settings.</p>
            </div>
            <?php else: ?>
            <?php foreach ($transactions as $txn):
                $isCredit  = $txn['ToCustomerID'] == $userId;
                $type      = strtolower($txn['TypeName'] ?? 'transfer');
                $desc      = strtolower($txn['Description'] ?? '');
                $iconClass = $isCredit ? 'credit' : 'debit';
                if (strpos($type, 'transfer') !== false) {
                    $iconClass = 'transfer';
                } elseif (strpos($type, 'fee') !== false || strpos($desc, 'maintenance') !== false || strpos($desc, 'custody') !== false || strpos($desc, 'storage') !== false) {
                    $iconClass = 'fee';
                }
            ?>
            <div class="txn-row">
                <div class="txn-icon <?= $iconClass ?>">
                    <?php if ($iconClass === 'credit'): ?>
                    <i class='bx bx-log-in-circle'></i>
                    <?php elseif ($iconClass === 'fee'): ?>
                    <i class='bx bx-shield-quarter'></i>
                    <?php elseif ($iconClass === 'debit'): ?>
                    <i class='bx bx-log-out-circle'></i>
                    <?php else: ?>
                    <i class='bx bx-transfer'></i>
                    <?php endif; ?>
                </div>
                <div class="txn-info">
                    <div class="txn-title"><?= e($txn['Description'] ?: $txn['TypeName']) ?></div>
                    <div class="txn-date"><?= formatDate($txn['TransactionDate'], 'd M Y, g:i A') ?></div>
                </div>
                <div style="text-align:right;flex-shrink:0;">
                    <div class="txn-amount <?= $isCredit ? 'credit' : 'debit' ?>">
                        <?= $isCredit ? '+' : '-' ?><?= formatBDT($txn['TransactionAmount']) ?>
                    </div>
                    <div class="txn-ref"><?= e($txn['ReferenceNumber'] ?? '') ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>
