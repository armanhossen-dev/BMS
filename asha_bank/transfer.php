<?php
require_once 'config/db.php';
requireAuth('client');

$userId    = $_SESSION['user_id'];
$account   = getUserAccount($pdo, $userId);
if (!$account) redirect('logout.php');

$balance   = (float)$account['AvailableBalance'];
$accountId = $account['AccountID'];
$minBal    = (float)($account['MinBalance'] ?? 500);

$error     = '';
$recipient = null;
$step      = 1; // 1 = lookup, 2 = confirm, done = process

// AJAX: look up recipient by account number
if (isset($_GET['ajax']) && $_GET['ajax'] === 'lookup') {
    header('Content-Type: application/json');
    $acn = sanitize($_GET['acn'] ?? '');
    if (!$acn) { echo json_encode(['error' => 'Account number required']); exit; }

    $r = $pdo->prepare(
        "SELECT a.AccountNumber, a.AccountID, c.CustomerID, c.FirstName, c.LastName, c.Email, a.AccountType, a.AccountStatus
         FROM ACCOUNT a JOIN CUSTOMER c ON a.CustomerID = c.CustomerID
         WHERE a.AccountNumber = ? AND c.CustomerID != ? AND a.AccountStatus = 'Active' LIMIT 1"
    );
    $r->execute([$acn, $userId]);
    $rec = $r->fetch();

    if ($rec) {
        echo json_encode(['success' => true, 'name' => $rec['FirstName'] . ' ' . $rec['LastName'], 'type' => $rec['AccountType'], 'account' => $rec['AccountNumber'], 'id' => $rec['CustomerID'], 'accountId' => $rec['AccountID']]);
    } else {
        echo json_encode(['error' => 'Account not found or inactive.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();

    $toAccountNumber = sanitize($_POST['to_account'] ?? '');
    $amount          = (float)($_POST['amount'] ?? 0);
    $desc            = sanitize($_POST['description'] ?? 'Fund Transfer');
    $pin             = $_POST['pin'] ?? '';
    $toCustomerId    = (int)($_POST['to_customer_id'] ?? 0);
    $toAccountId     = (int)($_POST['to_account_id'] ?? 0);

    if (!$toAccountNumber || !$toCustomerId || !$toAccountId) {
        $error = 'Please look up a valid recipient account first.';
    } elseif ($amount <= 0) {
        $error = 'Please enter a valid amount.';
    } elseif ($amount > ($balance - $minBal)) {
        $error = 'Insufficient balance. Available: ' . formatBDT($balance - $minBal);
    } elseif ($amount > 200000) {
        $error = 'Maximum single transfer is ৳2,00,000 per transaction.';
    } elseif (!$pin || strlen($pin) < 4) {
        $error = 'Please enter your transaction PIN.';
    } else {
        // Verify PIN
        $pinRow = $pdo->prepare("SELECT TransactionPIN FROM DIGITALBANKINGUSER WHERE CustomerID = ?");
        $pinRow->execute([$userId]);
        $savedPin = $pinRow->fetchColumn();

        if (!$savedPin || !password_verify($pin, $savedPin)) {
            $error = 'Invalid transaction PIN.';
        } else {
            try {
                // Use stored procedure if available, else manual
                $ref = 'TRF' . date('Ymd') . strtoupper(substr(uniqid(), -6));
                $typeId = $pdo->query("SELECT TransactionTypeID FROM TRANSACTIONTYPE WHERE TypeName='Transfer' LIMIT 1")->fetchColumn();
                if (!$typeId) throw new \Exception('Transaction type not configured.');

                $pdo->beginTransaction();

                // Lock both accounts in a deterministic order
                $ids = [$accountId, $toAccountId];
                sort($ids);
                foreach ($ids as $lockId) {
                    $pdo->prepare("SELECT AvailableBalance FROM ACCOUNT WHERE AccountID = ? FOR UPDATE")->execute([$lockId]);
                }

                // Re-check sender balance
                $freshBal = (float)$pdo->query("SELECT AvailableBalance FROM ACCOUNT WHERE AccountID=$accountId")->fetchColumn();
                if ($freshBal - $minBal < $amount) {
                    $pdo->rollBack();
                    $error = 'Insufficient balance at time of processing.';
                } else {
                    // Insert transaction record
                    $pdo->prepare(
                        "INSERT INTO TRANSACTION (FromAccountID, FromCustomerID, ToAccountID, ToCustomerID, TransactionTypeID, TransactionAmount, Description, ReferenceNumber, TransactionStatus, TransactionDate)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Completed', NOW())"
                    )->execute([$accountId, $userId, $toAccountId, $toCustomerId, $typeId, $amount, $desc, $ref]);

                    // Debit sender
                    $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance - ? WHERE AccountID = ?")->execute([$amount, $accountId]);

                    // Credit receiver
                    $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance + ? WHERE AccountID = ?")->execute([$amount, $toAccountId]);

                    // Notify both
                    $recipientName = $pdo->query("SELECT FirstName FROM CUSTOMER WHERE CustomerID=$toCustomerId")->fetchColumn();
                    $senderName    = explode(' ', $_SESSION['username'])[0];

                    $pdo->prepare("INSERT INTO NOTIFICATIONS (customer_id, title, message, type, created_at) VALUES (?, ?, ?, 'warning', NOW())")->execute([
                        $userId,
                        'Transfer Sent',
                        formatBDT($amount) . ' sent to ' . $recipientName . '. Ref: ' . $ref,
                    ]);
                    $pdo->prepare("INSERT INTO NOTIFICATIONS (customer_id, title, message, type, created_at) VALUES (?, ?, ?, 'success', NOW())")->execute([
                        $toCustomerId,
                        'Money Received',
                        formatBDT($amount) . ' received from ' . $senderName . '. Ref: ' . $ref,
                    ]);

                    $pdo->commit();
                    setToast('Transfer of ' . formatBDT($amount) . ' sent successfully! Ref: ' . $ref, 'success');
                    redirect('dashboard.php');
                }
            } catch (\Exception $e) {
                $pdo->inTransaction() && $pdo->rollBack();
                $error = 'Transfer failed. Please try again.';
                error_log('[TRANSFER] ' . $e->getMessage());
            }
        }
    }
}

// Transfer history
$history = $pdo->prepare(
    "SELECT t.*, tt.TypeName,
            CASE WHEN t.FromCustomerID=? THEN 'sent' ELSE 'received' END AS direction,
            CASE WHEN t.FromCustomerID=? THEN CONCAT(rc.FirstName,' ',rc.LastName) ELSE CONCAT(sc.FirstName,' ',sc.LastName) END AS other_party
     FROM TRANSACTION t
     JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
     LEFT JOIN CUSTOMER rc ON t.ToCustomerID = rc.CustomerID
     LEFT JOIN CUSTOMER sc ON t.FromCustomerID = sc.CustomerID
     WHERE (t.FromCustomerID=? OR t.ToCustomerID=?) AND tt.TypeName='Transfer'
     ORDER BY t.TransactionDate DESC LIMIT 8"
);
$history->execute([$userId, $userId, $userId, $userId]);
$history = $history->fetchAll();

$pageTitle  = 'Transfer';
$activePage = 'transfer';
include 'includes/header.php';
?>

<div class="page-content">
    <div class="page-header">
        <h1>Send Money</h1>
        <p>Transfer funds to any Asha Bank account instantly.</p>
    </div>

    <div style="display:grid;grid-template-columns:480px 1fr;gap:24px;align-items:start;">

        <div>
            <!-- Balance -->
            <div class="stat-card reveal" style="margin-bottom:20px;">
                <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/></svg>
                </div>
                <div class="stat-card-label">Transferable Balance</div>
                <div class="stat-card-value"><?= formatBDT(max(0, $balance - $minBal)) ?></div>
                <div class="stat-card-sub">Acct: <?= e($account['AccountNumber']) ?> | Min reserve: <?= formatBDT($minBal) ?></div>
            </div>

            <div class="card reveal">
                <div class="card-header">
                    <h4>Fund Transfer</h4>
                    <span class="badge badge-info">
                        <span class="status-dot blue"></span> Instant
                    </span>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger" role="alert">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                        <?= e($error) ?>
                    </div>
                    <?php endif; ?>

                    <form method="POST" action="transfer.php" id="transferForm" data-loading>
                        <?= csrfField() ?>
                        <input type="hidden" name="to_customer_id" id="toCustomerId" value="">
                        <input type="hidden" name="to_account_id"  id="toAccountId"  value="">

                        <!-- Step 1: Recipient lookup -->
                        <div class="form-group">
                            <label class="form-label" for="to_account">Recipient Account Number <span class="required">*</span></label>
                            <div style="display:flex;gap:8px;">
                                <div class="input-group" style="flex:1;">
                                    <span class="input-icon">
                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                                    </span>
                                    <input type="text" id="to_account" name="to_account" class="form-control"
                                           placeholder="e.g. ACN000001"
                                           autocomplete="off" required>
                                </div>
                                <button type="button" id="lookupBtn" class="btn btn-outline">Lookup</button>
                            </div>
                        </div>

                        <!-- Recipient info box -->
                        <div id="recipientBox" style="display:none;background:var(--blue-light);border:1px solid #bbdefb;border-radius:12px;padding:14px;margin-bottom:20px;">
                            <div style="font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--blue);margin-bottom:8px;">Recipient Found</div>
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div style="width:36px;height:36px;background:var(--blue);border-radius:50%;display:flex;align-items:center;justify-content:center;color:white;font-weight:700;" id="recipientInitial">?</div>
                                <div>
                                    <div style="font-size:15px;font-weight:600;color:var(--ink);" id="recipientName">—</div>
                                    <div style="font-size:12px;color:var(--ink-muted);" id="recipientType">—</div>
                                </div>
                            </div>
                        </div>
                        <div id="lookupError" style="display:none;" class="alert alert-danger" role="alert">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                            <span id="lookupErrorText"></span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="amount">Amount <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon" style="font-weight:600;color:var(--ink-muted);">৳</span>
                                <input type="number" id="amount" name="amount" class="form-control"
                                       step="0.01" min="1" max="<?= max(0, $balance - $minBal) ?>"
                                       placeholder="0.00" required>
                            </div>
                        </div>

                        <!-- Quick amounts -->
                        <div style="display:flex;gap:8px;margin-bottom:20px;flex-wrap:wrap;">
                            <?php foreach ([500, 1000, 2000, 5000, 10000] as $q):
                                if ($q <= ($balance - $minBal)): ?>
                            <button type="button" class="btn btn-outline btn-sm" onclick="document.getElementById('amount').value=<?= $q ?>;updateTransferSummary()">
                                ৳<?= number_format($q) ?>
                            </button>
                            <?php endif; endforeach; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="description">Note / Purpose</label>
                            <input type="text" id="description" name="description" class="form-control"
                                   value="Fund Transfer" maxlength="255" placeholder="e.g. Rent payment">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="pin">Transaction PIN <span class="required">*</span></label>
                            <div class="input-group">
                                <span class="input-icon">
                                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                                </span>
                                <input type="password" id="pin" name="pin" class="form-control"
                                       maxlength="6" pattern="[0-9]{4,6}" placeholder="••••"
                                       autocomplete="off" required inputmode="numeric">
                            </div>
                        </div>

                        <!-- Summary -->
                        <div id="transferSummary" style="display:none;background:#e8f5e9;border:1px solid #c8e6c9;border-radius:12px;padding:16px;margin-bottom:20px;">
                            <div style="font-size:12px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--green);margin-bottom:10px;">Transfer Summary</div>
                            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                <span style="font-size:13px;color:var(--ink-muted);">Send Amount</span>
                                <span style="font-size:13px;font-weight:600;" id="tSummaryAmount">৳0</span>
                            </div>
                            <div style="display:flex;justify-content:space-between;margin-bottom:6px;">
                                <span style="font-size:13px;color:var(--ink-muted);">Fee</span>
                                <span style="font-size:13px;font-weight:600;color:var(--green);">Free</span>
                            </div>
                            <div style="height:1px;background:#c8e6c9;margin:8px 0;"></div>
                            <div style="display:flex;justify-content:space-between;">
                                <span style="font-size:13px;font-weight:600;">Your Balance After</span>
                                <span style="font-size:15px;font-weight:700;" id="tSummaryBalance">—</span>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary w-full btn-lg" id="transferSubmitBtn" disabled>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                            Send Money
                        </button>
                        <div style="font-size:12px;color:var(--ink-faint);text-align:center;margin-top:10px;">Lookup a recipient first to enable the transfer button.</div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Transfer history -->
        <div class="card reveal">
            <div class="card-header"><h4>Transfer History</h4></div>
            <?php if (empty($history)): ?>
            <div class="empty-state">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/></svg>
                <h3>No transfers yet</h3>
                <p>Your transfer history will appear here.</p>
            </div>
            <?php else: ?>
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr><th>Date</th><th>Type</th><th>Party</th><th>Amount</th><th>Ref</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $txn):
                            $sent = $txn['direction'] === 'sent';
                        ?>
                        <tr>
                            <td><?= formatDate($txn['TransactionDate'], 'd M Y') ?><br><span style="font-size:11px;color:var(--ink-faint);"><?= formatDate($txn['TransactionDate'], 'g:i A') ?></span></td>
                            <td><span class="badge badge-<?= $sent ? 'warning' : 'success' ?>"><?= $sent ? 'Sent' : 'Received' ?></span></td>
                            <td style="font-size:13px;"><?= e($txn['other_party'] ?? '—') ?></td>
                            <td><strong style="color:<?= $sent ? 'var(--red)' : 'var(--green)' ?>;"><?= ($sent?'-':'+'). formatBDT($txn['TransactionAmount']) ?></strong></td>
                            <td><code style="font-size:11px;"><?= e($txn['ReferenceNumber']) ?></code></td>
                            <td><span class="badge badge-<?= $txn['TransactionStatus']==='Completed'?'success':'warning' ?>"><?= e($txn['TransactionStatus']) ?></span></td>
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
const availBal = <?= max(0, $balance - $minBal) ?>;
const totalBal = <?= $balance ?>;
let recipientFound = false;

// Recipient lookup
document.getElementById('lookupBtn').addEventListener('click', async () => {
    const acn = document.getElementById('to_account').value.trim();
    if (!acn) { showLookupError('Please enter an account number.'); return; }

    const btn = document.getElementById('lookupBtn');
    btn.textContent = '...'; btn.disabled = true;

    try {
        const r = await fetch('transfer.php?ajax=lookup&acn=' + encodeURIComponent(acn));
        const d = await r.json();

        if (d.success) {
            document.getElementById('toCustomerId').value = d.id;
            document.getElementById('toAccountId').value  = d.accountId;
            document.getElementById('recipientName').textContent  = d.name;
            document.getElementById('recipientType').textContent  = d.type + ' · ' + d.account;
            document.getElementById('recipientInitial').textContent = d.name.charAt(0).toUpperCase();
            document.getElementById('recipientBox').style.display = 'block';
            document.getElementById('lookupError').style.display  = 'none';
            document.getElementById('transferSubmitBtn').disabled = false;
            recipientFound = true;
        } else {
            showLookupError(d.error);
            document.getElementById('recipientBox').style.display  = 'none';
            document.getElementById('transferSubmitBtn').disabled  = true;
            recipientFound = false;
        }
    } catch (e) {
        showLookupError('Lookup failed. Please try again.');
    } finally {
        btn.textContent = 'Lookup'; btn.disabled = false;
    }
});

function showLookupError(msg) {
    document.getElementById('lookupErrorText').textContent = msg;
    document.getElementById('lookupError').style.display = 'flex';
}

function updateTransferSummary() {
    const val = parseFloat(document.getElementById('amount').value) || 0;
    const summary = document.getElementById('transferSummary');
    if (val > 0) {
        summary.style.display = 'block';
        document.getElementById('tSummaryAmount').textContent = '৳' + val.toLocaleString('en-IN', {minimumFractionDigits:2,maximumFractionDigits:2});
        const remain = Math.max(0, totalBal - val);
        document.getElementById('tSummaryBalance').textContent = '৳' + remain.toLocaleString('en-IN', {minimumFractionDigits:2,maximumFractionDigits:2});
    } else {
        summary.style.display = 'none';
    }
}
document.getElementById('amount').addEventListener('input', updateTransferSummary);

// Enter key triggers lookup
document.getElementById('to_account').addEventListener('keydown', (e) => {
    if (e.key === 'Enter') { e.preventDefault(); document.getElementById('lookupBtn').click(); }
});
</script>

<?php include 'includes/footer.php'; ?>
