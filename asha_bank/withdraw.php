<?php
require_once 'config/db.php';
requireAuth('client');

$userId    = $_SESSION['user_id'];
$account   = getUserAccount($pdo, $userId);
if (!$account) redirect('logout.php');

$balance   = (float)$account['AvailableBalance'];
$accountId = $account['AccountID'];
$minBal    = (float)($account['MinBalance'] ?? 500);
$maxWithdraw = $balance - $minBal;

$error  = '';
$amount = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $amount = (float)($_POST['amount'] ?? 0);
    $desc   = sanitize($_POST['description'] ?? 'Cash Withdrawal');
    $pin    = $_POST['pin'] ?? '';

    if ($amount <= 0) {
        $error = 'Please enter a valid amount.';
    } elseif ($amount > $maxWithdraw) {
        $error = 'Insufficient balance. Your available balance after minimum requirement is ' . formatBDT($maxWithdraw) . '.';
    } elseif ($amount > 500000) {
        $error = 'Maximum single withdrawal is ৳5,00,000. Please contact the branch for larger amounts.';
    } elseif (!$pin || strlen($pin) < 4) {
        $error = 'Please enter your 4-digit transaction PIN.';
    } else {
        // Verify PIN
        $pinRow = $pdo->prepare("SELECT TransactionPIN FROM DIGITALBANKINGUSER WHERE CustomerID = ?");
        $pinRow->execute([$userId]);
        $savedPin = $pinRow->fetchColumn();
        if (!$savedPin || !password_verify($pin, $savedPin)) {
            $error = 'Invalid transaction PIN.';
        } else {
            try {
                $typeId = $pdo->query("SELECT TransactionTypeID FROM TRANSACTIONTYPE WHERE TypeName='Withdrawal' LIMIT 1")->fetchColumn();
                $ref    = 'WIT' . date('Ymd') . strtoupper(substr(uniqid(), -6));

                $pdo->beginTransaction();

                // Lock the account row
                $pdo->prepare("SELECT AvailableBalance FROM ACCOUNT WHERE AccountID = ? FOR UPDATE")->execute([$accountId]);

                // Re-check balance inside transaction
                $freshBal = (float)$pdo->query("SELECT AvailableBalance FROM ACCOUNT WHERE AccountID=$accountId")->fetchColumn();
                if ($freshBal - $minBal < $amount) {
                    $pdo->rollBack();
                    $error = 'Insufficient balance at time of processing.';
                } else {
                    // Insert transaction
                    $pdo->prepare("INSERT INTO TRANSACTION (FromAccountID, FromCustomerID, TransactionTypeID, TransactionAmount, Description, ReferenceNumber, TransactionStatus, TransactionDate)
                        VALUES (?, ?, ?, ?, ?, ?, 'Completed', NOW())")->execute([
                        $accountId, $userId, $typeId, $amount, $desc, $ref
                    ]);

                    // Debit
                    $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance - ? WHERE AccountID = ?")->execute([$amount, $accountId]);

                    // Notification
                    $pdo->prepare("INSERT INTO NOTIFICATIONS (customer_id, title, message, type, created_at) VALUES (?, ?, ?, 'warning', NOW())")->execute([
                        $userId,
                        'Withdrawal Processed',
                        formatBDT($amount) . ' withdrawn from your account. Ref: ' . $ref,
                    ]);

                    $pdo->commit();
                    setToast('Withdrawal of ' . formatBDT($amount) . ' successful! Ref: ' . $ref, 'success');
                    redirect('dashboard.php');
                }
            } catch (\Exception $e) {
                $pdo->inTransaction() && $pdo->rollBack();
                $error = 'Transaction failed. Please try again.';
                error_log('[WITHDRAW] ' . $e->getMessage());
            }
        }
    }
}

// History
$history = $pdo->prepare(
    "SELECT t.*, tt.TypeName FROM TRANSACTION t
     JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
     WHERE t.FromCustomerID = ? AND tt.TypeName = 'Withdrawal'
     ORDER BY t.TransactionDate DESC LIMIT 8"
);
$history->execute([$userId]);
$history = $history->fetchAll();

$pageTitle  = 'Withdraw';
$activePage = 'withdraw';
include 'includes/header.php';
?>

