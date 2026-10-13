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
                    $pdo->prepare("INSERT INTO TRANSACTION (FromAccountNumber, FromCustomerID, TransactionTypeID, TransactionAmount, Description, ReferenceNumber, TransactionStatus, TransactionDate)
                        VALUES (?, ?, ?, ?, ?, ?, 'Completed', NOW())")->execute([
                        $account['AccountNumber'], $userId, $typeId, $amount, $desc, $ref
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

// Withdrawal History & Statistics
$historyStmt = $pdo->prepare(
    "SELECT t.*, tt.TypeName 
     FROM TRANSACTION t
     JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
     WHERE (t.FromCustomerID = ? OR t.FromAccountNumber = ?) 
       AND (tt.TypeName = 'Withdrawal' OR t.TransactionTypeID = 2)
     ORDER BY t.TransactionDate DESC 
     LIMIT 25"
);
$historyStmt->execute([$userId, $account['AccountNumber']]);
$history = $historyStmt->fetchAll();

$statsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS total_count, COALESCE(SUM(TransactionAmount), 0) AS total_amount
     FROM TRANSACTION t
     JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
     WHERE (t.FromCustomerID = ? OR t.FromAccountNumber = ?) 
       AND (tt.TypeName = 'Withdrawal' OR t.TransactionTypeID = 2)
       AND t.TransactionStatus = 'Completed'"
);
$statsStmt->execute([$userId, $account['AccountNumber']]);
$stats = $statsStmt->fetch();
$totalWithdrawn     = (float)($stats['total_amount'] ?? 0);
$totalWithdrawCount = (int)($stats['total_count'] ?? 0);

$pageTitle  = 'Withdraw';
$activePage = 'withdraw';
include 'includes/header.php';
?>

