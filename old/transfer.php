<?php
require_once 'config/db.php';
requireLogin();
$pageTitle = 'Transfer';

$success = $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $from_id = intval($_POST['from_account']);
    $to_num  = $conn->real_escape_string(trim($_POST['to_account']));
    $amount  = floatval($_POST['amount']);
    $desc    = $conn->real_escape_string($_POST['description']);
    $by      = $_SESSION['user_id'];

    if ($amount <= 0) {
        $error = "Amount must be greater than 0.";
    } elseif ($from_id === 0 || empty($to_num)) {
        $error = "Please fill in all required fields.";
    } else {
        $fromAcc = $conn->query("SELECT * FROM accounts WHERE id=$from_id AND status='active'")->fetch_assoc();
        $toAcc   = $conn->query("SELECT * FROM accounts WHERE account_number='$to_num' AND status='active'")->fetch_assoc();

        if (!$fromAcc) {
            $error = "Source account not found or inactive.";
        } elseif (!$toAcc) {
            $error = "Destination account not found: $to_num";
        } elseif ($fromAcc['id'] === $toAcc['id']) {
            $error = "Cannot transfer to the same account.";
        } elseif ($fromAcc['balance'] < $amount) {
            $error = "Insufficient balance! Available: " . formatCurrency($fromAcc['balance']);
        } else {
            $fromNew = $fromAcc['balance'] - $amount;
            $toNew   = $toAcc['balance'] + $amount;
            $txnOut  = generateTransactionId();
            $txnIn   = generateTransactionId();

            $conn->begin_transaction();
            try {
                $conn->query("UPDATE accounts SET balance=$fromNew WHERE id={$fromAcc['id']}");
                $conn->query("UPDATE accounts SET balance=$toNew WHERE id={$toAcc['id']}");
                $conn->query("INSERT INTO transactions (transaction_id, account_id, type, amount, balance_after, description, reference_account, performed_by)
                              VALUES ('$txnOut', {$fromAcc['id']}, 'transfer_out', $amount, $fromNew, 'Transfer to {$toAcc['account_number']}: $desc', '{$toAcc['account_number']}', $by)");
                $conn->query("INSERT INTO transactions (transaction_id, account_id, type, amount, balance_after, description, reference_account, performed_by)
                              VALUES ('$txnIn', {$toAcc['id']}, 'transfer_in', $amount, $toNew, 'Transfer from {$fromAcc['account_number']}: $desc', '{$fromAcc['account_number']}', $by)");
                $conn->commit();
                $success = "Transfer of " . formatCurrency($amount) . " successful! TXN: $txnOut";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Transfer failed: " . $e->getMessage();
            }
        }
    }
}

$accounts = $conn->query("
    SELECT a.*, c.full_name FROM accounts a
    JOIN customers c ON a.customer_id = c.id
    WHERE a.status = 'active' ORDER BY c.full_name
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transfer – Apex Bank</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="page-header">
    <h2>⇄ Fund Transfer</h2>
    <p>Transfer funds between bank accounts securely.</p>
</div>

<div style="max-width:700px;">
    <?php if ($success): ?><div class="alert alert-success">✔ <?php echo $success; ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-danger">⚠ <?php echo $error; ?></div><?php endif; ?>

    <div class="card">
        <div class="card-header">
            <h4>Transfer Details</h4>
        </div>
        <div class="card-body">
            <form method="POST">
                <div style="display:grid;gap:0;position:relative;">
                    <!-- FROM -->
                    <div style="background:rgba(212,175,55,0.04);border:1px solid rgba(212,175,55,0.2);border-radius:10px 10px 0 0;padding:20px;">
                        <div style="font-size:0.7rem;color:#888;text-transform:uppercase;margin-bottom:10px;letter-spacing:0.1em;">From Account</div>
                        <div class="form-group" style="margin-bottom:0;">
                            <select name="from_account" id="from_acc" class="form-control" required onchange="loadFromInfo(this)">
                                <option value="">-- Select Source Account --</option>
                                <?php while ($a = $accounts->fetch_assoc()): ?>
                                <option value="<?php echo $a['id']; ?>"
                                        data-balance="<?php echo $a['balance']; ?>"
                                        data-name="<?php echo htmlspecialchars($a['full_name']); ?>"
                                        data-num="<?php echo $a['account_number']; ?>">
                                    <?php echo $a['account_number']; ?> – <?php echo htmlspecialchars($a['full_name']); ?>
                                    (৳ <?php echo number_format($a['balance'],2); ?>)
                                </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div id="from_balance" style="font-size:0.82rem;color:#2ECC71;margin-top:8px;display:none;"></div>
                    </div>

                    <!-- ARROW -->
                    <div style="text-align:center;background:var(--black-3);padding:10px;position:relative;">
                        <span style="font-size:1.4rem;color:#D4AF37;">⬇</span>
                    </div>

                    <!-- TO -->
                    <div style="background:rgba(52,152,219,0.04);border:1px solid rgba(52,152,219,0.2);border-radius:0 0 10px 10px;padding:20px;">
                        <div style="font-size:0.7rem;color:#888;text-transform:uppercase;margin-bottom:10px;letter-spacing:0.1em;">To Account</div>
                        <div class="form-group" style="margin-bottom:0;">
                            <input type="text" name="to_account" class="form-control"
                                   placeholder="Enter destination account number (e.g. ACC-0001001)"
                                   required>
                        </div>
                    </div>
                </div>

                <div class="divider"></div>

                <div class="grid-2">
                    <div class="form-group">
                        <label>Amount (৳) *</label>
                        <input type="number" name="amount" id="tr_amount" class="form-control amount-input"
                               placeholder="0.00" min="1" step="0.01" required oninput="updateFromBalance()">
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Transfer note...">
                    </div>
                </div>

                <div id="tr_summary" style="display:none;background:rgba(212,175,55,0.06);border:1px solid rgba(212,175,55,0.2);border-radius:8px;padding:14px;margin-bottom:18px;font-size:0.85rem;">
                    Balance after transfer: <strong id="tr_after" style="color:#D4AF37;font-family:'Cinzel',serif;"></strong>
                </div>

                <button type="submit" class="btn btn-gold btn-full btn-lg"
                        onclick="return confirm('Confirm this transfer?')">
                    ⇄ Confirm Transfer
                </button>
            </form>
        </div>
    </div>
</div>

<script>
function loadFromInfo(sel) {
    const opt = sel.selectedOptions[0];
    const el = document.getElementById('from_balance');
    if (opt.value) {
        el.textContent = 'Available Balance: ৳ ' + parseFloat(opt.dataset.balance).toLocaleString('en-US', {minimumFractionDigits:2});
        el.style.display = 'block';
        updateFromBalance();
    } else {
        el.style.display = 'none';
    }
}

function updateFromBalance() {
    const sel = document.getElementById('from_acc');
    const amt = parseFloat(document.getElementById('tr_amount').value || 0);
    const opt = sel.selectedOptions[0];
    if (opt && opt.dataset.balance && amt > 0) {
        const after = parseFloat(opt.dataset.balance) - amt;
        const sumEl = document.getElementById('tr_summary');
        const afterEl = document.getElementById('tr_after');
        afterEl.textContent = '৳ ' + after.toLocaleString('en-US', {minimumFractionDigits:2});
        afterEl.style.color = after < 0 ? '#E74C3C' : '#D4AF37';
        sumEl.style.display = 'block';
    }
}
</script>

    </div>
</div>
</div>
<script src="js/main.js"></script>
</body>
</html>