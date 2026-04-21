<?php
require_once 'config/db.php';
requireLogin();
$pageTitle = 'Withdraw';

$success = $error = '';
$preAccount = isset($_GET['account']) ? intval($_GET['account']) : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acc_id = intval($_POST['account_id']);
    $amount = floatval($_POST['amount']);
    $desc   = $conn->real_escape_string($_POST['description']);
    $by     = $_SESSION['user_id'];

    if ($amount <= 0) {
        $error = "Amount must be greater than 0.";
    } else {
        $acc = $conn->query("SELECT * FROM accounts WHERE id=$acc_id AND status='active'")->fetch_assoc();
        if (!$acc) {
            $error = "Account not found or is not active.";
        } elseif ($acc['balance'] < $amount) {
            $error = "Insufficient balance! Available: " . formatCurrency($acc['balance']);
        } else {
            $new_balance = $acc['balance'] - $amount;
            $txn_id      = generateTransactionId();

            $conn->begin_transaction();
            try {
                $conn->query("UPDATE accounts SET balance=$new_balance WHERE id=$acc_id");
                $conn->query("INSERT INTO transactions (transaction_id, account_id, type, amount, balance_after, description, performed_by)
                              VALUES ('$txn_id', $acc_id, 'withdrawal', $amount, $new_balance, '$desc', $by)");
                $conn->commit();
                $success = "Withdrawal of " . formatCurrency($amount) . " processed! TXN: $txn_id";
            } catch (Exception $e) {
                $conn->rollback();
                $error = "Transaction failed: " . $e->getMessage();
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
    <title>Withdraw – Apex Bank</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="page-header">
    <h2>⬇ Withdraw Money</h2>
    <p>Process cash withdrawal from a customer account.</p>
</div>

<div class="grid-2" style="max-width:900px;">
    <div>
        <?php if ($success): ?><div class="alert alert-success">✔ <?php echo $success; ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert alert-danger">⚠ <?php echo $error; ?></div><?php endif; ?>

        <div class="card">
            <div class="card-header"><h4>Withdrawal Form</h4></div>
            <div class="card-body">
                <form method="POST">
                    <div class="form-group">
                        <label>Select Account *</label>
                        <select name="account_id" id="acc_select" class="form-control" required onchange="loadAccountInfo(this)">
                            <option value="">-- Choose Account --</option>
                            <?php while ($a = $accounts->fetch_assoc()): ?>
                            <option value="<?php echo $a['id']; ?>"
                                    data-balance="<?php echo $a['balance']; ?>"
                                    data-name="<?php echo htmlspecialchars($a['full_name']); ?>"
                                    data-type="<?php echo $a['account_type']; ?>"
                                    <?php echo $preAccount == $a['id'] ? 'selected' : ''; ?>>
                                <?php echo $a['account_number']; ?> – <?php echo htmlspecialchars($a['full_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div id="accInfo" style="display:none;background:rgba(231,76,60,0.06);border:1px solid rgba(231,76,60,0.2);border-radius:8px;padding:16px;margin-bottom:20px;">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                            <div>
                                <div class="text-muted">Account Holder</div>
                                <div style="color:#fff;" id="info_name">—</div>
                            </div>
                            <div>
                                <div class="text-muted">Account Type</div>
                                <div style="color:#D4AF37;text-transform:capitalize;" id="info_type">—</div>
                            </div>
                            <div>
                                <div class="text-muted">Available Balance</div>
                                <div style="color:#2ECC71;font-family:'Cinzel',serif;" id="info_balance">—</div>
                            </div>
                            <div>
                                <div class="text-muted">Balance After Withdrawal</div>
                                <div style="font-family:'Cinzel',serif;" id="info_after">—</div>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Withdrawal Amount (৳) *</label>
                        <input type="number" name="amount" id="wd_amount" class="form-control amount-input"
                               placeholder="0.00" min="1" step="0.01" required oninput="updateAfterBalance()">
                    </div>
                    <div class="form-group">
                        <label>Description / Note</label>
                        <input type="text" name="description" class="form-control" placeholder="e.g. Cash withdrawal...">
                    </div>
                    <button type="submit" class="btn btn-danger btn-full btn-lg"
                            onclick="return confirm('Confirm withdrawal?')">
                        ⬇ Confirm Withdrawal
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div>
        <div class="card">
            <div class="card-header"><h4>Withdrawal Guidelines</h4></div>
            <div class="card-body">
                <div style="font-size:0.83rem;color:#aaa;line-height:2;">
                    <div style="color:#E74C3C;margin-bottom:12px;">⚠ Important Rules:</div>
                    <div>• Minimum withdrawal: ৳ 100</div>
                    <div>• Cannot exceed available balance</div>
                    <div>• Frozen accounts cannot be withdrawn</div>
                    <div>• All transactions are logged</div>
                    <div>• Reversals require admin approval</div>
                </div>
                <div class="divider"></div>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;">
                    <?php foreach ([500,1000,5000,10000,20000,50000] as $amt): ?>
                    <button type="button" class="btn btn-outline" onclick="setAmount(<?php echo $amt; ?>)">
                        ৳ <?php echo number_format($amt); ?>
                    </button>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function loadAccountInfo(sel) {
    const opt = sel.selectedOptions[0];
    const info = document.getElementById('accInfo');
    if (opt.value) {
        document.getElementById('info_name').textContent = opt.dataset.name;
        document.getElementById('info_type').textContent = opt.dataset.type.replace('_',' ');
        document.getElementById('info_balance').textContent = '৳ ' + parseFloat(opt.dataset.balance).toLocaleString('en-US', {minimumFractionDigits:2});
        info.style.display = 'block';
        updateAfterBalance();
    } else {
        info.style.display = 'none';
    }
}

function updateAfterBalance() {
    const sel = document.getElementById('acc_select');
    const amt = parseFloat(document.getElementById('wd_amount').value || 0);
    const opt = sel.selectedOptions[0];
    if (opt && opt.dataset.balance) {
        const after = parseFloat(opt.dataset.balance) - amt;
        const el = document.getElementById('info_after');
        el.textContent = '৳ ' + after.toLocaleString('en-US', {minimumFractionDigits:2});
        el.style.color = after < 0 ? '#E74C3C' : '#D4AF37';
    }
}

function setAmount(val) {
    document.getElementById('wd_amount').value = val;
    updateAfterBalance();
}

window.addEventListener('load', () => {
    const sel = document.getElementById('acc_select');
    if (sel.value) loadAccountInfo(sel);
});
</script>

    </div>
</div>
</div>
<script src="js/main.js"></script>
</body>
</html>