<div class="page-content">
    <div class="page-header">
        <h1>Withdraw Funds</h1>
        <p>Withdraw cash from your account securely.</p>
    </div>

    <div class="transaction-page-grid" style="display:grid;grid-template-columns:460px 1fr;gap:24px;align-items:start;">

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
                        <div id="withdrawSummary" style="display:none;background:var(--bg-subtle);border:1px solid var(--border-subtle);border-radius:12px;padding:16px;margin-bottom:20px;">
                            <div style="font-size:11.5px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--text-muted);margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                                <i class='bx bx-calculator'></i> Transaction Summary
                            </div>
                            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                <span style="font-size:13px;color:var(--text-muted);">Withdraw Amount</span>
                                <span style="font-size:13px;font-weight:600;color:var(--red);" id="wSummaryAmount">৳0.00</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                <span style="font-size:13px;color:var(--text-muted);">Processing Fee</span>
                                <span style="font-size:13px;font-weight:600;color:var(--green);">৳0.00 (Free)</span>
                            </div>
                            <div style="height:1px;background:var(--border-subtle);margin:10px 0;"></div>
                            <div style="display:flex;justify-content:space-between;align-items:center;">
                                <span style="font-size:13px;font-weight:600;color:var(--text-primary);">Remaining Balance</span>
                                <span style="font-size:15px;font-weight:700;" id="wSummaryBalance">৳<?= number_format($balance, 2) ?></span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-danger w-full btn-lg">
                            <i class='bx bx-log-out-circle' style="font-size:18px;"></i>
                            Confirm Withdrawal
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- History -->
        <div class="card reveal" style="overflow:hidden;">
            <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:12px;padding:18px 24px;border-bottom:1px solid var(--border-subtle);">
                <div style="display:flex;align-items:center;gap:12px;">
                    <div style="width:38px;height:38px;border-radius:10px;background:rgba(239,68,68,0.1);color:#EF4444;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0;">
                        <i class='bx bx-history'></i>
                    </div>
                    <div>
                        <h4 style="margin:0;font-size:16px;font-weight:700;">Withdrawal History</h4>
                        <p style="margin:2px 0 0;font-size:12px;color:var(--text-muted);">Recent counter & ATM cash disbursements</p>
                    </div>
                </div>
                <div style="display:flex;align-items:center;gap:8px;">
                    <span class="badge badge-neutral" style="font-size:11.5px;padding:4px 10px;">
                        <?= count($history) ?> <?= count($history) === 1 ? 'record' : 'records' ?>
                    </span>
                </div>
            </div>

            <!-- Mini Summary Metrics Strip -->
            <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(180px, 1fr));gap:1px;background:var(--border-subtle);border-bottom:1px solid var(--border-subtle);">
                <div style="background:var(--bg-card);padding:14px 20px;">
                    <div style="font-size:11px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px;">Total Withdrawn</div>
                    <div style="font-size:18px;font-weight:700;color:#EF4444;letter-spacing:-0.02em;">
                        <?= formatBDT($totalWithdrawn) ?>
                    </div>
                </div>
                <div style="background:var(--bg-card);padding:14px 20px;">
                    <div style="font-size:11px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px;">Successful Withdrawals</div>
                    <div style="font-size:18px;font-weight:700;color:var(--text-primary);letter-spacing:-0.02em;">
                        <?= $totalWithdrawCount ?> <span style="font-size:12px;font-weight:500;color:var(--text-muted);">completed</span>
                    </div>
                </div>
                <div style="background:var(--bg-card);padding:14px 20px;">
                    <div style="font-size:11px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px;">Primary Account</div>
                    <div style="font-size:14px;font-family:'SF Mono','Fira Code',monospace;font-weight:600;color:var(--text-secondary);letter-spacing:0.04em;">
                        <?= e($account['AccountNumber']) ?>
                    </div>
                </div>
            </div>

            <?php if (empty($history)): ?>
            <div class="empty-state" style="padding:48px 24px;">
                <div style="width:60px;height:60px;border-radius:50%;background:rgba(239,68,68,0.08);color:#EF4444;display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:30px;">
                    <i class='bx bx-money-withdraw'></i>
                </div>
                <h3 style="margin-bottom:6px;font-size:16px;">No withdrawals yet</h3>
                <p style="font-size:13.5px;max-width:340px;margin:0 auto 18px;color:var(--text-muted);">Your cash and ATM withdrawal records along with printable receipts will appear here.</p>
                <?php if ($maxWithdraw > 0): ?>
                <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('amount').focus();">
                    <i class='bx bx-plus'></i> Make a withdrawal
                </button>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="table-wrapper">
                <table style="width:100%;margin-bottom:0;">
                    <thead>
                        <tr>
                            <th style="padding-left:24px;">Date & Time</th>
                            <th>Amount</th>
                            <th>Description</th>
                            <th>Reference</th>
                            <th>Status</th>
                            <th style="text-align:right;padding-right:24px;">Receipt</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $txn): ?>
                        <tr>
                            <td style="padding-left:24px;">
                                <div style="font-weight:600;color:var(--text-primary);font-size:13px;"><?= formatDate($txn['TransactionDate'], 'd M Y') ?></div>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:2px;display:flex;align-items:center;gap:4px;">
                                    <i class='bx bx-time-five' style="font-size:11px;"></i> <?= formatDate($txn['TransactionDate'], 'g:i A') ?>
                                </div>
                            </td>
                            <td>
                                <div style="display:inline-flex;align-items:center;gap:6px;font-weight:700;color:#EF4444;font-size:14px;letter-spacing:-0.02em;">
                                    <i class='bx bx-down-arrow-alt' style="font-size:16px;background:rgba(239,68,68,0.1);border-radius:50%;padding:2px;"></i>
                                    -<?= formatBDT($txn['TransactionAmount']) ?>
                                </div>
                            </td>
                            <td>
                                <div style="font-size:13px;font-weight:600;color:var(--text-primary);max-width:200px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= e($txn['Description'] ?: 'Cash Withdrawal') ?>">
                                    <?= e($txn['Description'] ?: 'Cash Withdrawal') ?>
                                </div>
                                <div style="font-size:11px;color:var(--text-muted);margin-top:1px;">Cash Disbursement</div>
                            </td>
                            <td>
                                <div style="display:inline-flex;align-items:center;gap:6px;">
                                    <code style="font-size:11.5px;font-family:'SF Mono','Fira Code',monospace;padding:3px 7px;background:var(--bg-subtle);border-radius:6px;border:1px solid var(--border-subtle);letter-spacing:0.04em;">
                                        <?= e($txn['ReferenceNumber']) ?>
                                    </code>
                                    <button type="button" class="btn btn-ghost btn-sm" style="padding:4px 6px;height:auto;font-size:13px;color:var(--text-muted);"
                                            onclick="copyReference('<?= e($txn['ReferenceNumber']) ?>')" title="Copy reference">
                                        <i class='bx bx-copy'></i>
                                    </button>
                                </div>
                            </td>
                            <td>
                                <span class="badge badge-<?= $txn['TransactionStatus']==='Completed' ? 'success' : ($txn['TransactionStatus']==='Failed' ? 'danger' : 'warning') ?>" style="display:inline-flex;align-items:center;gap:4px;font-size:11px;padding:3px 8px;">
                                    <i class='bx bx-<?= $txn['TransactionStatus']==='Completed' ? 'check-circle' : 'time' ?>'></i>
                                    <?= e($txn['TransactionStatus']) ?>
                                </span>
                            </td>
                            <td style="text-align:right;padding-right:24px;">
                                <button type="button" class="btn btn-outline btn-sm" style="padding:4px 10px;font-size:12px;display:inline-flex;align-items:center;gap:4px;"
                                        onclick="showWithdrawReceipt(<?= htmlspecialchars(json_encode([
                                            'ref'      => $txn['ReferenceNumber'],
                                            'amount'   => formatBDT($txn['TransactionAmount']),
                                            'date'     => formatDate($txn['TransactionDate'], 'd M Y, g:i A'),
                                            'account'  => $account['AccountNumber'],
                                            'desc'     => $txn['Description'] ?: 'Cash Withdrawal',
                                            'status'   => $txn['TransactionStatus']
                                        ]), ENT_QUOTES, 'UTF-8') ?>)">
                                    <i class='bx bx-receipt'></i> Voucher
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Receipt Modal -->
<div id="withdrawReceiptModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.6);z-index:9999;backdrop-filter:blur(4px);align-items:center;justify-content:center;padding:20px;">
    <div style="background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:18px;max-width:440px;width:100%;box-shadow:0 24px 48px rgba(0,0,0,0.25);overflow:hidden;animation:modalIn 0.2s cubic-bezier(0.16,1,0.3,1);">
        <div style="background:linear-gradient(135deg, #1E293B 0%, #0F172A 100%);padding:22px;color:#FFFFFF;position:relative;">
            <button type="button" onclick="closeWithdrawReceipt()" style="position:absolute;top:16px;right:16px;background:rgba(255,255,255,0.15);border:none;color:#FFFFFF;width:28px;height:28px;border-radius:50%;cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;">
                <i class='bx bx-x'></i>
            </button>
            <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
                <div style="width:24px;height:24px;border-radius:6px;background:rgba(255,255,255,0.2);display:flex;align-items:center;justify-content:center;">
                    <i class='bx bx-building-house' style="font-size:14px;"></i>
                </div>
                <span style="font-weight:700;font-size:14px;letter-spacing:-0.01em;">Asha Bank</span>
            </div>
            <h3 style="margin:0 0 2px;color:#FFFFFF;font-size:18px;font-weight:700;">Debit Advice Voucher</h3>
            <div style="font-size:12px;color:rgba(255,255,255,0.7);">Official Cash Withdrawal Receipt</div>
        </div>
        <div style="padding:22px;">
            <div style="text-align:center;padding:12px 0 16px;border-bottom:1px dashed var(--border-subtle);">
                <div style="font-size:11px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted);margin-bottom:4px;">Amount Debited</div>
                <div id="receiptAmount" style="font-size:26px;font-weight:800;color:#EF4444;letter-spacing:-0.03em;">৳0.00</div>
                <span class="badge badge-success" style="margin-top:6px;display:inline-flex;align-items:center;gap:4px;">
                    <i class='bx bx-check-circle'></i> Completed
                </span>
            </div>

            <div style="display:flex;flex-direction:column;gap:10px;margin:16px 0;">
                <div style="display:flex;justify-content:space-between;font-size:13px;">
                    <span style="color:var(--text-muted);">Reference No</span>
                    <span id="receiptRef" style="font-family:monospace;font-weight:600;color:var(--text-primary);">—</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px;">
                    <span style="color:var(--text-muted);">Date & Time</span>
                    <span id="receiptDate" style="font-weight:500;color:var(--text-primary);">—</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px;">
                    <span style="color:var(--text-muted);">Debited Account</span>
                    <span id="receiptAccount" style="font-family:monospace;font-weight:600;color:var(--text-primary);">—</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px;">
                    <span style="color:var(--text-muted);">Description</span>
                    <span id="receiptDesc" style="font-weight:500;color:var(--text-primary);max-width:200px;text-align:right;">Cash Withdrawal</span>
                </div>
                <div style="display:flex;justify-content:space-between;font-size:13px;">
                    <span style="color:var(--text-muted);">Service Fee</span>
                    <span style="font-weight:600;color:var(--green);">৳0.00</span>
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:20px;">
                <button type="button" class="btn btn-outline" style="flex:1;" onclick="window.print()">
                    <i class='bx bx-printer'></i> Print Voucher
                </button>
                <button type="button" class="btn btn-primary" style="flex:1;" onclick="closeWithdrawReceipt()">
                    Done
                </button>
            </div>
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
if (amountInput) {
    amountInput.addEventListener('input', updateSummary);
}

function copyReference(ref) {
    navigator.clipboard.writeText(ref).then(function() {
        if (typeof showToast === 'function') {
            showToast('Reference copied: ' + ref, 'success');
        } else {
            alert('Reference copied: ' + ref);
        }
    }).catch(function() {
        prompt('Copy reference number:', ref);
    });
}

function showWithdrawReceipt(data) {
    document.getElementById('receiptAmount').textContent  = '-' + data.amount;
    document.getElementById('receiptRef').textContent     = data.ref;
    document.getElementById('receiptDate').textContent    = data.date;
    document.getElementById('receiptAccount').textContent = data.account;
    document.getElementById('receiptDesc').textContent    = data.desc;
    const modal = document.getElementById('withdrawReceiptModal');
    modal.style.display = 'flex';
}

function closeWithdrawReceipt() {
    document.getElementById('withdrawReceiptModal').style.display = 'none';
}

document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') closeWithdrawReceipt();
});
</script>

<?php include 'includes/footer.php'; ?>
