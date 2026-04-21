<?php
require_once 'config/db.php';
requireLogin();
$pageTitle = 'Accounts';

$success = $error = '';
$isAdmin = isAdmin();  // true for admin/staff, false for customer role

// Determine whose accounts to show
// If staff/admin: show all (or filtered by ?customer=X)
// If customer role: show ONLY their own accounts (linked via session customer_id)
$filterCustomer = isset($_GET['customer']) ? intval($_GET['customer']) : 0;

// If the logged-in user is a customer, lock them to their own customer_id only
$sessionCustomerId = $_SESSION['customer_id'] ?? 0; // set at login for customer accounts
$viewingOwnOnly    = !$isAdmin;                      // customers always see only own accounts

// ── ADD ACCOUNT (admin/staff only) ──────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add') {
    if (!$isAdmin) {
        $error = "⛔ Permission denied. Only staff can open new accounts.";
    } else {
        $acc_num  = generateAccountNumber($conn);
        $cust_id  = intval($_POST['customer_id']);
        $type     = $conn->real_escape_string($_POST['account_type']);
        $balance  = floatval($_POST['initial_deposit']);
        $rate     = floatval($_POST['interest_rate']);
        $date     = $conn->real_escape_string($_POST['opened_date']);

        $sql = "INSERT INTO accounts (account_number, customer_id, account_type, balance, interest_rate, opened_date)
                VALUES ('$acc_num', $cust_id, '$type', $balance, $rate, '$date')";

        if ($conn->query($sql)) {
            if ($balance > 0) {
                $acc_id = $conn->insert_id;
                $txn_id = generateTransactionId();
                $by     = $_SESSION['user_id'];
                $conn->query("INSERT INTO transactions (transaction_id, account_id, type, amount, balance_after, description, performed_by)
                              VALUES ('$txn_id', $acc_id, 'deposit', $balance, $balance, 'Initial deposit on account opening', $by)");
            }
            $success = "Account $acc_num created successfully!";
        } else {
            $error = "Error: " . $conn->error;
        }
    }
}

// ── DELETE (admin/staff only) ────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    if (!$isAdmin) {
        $error = "⛔ Permission denied. You cannot delete accounts.";
    } else {
        $did = intval($_GET['delete']);
        $conn->query("DELETE FROM accounts WHERE id=$did");
        $success = "Account deleted.";
    }
}

// ── FREEZE / UNFREEZE (admin/staff only) ─────────────────────────────────────
if (isset($_GET['freeze'])) {
    if (!$isAdmin) {
        $error = "⛔ Permission denied. You cannot freeze or unfreeze accounts.";
    } else {
        $fid = intval($_GET['freeze']);
        $cur = $conn->query("SELECT status FROM accounts WHERE id=$fid")->fetch_assoc()['status'];
        $new = $cur === 'active' ? 'frozen' : 'active';
        $conn->query("UPDATE accounts SET status='$new' WHERE id=$fid");
        $success = "Account status updated to '$new'.";
    }
}

// ── FETCH ACCOUNTS ────────────────────────────────────────────────────────────
if ($viewingOwnOnly && $sessionCustomerId) {
    // Customer sees only their own accounts
    $where = "WHERE a.customer_id = $sessionCustomerId";
} elseif ($filterCustomer) {
    $where = "WHERE a.customer_id = $filterCustomer";
} else {
    $where = '';
}

