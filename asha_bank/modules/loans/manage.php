<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/language.php';
require_once __DIR__ . '/../../includes/functions.php';
require_customer();

$customerId = $_SESSION['customer_id'];
$loanId = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("SELECT l.*, lp.product_name FROM LOANS l JOIN LOAN_PRODUCTS lp ON l.loan_product_id=lp.loan_product_id WHERE l.loan_id=? AND l.customer_id=?");
$stmt->execute([$loanId, $customerId]);
$loan = $stmt->fetch();
if (!$loan) { header('Location: ' . BASE_URL . '/modules/loans/apply.php'); exit; }

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_emi'])) {
    verify_csrf();
    $emiId = (int)$_POST['emi_id'];
    $accountNum = $_POST['account'];
    $stmt = $pdo->prepare("SELECT * FROM LOAN_EMI_SCHEDULE WHERE emi_id = ? AND loan_id = ?");
    $stmt->execute([$emiId, $loanId]);
    $emi = $stmt->fetch();
    $stmt = $pdo->prepare("SELECT * FROM ACCOUNT WHERE AccountNumber = ? AND CustomerID = ?");
    $stmt->execute([$accountNum, $customerId]);
    $acc = $stmt->fetch();

    if ($emi && $acc && $acc['AvailableBalance'] >= $emi['emi_amount']) {
        $pdo->beginTransaction();
        $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance - ? WHERE AccountNumber = ?")->execute([$emi['emi_amount'], $accountNum]);
        $ref = gen_reference('EMI');
        $pdo->prepare("INSERT INTO TRANSACTION (TransactionTypeID,TransactionAmount,FromAccountNumber,FromCustomerID,Description,TransactionStatus,ReferenceNumber) VALUES (7,?,?,?,?, 'Completed', ?)")
            ->execute([$emi['emi_amount'], $accountNum, $customerId, "EMI Payment - {$loan['product_name']}", $ref]);
        $txnId = $pdo->lastInsertId();
        $pdo->prepare("UPDATE LOAN_EMI_SCHEDULE SET status='Paid', paid_date=NOW(), transaction_id=? WHERE emi_id=?")->execute([$txnId, $emiId]);
        $pdo->commit();
        flash('page', 'EMI paid successfully.', 'success');
    } else {
        flash('page', 'Insufficient balance or invalid EMI.', 'danger');
    }
    header('Location: ' . BASE_URL . '/modules/loans/manage.php?id=' . $loanId); exit;
}

$stmt = $pdo->prepare("SELECT * FROM LOAN_EMI_SCHEDULE WHERE loan_id = ? ORDER BY due_date");
$stmt->execute([$loanId]);
$schedule = $stmt->fetchAll();
$accounts = get_accounts($customerId);

$pageTitle = 'Loan Details';
$activeNav = 'loans';
require __DIR__ . '/../../includes/header.php';
$f = flash('page');
?>
<div class="page-head">
  <div><h1><?= clean($loan['product_name']) ?></h1><div class="sub">Loan #<?= $loan['loan_id'] ?> · <?= currency($loan['loan_amount']) ?> over <?= $loan['tenure_months'] ?> months</div></div>
  <span class="badge <?= $loan['status']==='Active'?'badge-success':($loan['status']==='Rejected'?'badge-danger':'badge-warning') ?>"><?= clean($loan['status']) ?></span>
</div>
<?php if ($f): ?><div class="badge badge-<?= $f['type'] ?>" style="display:block;padding:12px 14px;margin-bottom:18px;"><?= clean($f['msg']) ?></div><?php endif; ?>

<div class="grid grid-4">
  <div class="card stat-card"><div class="stat-label">EMI Amount</div><div class="stat-value mono"><?= currency($loan['emi_amount']) ?></div></div>
  <div class="card stat-card"><div class="stat-label">Interest Rate</div><div class="stat-value"><?= $loan['interest_rate'] ?>%</div></div>
  <div class="card stat-card"><div class="stat-label">Total Payable</div><div class="stat-value mono"><?= currency($loan['total_payable']) ?></div></div>
  <div class="card stat-card"><div class="stat-label">Tenure</div><div class="stat-value"><?= $loan['tenure_months'] ?> mo</div></div>
</div>

<div class="card mt-16">
  <div class="card-head"><span class="card-title">EMI Schedule</span></div>
  <?php if (empty($schedule)): ?><div class="empty-state">EMI schedule will appear once the loan is approved.</div>
  <?php else: ?>
  <div class="table-wrap"><table class="data-table">
    <thead><tr><th>Due Date</th><th>EMI</th><th>Principal</th><th>Interest</th><th>Balance</th><th>Status</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($schedule as $e): ?>
      <tr>
        <td><?= date('M j, Y', strtotime($e['due_date'])) ?></td>
        <td class="mono"><?= currency($e['emi_amount']) ?></td>
        <td class="mono"><?= currency($e['principal_amount']) ?></td>
        <td class="mono"><?= currency($e['interest_amount']) ?></td>
        <td class="mono"><?= currency($e['outstanding_balance']) ?></td>
        <td><span class="badge <?= $e['status']==='Paid'?'badge-success':($e['status']==='Overdue'?'badge-danger':'badge-warning') ?>"><?= clean($e['status']) ?></span></td>
        <td>
          <?php if ($e['status'] !== 'Paid'): ?>
          <button class="btn btn-sm btn-primary" data-modal-open="pay-<?= $e['emi_id'] ?>">Pay</button>
          <div class="modal-overlay" id="pay-<?= $e['emi_id'] ?>">
            <div class="modal">
              <div class="modal-head"><h3 style="font-size:16px;">Pay EMI</h3><span class="modal-close" data-modal-close>✕</span></div>
              <form method="POST">
                <?= csrf_field() ?><input type="hidden" name="emi_id" value="<?= $e['emi_id'] ?>">
                <div class="field"><label>From Account</label>
                  <select class="input" name="account" required><?php foreach ($accounts as $a): ?><option value="<?= $a['AccountNumber'] ?>"><?= mask_account($a['AccountNumber']) ?> (<?= currency($a['AvailableBalance']) ?>)</option><?php endforeach; ?></select>
                </div>
                <div class="text-sm text-muted mt-8" style="margin-bottom:16px;">Amount due: <strong class="mono"><?= currency($e['emi_amount']) ?></strong></div>
                <button type="submit" name="pay_emi" class="btn btn-primary btn-block">Confirm Payment</button>
              </form>
            </div>
          </div>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
