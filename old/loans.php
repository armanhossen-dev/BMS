<?php
require_once 'config/db.php';
requireLogin();
$pageTitle = 'Loans';

$success = $error = '';

// APPLY LOAN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'apply') {
    $loan_id  = generateLoanId($conn);
    $cust_id  = intval($_POST['customer_id']);
    $acc_id   = intval($_POST['account_id']);
    $type     = $conn->real_escape_string($_POST['loan_type']);
    $amount   = floatval($_POST['loan_amount']);
    $rate     = floatval($_POST['interest_rate']);
    $tenure   = intval($_POST['tenure_months']);
    $monthly  = floatval($_POST['monthly_payment']);

    $mr = $rate / (12 * 100);
    if ($mr > 0) {
        $emi = $amount * $mr * pow(1 + $mr, $tenure) / (pow(1 + $mr, $tenure) - 1);
    } else {
        $emi = $amount / $tenure;
    }
    $emi = round($emi, 2);

    $sql = "INSERT INTO loans (loan_id, customer_id, account_id, loan_type, principal_amount, interest_rate, tenure_months, monthly_payment, amount_remaining, applied_date)
            VALUES ('$loan_id', $cust_id, $acc_id, '$type', $amount, $rate, $tenure, $emi, $amount, CURDATE())";

    if ($conn->query($sql)) {
        $success = "Loan application $loan_id submitted successfully!";
    } else {
        $error = "Error: " . $conn->error;
    }
}