$accounts = $conn->query("
    SELECT a.*, c.full_name, c.customer_id as cust_code
    FROM accounts a
    JOIN customers c ON a.customer_id = c.id
    $where
    ORDER BY a.id DESC
");

// CUSTOMERS FOR DROPDOWN (admin/staff only)
$allCustomers = $isAdmin
    ? $conn->query("SELECT id, customer_id, full_name FROM customers WHERE status='active' ORDER BY full_name")
    : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounts – Apex Bank</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="page-header">
    <h2>💳 <?php echo $viewingOwnOnly ? 'My Accounts' : 'Account Management'; ?></h2>
    <p><?php echo $viewingOwnOnly
        ? 'View your bank accounts and transaction history.'
        : 'Open, manage and monitor all bank accounts.';
    ?></p>
</div>

<?php if ($success): ?><div class="alert alert-success">✔ <?php echo $success; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger"><?php echo $error; ?></div><?php endif; ?>

<!-- ── PERMISSION NOTICE FOR NON-ADMIN ── -->
<?php if (!$isAdmin): ?>
<div class="alert alert-info" style="display:flex;align-items:center;gap:10px;">
    <span style="font-size:1.1rem;">ℹ</span>
    <div>
        <strong style="color:#3498DB;">View-only mode.</strong>
        <span style="color:#aaa;font-size:0.85rem;"> You can view your accounts and initiate deposits or withdrawals. To open, freeze, or close an account, please contact a bank officer.</span>
    </div>
</div>
<?php endif; ?>

<!-- FILTER BAR -->
<div class="filter-bar">
    <div class="search-box" style="flex:1;">
        <span class="search-icon">🔍</span>
        <input type="text" id="tableSearch" placeholder="Search accounts...">
    </div>
    <?php if ($isAdmin && $filterCustomer): ?>
        <a href="accounts.php" class="btn btn-outline">✕ Show All</a>
    <?php endif; ?>
    <?php if ($isAdmin): ?>
        <button class="btn btn-gold" onclick="openModal('addAccountModal')">+ Open Account</button>
    <?php endif; ?>
</div>

<!-- ACCOUNTS TABLE -->
<div class="card">
    <div class="card-header">
        <h4><?php echo $viewingOwnOnly ? 'My Accounts' : 'All Accounts'; ?></h4>
        <span class="text-muted"><?php echo $accounts->num_rows; ?> account(s)</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Account Number</th>
                    <?php if ($isAdmin): ?><th>Customer</th><?php endif; ?>
                    <th>Type</th>
                    <th>Balance</th>
                    <th>Interest Rate</th>
                    <th>Status</th>
                    <th>Opened</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($accounts->num_rows > 0):
                    $i = 1;
                    while ($a = $accounts->fetch_assoc()):
                        $typeColors = ['savings'=>'gold','current'=>'info','fixed_deposit'=>'success','loan'=>'warning'];
                        $tColor = $typeColors[$a['account_type']] ?? 'gray';
                        $isActive = $a['status'] === 'active';
                        $isFrozen = $a['status'] === 'frozen';
                ?>
                <tr>
                    <td><?php echo $i++; ?></td>
                    <td>
                        <span style="color:#D4AF37;font-family:'Cinzel',serif;font-size:0.82rem;">
                            <?php echo $a['account_number']; ?>
                        </span>
                    </td>

                    <?php if ($isAdmin): ?>
                    <td>
                        <div style="font-size:0.85rem;"><?php echo htmlspecialchars($a['full_name']); ?></div>
                        <div style="font-size:0.73rem;color:#888;"><?php echo $a['cust_code']; ?></div>
                    </td>
                    <?php endif; ?>

                    <td>
                        <span class="badge badge-<?php echo $tColor; ?>">
                            <?php echo str_replace('_', ' ', $a['account_type']); ?>
                        </span>
                    </td>
                    <td style="font-family:'Cinzel',serif;color:#D4AF37;"><?php echo formatCurrency($a['balance']); ?></td>
                    <td><?php echo $a['interest_rate']; ?>%</td>
                    <td>
                        <span class="badge badge-<?php echo $isActive ? 'success' : ($isFrozen ? 'info' : 'danger'); ?>">
                            <?php if ($isFrozen): ?>❄ <?php endif; ?><?php echo $a['status']; ?>
                        </span>
                    </td>
                    <td><?php echo date('d M Y', strtotime($a['opened_date'])); ?></td>

                    <!-- ── ACTIONS: controlled by role ── -->
                    <td>
                        <div style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">

                            <?php if ($isActive): ?>
                                <!-- Deposit & Withdraw: available to everyone for their own accounts -->
                                <a href="deposit.php?account=<?php echo $a['id']; ?>"
                                   class="btn btn-success btn-sm" title="Deposit money">⬆ Deposit</a>
                                <a href="withdraw.php?account=<?php echo $a['id']; ?>"
                                   class="btn btn-danger btn-sm" title="Withdraw money">⬇ Withdraw</a>
                            <?php elseif ($isFrozen): ?>
                                <span style="font-size:0.75rem;color:#3498DB;padding:4px 8px;background:rgba(52,152,219,0.1);border-radius:6px;border:1px solid rgba(52,152,219,0.2);">
                                    ❄ Account Frozen
                                </span>
                            <?php else: ?>
                                <span style="font-size:0.75rem;color:#E74C3C;padding:4px 8px;background:rgba(231,76,60,0.1);border-radius:6px;border:1px solid rgba(231,76,60,0.2);">
                                    ✕ Inactive
                                </span>
                            <?php endif; ?>

                            <?php if ($isAdmin): ?>
                                <!-- ADMIN-ONLY ACTIONS -->
                                <a href="accounts.php?freeze=<?php echo $a['id']; ?>"
                                   class="btn btn-outline btn-sm"
                                   title="<?php echo $isFrozen ? 'Unfreeze account' : 'Freeze account'; ?>"
                                   onclick="return confirm('<?php echo $isFrozen ? 'Unfreeze' : 'Freeze'; ?> this account?')">
                                    <?php echo $isFrozen ? '🔓 Unfreeze' : '❄ Freeze'; ?>
                                </a>
                                <a href="accounts.php?delete=<?php echo $a['id']; ?>"
                                   class="btn btn-danger btn-sm btn-delete-confirm"
                                   title="Delete account">
                                    🗑
                                </a>
                            <?php endif; ?>

                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr>
                    <td colspan="<?php echo $isAdmin ? 9 : 8; ?>"
                        style="text-align:center;padding:50px;color:#888;">
                        <?php if ($viewingOwnOnly): ?>
                            <div style="font-size:2rem;margin-bottom:10px;">💳</div>
                            <div>You have no accounts yet.</div>
                            <div style="font-size:0.78rem;margin-top:6px;color:#666;">Please contact a bank officer to open an account.</div>
                        <?php else: ?>
                            No accounts found.
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ── ADMIN ONLY: Add Account Modal ─────────────────────────────────────────── -->
<?php if ($isAdmin): ?>
<div class="modal-overlay" id="addAccountModal">
    <div class="modal">
        <div class="modal-header">
            <h4>➕ Open New Account</h4>
            <button class="modal-close" onclick="closeModal('addAccountModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="add">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group" style="grid-column:1/-1;">
                        <label>Select Customer *</label>
                        <select name="customer_id" class="form-control" required>
                            <option value="">-- Choose Customer --</option>
                            <?php
                            $allCustomers->data_seek(0);
                            while ($c = $allCustomers->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>"
                                    <?php echo $filterCustomer == $c['id'] ? 'selected' : ''; ?>>
                                <?php echo $c['customer_id']; ?> – <?php echo htmlspecialchars($c['full_name']); ?>
                            </option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Account Type *</label>
                        <select name="account_type" class="form-control" required>
                            <option value="savings">Savings Account</option>
                            <option value="current">Current Account</option>
                            <option value="fixed_deposit">Fixed Deposit</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Initial Deposit (৳)</label>
                        <input type="number" name="initial_deposit" class="form-control amount-input"
                               placeholder="0.00" min="0" step="0.01">
                    </div>
                    <div class="form-group">
                        <label>Interest Rate (%)</label>
                        <input type="number" name="interest_rate" class="form-control"
                               placeholder="e.g. 5.50" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Opening Date *</label>
                        <input type="date" name="opened_date" class="form-control"
                               value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addAccountModal')">Cancel</button>
                <button type="submit" class="btn btn-gold">✔ Open Account</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

    </div>
</div>
</div>
<script src="js/main.js"></script>
</body>
</html>