<div class="page-content">
    <div class="page-header">
        <h1>Withdraw Funds</h1>
        <p>Withdraw cash from your account securely.</p>
    </div>

    <div style="display:grid;grid-template-columns:460px 1fr;gap:24px;align-items:start;">

        <div>
            <!-- Balance indicator -->
            <div class="stat-card reveal" style="margin-bottom:20px;">
                <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/></svg>
                </div>
                <div class="stat-card-label">Available to Withdraw</div>
                <div class="stat-card-value" style="color:var(--ink);"><?= formatBDT(max(0, $maxWithdraw)) ?></div>
                <div class="stat-card-sub">Balance: <?= formatBDT($balance) ?> | Min reserve: <?= formatBDT($minBal) ?></div>
            </div>

            <div class="card reveal">
                <div class="card-header">
                    <h4>Withdrawal Request</h4>
                    <span class="badge badge-warning">PIN Required</span>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <?= e($error) ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="withdraw.php" data-loading>
                        <?= csrfField() ?>

                        <div class="form-group">
                            <label class="form-label" for="amount">Amount <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon" style="font-weight:600;font-size:15px;color:var(--ink-muted);">৳</span>
                                <input type="number" id="amount" name="amount" class="form-control" step="0.01"
                                       min="1" max="<?= $maxWithdraw ?>"
                                       value="<?= e($amount ?: '') ?>" placeholder="0.00" required>
                            </div>
                            <div class="form-helper">Max withdrawal: <?= formatBDT(max(0, $maxWithdraw)) ?></div>
                        </div>

                        <!-- Quick amounts -->
                        <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
                            <?php foreach ([500, 1000, 2000, 5000, 10000] as $q):
                                if ($q <= $maxWithdraw): ?>
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('amount').value=<?= $q ?>;updateSummary()">
                                ৳<?= number_format($q) ?>
                            </button>
                            <?php endif; endforeach; ?>
                            <?php if ($maxWithdraw > 0): ?>
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('amount').value=<?= floor($maxWithdraw) ?>;updateSummary()" style="color:var(--red);border-color:var(--red);">Max</button>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="description">Description</label>
                            <input type="text" id="description" name="description" class="form-control"
                                   value="Cash Withdrawal" maxlength="255" placeholder="Purpose of withdrawal">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="pin">Transaction PIN <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                                <input type="password" id="pin" name="pin" class="form-control"
                                       maxlength="6" pattern="[0-9]{4,6}" placeholder="••••"
                                       autocomplete="off" required inputmode="numeric">
                            </div>
                            <div class="form-helper">4-6 digit transaction PIN</div>
                        </div>

                        <!-- Summary -->
                        <div id="withdrawSummary" style="display:none;background:#fff8f1;border:1px solid #ffe0b2;border-radius:12px;padding:16px;margin-bottom:20px;">
                            <div style="font-size:12px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--amber);margin-bottom:10px;">Summary</div>
                            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                <span style="font-size:13px;color:var(--ink-muted);">Withdraw Amount</span>
                                <span style="font-size:13px;font-weight:600;color:var(--red);" id="wSummaryAmount">৳0</span>
                            </div>
                            <div style="height:1px;background:#ffe0b2;margin:8px 0;"></div>
                            <div style="display:flex;justify-content:space-between;">
                                <span style="font-size:13px;font-weight:600;">Remaining Balance</span>
                                <span style="font-size:15px;font-weight:700;" id="wSummaryBalance">৳<?= number_format($balance, 2) ?></span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-danger w-full btn-lg">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                            Confirm Withdrawal
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- History -->
        <div class="card reveal">
            <div class="card-header"><h4>Withdrawal History</h4></div>
            <?php if (empty($history)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                <h3>No withdrawals yet</h3>
                <p>Your withdrawal history will appear here.</p>
            </div>
            <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Date</th><th>Amount</th><th>Description</th><th>Reference</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $txn): ?>
                        <tr>
                            <td><?= formatDate($txn['TransactionDate'], 'd M Y') ?><br><span style="font-size:11px;color:var(--ink-faint);"><?= formatDate($txn['TransactionDate'], 'g:i A') ?></span></td>
                            <td><strong style="color:var(--red);">-<?= formatBDT($txn['TransactionAmount']) ?></strong></td>
                            <td><?= e($txn['Description']) ?></td>
                            <td><code style="font-size:12px;"><?= e($txn['ReferenceNumber']) ?></code></td>
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
const currentBalance = <?= $balance ?>;
const minBalance     = <?= $minBal ?>;
const amountInput    = document.getElementById('amount');

function updateSummary() {
    const val = parseFloat(amountInput.value) || 0;
    const summary = document.getElementById('withdrawSummary');
    if (val > 0) {
        summary.style.display = 'block';
        document.getElementById('wSummaryAmount').textContent = '৳' + val.toLocaleString('en-IN', {minimumFractionDigits:2,maximumFractionDigits:2});
        const remain = Math.max(0, currentBalance - val);
        const remainEl = document.getElementById('wSummaryBalance');
        remainEl.textContent = '৳' + remain.toLocaleString('en-IN', {minimumFractionDigits:2,maximumFractionDigits:2});
        remainEl.style.color = remain <= minBalance ? 'var(--red)' : 'var(--green)';
    } else {
        summary.style.display = 'none';
    }
}
amountInput.addEventListener('input', updateSummary);
</script>

<?php include 'includes/footer.php'; ?>
