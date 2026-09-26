<?php
require_once 'config/db.php';
requireAuth('client');

$userId  = $_SESSION['user_id'];
$account = getUserAccount($pdo, $userId);
if (!$account) redirect('logout.php');

$balance   = (float)$account['AvailableBalance'];
$accountId = $account['AccountID'];

$success = '';
$error   = '';
$amount  = '';
$desc    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $amount = (float)($_POST['amount'] ?? 0);
    $desc   = sanitize($_POST['description'] ?? 'Cash Deposit');
    $method = sanitize($_POST['method'] ?? 'cash');

    if ($amount <= 0) {
        $error = 'Please enter a valid amount greater than 0.';
    } elseif ($amount > 1000000) {
        $error = 'Maximum single deposit is ৳10,00,000. Please contact the branch for larger amounts.';
    } elseif (strlen($desc) > 255) {
        $error = 'Description is too long.';
    } else {
        try {
            // Get deposit transaction type
            $typeId = $pdo->query("SELECT TransactionTypeID FROM TRANSACTIONTYPE WHERE TypeName='Deposit' LIMIT 1")->fetchColumn();
            if (!$typeId) throw new \Exception('Transaction type not found.');

            // Generate unique reference
            $ref = 'DEP' . date('Ymd') . strtoupper(substr(uniqid(), -6));

            $pdo->beginTransaction();

            // Insert transaction
            $pdo->prepare("INSERT INTO TRANSACTION (ToAccountID, ToCustomerID, TransactionTypeID, TransactionAmount, Description, ReferenceNumber, TransactionStatus, TransactionDate)
                VALUES (?, ?, ?, ?, ?, ?, 'Completed', NOW())")->execute([
                $accountId, $userId, $typeId, $amount, $desc, $ref
            ]);

            // Update balance
            $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance + ? WHERE AccountID = ?")->execute([$amount, $accountId]);

            // Notification
            $pdo->prepare("INSERT INTO NOTIFICATIONS (customer_id, title, message, type, created_at) VALUES (?, ?, ?, 'success', NOW())")->execute([
                $userId,
                'Deposit Successful',
                'Your deposit of ' . formatBDT($amount) . ' was successful. Ref: ' . $ref,
            ]);

            $pdo->commit();

            setToast('Deposit of ' . formatBDT($amount) . ' successful! Ref: ' . $ref, 'success');
            redirect('dashboard.php');

        } catch (\Exception $e) {
            $pdo->inTransaction() && $pdo->rollBack();
            $error = 'Transaction failed. Please try again.';
            error_log('[DEPOSIT] ' . $e->getMessage());
        }
    }
}

// Recent deposit history
$history = $pdo->prepare(
    "SELECT t.*, tt.TypeName FROM TRANSACTION t
     JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
     WHERE t.ToCustomerID = ? AND tt.TypeName = 'Deposit'
     ORDER BY t.TransactionDate DESC LIMIT 8"
);
$history->execute([$userId]);
$history = $history->fetchAll();

$pageTitle  = 'Deposit';
$activePage = 'deposit';
include 'includes/header.php';
?>