// APPROVE LOAN
if (isset($_GET['approve'])) {
    $lid = intval($_GET['approve']);
    $loan = $conn->query("SELECT * FROM loans WHERE id=$lid AND status='pending'")->fetch_assoc();
    if ($loan) {
        $conn->begin_transaction();
        try {
            $conn->query("UPDATE loans SET status='active', approved_date=CURDATE() WHERE id=$lid");
            // Credit the amount to account
            $conn->query("UPDATE accounts SET balance = balance + {$loan['principal_amount']} WHERE id={$loan['account_id']}");
            $new_bal = $conn->query("SELECT balance FROM accounts WHERE id={$loan['account_id']}")->fetch_assoc()['balance'];
            $txn_id = generateTransactionId();
            $by = $_SESSION['user_id'];
            $conn->query("INSERT INTO transactions (transaction_id, account_id, type, amount, balance_after, description, performed_by)
                          VALUES ('$txn_id', {$loan['account_id']}, 'loan_disbursement', {$loan['principal_amount']}, $new_bal, 'Loan {$loan['loan_id']} disbursed', $by)");
            $conn->commit();
            $success = "Loan {$loan['loan_id']} approved and amount disbursed!";
        } catch (Exception $e) {
            $conn->rollback();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// REJECT
if (isset($_GET['reject'])) {
    $rid = intval($_GET['reject']);
    $conn->query("UPDATE loans SET status='rejected' WHERE id=$rid");
    $success = "Loan application rejected.";
}

// CLOSE LOAN
if (isset($_GET['close'])) {
    $cid = intval($_GET['close']);
    $conn->query("UPDATE loans SET status='closed' WHERE id=$cid");
    $success = "Loan closed.";
}

$loans = $conn->query("
    SELECT l.*, c.full_name, c.customer_id as cust_code, a.account_number
    FROM loans l
    JOIN customers c ON l.customer_id = c.id
    JOIN accounts a ON l.account_id = a.id
    ORDER BY l.id DESC
");

$customers = $conn->query("SELECT id, customer_id, full_name FROM customers WHERE status='active' ORDER BY full_name");
$accounts  = $conn->query("SELECT a.id, a.account_number, c.full_name FROM accounts a JOIN customers c ON a.customer_id=c.id WHERE a.status='active'");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loans – Apex Bank</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
<?php include 'includes/header.php'; ?>

<div class="page-header">
    <h2>🏦 Loan Management</h2>
    <p>Process, approve and track all loan applications.</p>
</div>

<?php if ($success): ?><div class="alert alert-success">✔ <?php echo $success; ?></div><?php endif; ?>
<?php if ($error): ?><div class="alert alert-danger">⚠ <?php echo $error; ?></div><?php endif; ?>

<div class="filter-bar">
    <div class="search-box" style="flex:1;">
        <span class="search-icon">🔍</span>
        <input type="text" id="tableSearch" placeholder="Search loans...">
    </div>
    <button class="btn btn-gold" onclick="openModal('addLoanModal')">+ Apply for Loan</button>
</div>

<div class="card">
    <div class="card-header">
        <h4>All Loan Applications</h4>
        <span class="text-muted"><?php echo $loans->num_rows; ?> loan(s)</span>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr>
                    <th>Loan ID</th>
                    <th>Customer</th>
                    <th>Account</th>
                    <th>Type</th>
                    <th>Principal</th>
                    <th>Rate</th>
                    <th>Tenure</th>
                    <th>Monthly EMI</th>
                    <th>Status</th>
                    <th>Applied</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($loans->num_rows > 0):
                    while ($l = $loans->fetch_assoc()):
                        $badges = ['pending'=>'warning','approved'=>'info','active'=>'success','closed'=>'gray','rejected'=>'danger'];
                        $badge  = $badges[$l['status']] ?? 'gray';
                ?>
                <tr>
                    <td style="color:#D4AF37;font-size:0.82rem;"><?php echo $l['loan_id']; ?></td>
                    <td>
                        <div><?php echo htmlspecialchars($l['full_name']); ?></div>
                        <div style="font-size:0.73rem;color:#888;"><?php echo $l['cust_code']; ?></div>
                    </td>
                    <td style="font-size:0.8rem;color:#aaa;"><?php echo $l['account_number']; ?></td>
                    <td><span class="badge badge-gold"><?php echo ucfirst($l['loan_type']); ?></span></td>
                    <td style="font-family:'Cinzel',serif;color:#D4AF37;"><?php echo formatCurrency($l['principal_amount']); ?></td>
                    <td><?php echo $l['interest_rate']; ?>%</td>
                    <td><?php echo $l['tenure_months']; ?> mo.</td>
                    <td style="font-family:'Cinzel',serif;color:#aaa;"><?php echo formatCurrency($l['monthly_payment']); ?></td>
                    <td><span class="badge badge-<?php echo $badge; ?>"><?php echo $l['status']; ?></span></td>
                    <td style="font-size:0.8rem;"><?php echo date('d M Y', strtotime($l['applied_date'])); ?></td>
                    <td>
                        <div style="display:flex;gap:5px;flex-wrap:wrap;">
                            <?php if ($l['status'] === 'pending'): ?>
                            <a href="loans.php?approve=<?php echo $l['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Approve and disburse this loan?')">✔</a>
                            <a href="loans.php?reject=<?php echo $l['id']; ?>" class="btn btn-danger btn-sm btn-delete-confirm">✕</a>
                            <?php elseif ($l['status'] === 'active'): ?>
                            <a href="loans.php?close=<?php echo $l['id']; ?>" class="btn btn-outline btn-sm" onclick="return confirm('Mark this loan as closed?')">Close</a>
                            <?php else: ?>
                            <span style="font-size:0.73rem;color:#555;">—</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="11" style="text-align:center;padding:40px;color:#888;">No loans found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- APPLY LOAN MODAL -->
<div class="modal-overlay" id="addLoanModal">
    <div class="modal" style="max-width:640px;">
        <div class="modal-header">
            <h4>🏦 Loan Application</h4>
            <button class="modal-close" onclick="closeModal('addLoanModal')">✕</button>
        </div>
        <form method="POST">
            <input type="hidden" name="action" value="apply">
            <input type="hidden" name="monthly_payment" id="monthly_payment" value="0">
            <div class="modal-body">
                <div class="grid-2">
                    <div class="form-group">
                        <label>Customer *</label>
                        <select name="customer_id" class="form-control" required>
                            <option value="">-- Select Customer --</option>
                            <?php while ($c = $customers->fetch_assoc()): ?>
                            <option value="<?php echo $c['id']; ?>"><?php echo $c['customer_id']; ?> – <?php echo htmlspecialchars($c['full_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Account *</label>
                        <select name="account_id" class="form-control" required>
                            <option value="">-- Select Account --</option>
                            <?php while ($a = $accounts->fetch_assoc()): ?>
                            <option value="<?php echo $a['id']; ?>"><?php echo $a['account_number']; ?> – <?php echo htmlspecialchars($a['full_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Loan Type</label>
                        <select name="loan_type" class="form-control">
                            <option value="personal">Personal</option>
                            <option value="home">Home</option>
                            <option value="car">Car</option>
                            <option value="business">Business</option>
                            <option value="education">Education</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Loan Amount (৳) *</label>
                        <input type="number" name="loan_amount" id="loan_amount" class="form-control" placeholder="e.g. 100000" min="1000" required>
                    </div>
                    <div class="form-group">
                        <label>Annual Interest Rate (%)</label>
                        <input type="number" name="interest_rate" id="interest_rate" class="form-control" placeholder="e.g. 12.5" step="0.01" min="0">
                    </div>
                    <div class="form-group">
                        <label>Tenure (Months)</label>
                        <input type="number" name="tenure_months" id="tenure_months" class="form-control" placeholder="e.g. 24" min="1">
                    </div>
                </div>
                <button type="button" id="calcEMI" class="btn btn-outline" style="width:100%;">🧮 Calculate EMI</button>
                <div id="emiResult"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline" onclick="closeModal('addLoanModal')">Cancel</button>
                <button type="submit" class="btn btn-gold">Submit Application</button>
            </div>
        </form>
    </div>
</div>

    </div>
</div>
</div>
<script src="js/main.js"></script>
</body>
</html>