<div class="page-content">
    <div class="page-header">
        <h1>Deposit Funds</h1>
        <p>Add money to your account safely and instantly.</p>
    </div>

    <div style="display:grid;grid-template-columns:460px 1fr;gap:24px;align-items:start;">

        <!-- Form -->
        <div>
            <!-- Balance card -->
            <div class="stat-card reveal" style="margin-bottom:20px;">
                <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                </div>
                <div class="stat-card-label">Current Balance</div>
                <div class="stat-card-value" style="color:var(--green);"><?= formatBDT($balance) ?></div>
                <div class="stat-card-sub">Account: <?= e($account['AccountNumber']) ?></div>
            </div>

            <div class="card reveal">
                <div class="card-header">
                    <h4>Make a Deposit</h4>
                    <span class="badge badge-success">
                        <span class="status-dot green"></span> Instant
                    </span>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <?= e($error) ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="deposit.php" data-loading>
                        <?= csrfField() ?>

                        <!-- Amount with quick presets -->
                        <div class="form-group">
                            <label class="form-label" for="amount">Amount <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon" style="font-weight:600;font-size:15px;color:var(--ink-muted);">৳</span>
                                <input type="number" id="amount" name="amount" class="form-control" step="0.01" min="1" max="1000000"
                                       value="<?= e($amount ?: '') ?>" placeholder="0.00" required>
                            </div>
                            <div class="form-helper">Min: ৳1 | Max: ৳10,00,000 per transaction</div>
                        </div>

                        <!-- Quick amount buttons -->
                        <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
                            <?php foreach ([500, 1000, 2000, 5000, 10000, 25000] as $q): ?>
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('amount').value=<?= $q ?>">
                                ৳<?= number_format($q) ?>
                            </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="method">Deposit Method</label>
                            <select id="method" name="method" class="form-control">
                                <option value="cash">Cash Deposit</option>
                                <option value="cheque">Cheque</option>
                                <option value="online">Online Transfer</option>
                                <option value="agent">Agent Banking</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="description">Description / Note</label>
                            <input type="text" id="description" name="description" class="form-control"
                                   value="<?= e($desc ?: 'Cash Deposit') ?>" maxlength="255"
                                   placeholder="e.g. Monthly savings">
                        </div>

                        <!-- Summary -->
                        <div id="depositSummary" style="display:none;background:var(--green-light);border:1px solid #c8e6c9;border-radius:12px;padding:16px;margin-bottom:20px;">
                            <div style="font-size:12px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--green);margin-bottom:10px;">Transaction Summary</div>
                            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                <span style="font-size:13px;color:var(--ink-muted);">Deposit Amount</span>
                                <span style="font-size:13px;font-weight:600;" id="summaryAmount">৳0</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                <span style="font-size:13px;color:var(--ink-muted);">Processing Fee</span>
                                <span style="font-size:13px;font-weight:600;color:var(--green);">Free</span>
                            </div>
                            <div style="height:1px;background:#c8e6c9;margin:10px 0;"></div>
                            <div style="display:flex;justify-content:space-between;">
                                <span style="font-size:13px;font-weight:600;">New Balance</span>
                                <span style="font-size:15px;font-weight:700;color:var(--green);" id="summaryBalance">৳<?= number_format($balance, 2) ?></span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-full btn-lg">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                            Confirm Deposit
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- History -->
        <div class="card reveal">
            <div class="card-header">
                <h4>Deposit History</h4>
            </div>
            <?php if (empty($history)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                <h3>No deposits yet</h3>
                <p>Make your first deposit to see history here.</p>
            </div>
            <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $txn): ?>
                        <tr>
                            <td><?= formatDate($txn['TransactionDate'], 'd M Y') ?><br><span style="font-size:11px;color:var(--ink-faint);"><?= formatDate($txn['TransactionDate'], 'g:i A') ?></span></td>
                            <td><strong style="color:var(--green);">+<?= formatBDT($txn['TransactionAmount']) ?></strong></td>
                            <td style="max-width:180px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= e($txn['Description']) ?></td>
                            <td><code style="font-size:12px;color:var(--ink-muted);"><?= e($txn['ReferenceNumber']) ?></code></td>
                            <td><span class="badge badge-<?= $txn['TransactionStatus']==='Completed' ? 'success' : 'warning' ?>"><?= e($txn['TransactionStatus']) ?></span></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
const balanceVal = <?= $balance ?>;
const amountInput = document.getElementById('amount');
const summary = document.getElementById('depositSummary');
const summaryAmount = document.getElementById('summaryAmount');
const summaryBalance = document.getElementById('summaryBalance');

amountInput.addEventListener('input', function() {
    const val = parseFloat(this.value) || 0;
    if (val > 0) {
        summary.style.display = 'block';
        summaryAmount.textContent = '৳' + val.toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
        summaryBalance.textContent = '৳' + (balanceVal + val).toLocaleString('en-IN', {minimumFractionDigits:2, maximumFractionDigits:2});
    } else {
        summary.style.display = 'none';
    }
});
</script>

<?php include 'includes/footer.php'; ?>
