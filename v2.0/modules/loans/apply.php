<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/language.php';
require_once __DIR__ . '/../../includes/functions.php';
require_customer();

$customerId = $_SESSION['customer_id'];
$accounts = get_accounts($customerId);
$products = $pdo->query("SELECT * FROM LOAN_PRODUCTS WHERE is_active = 1")->fetchAll();
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $productId = (int)$_POST['loan_product_id'];
    $amount = (float)$_POST['amount'];
    $tenure = (int)$_POST['tenure'];
    $purpose = trim($_POST['purpose'] ?? '');
    $disbAccount = $_POST['disbursement_account'] ?? null;

    $stmt = $pdo->prepare("SELECT * FROM LOAN_PRODUCTS WHERE loan_product_id = ?");
    $stmt->execute([$productId]);
    $product = $stmt->fetch();

    if (!$product) { $error = 'Invalid loan product.'; }
    elseif ($amount < $product['min_amount'] || $amount > $product['max_amount']) {
        $error = "Amount must be between " . currency($product['min_amount']) . " and " . currency($product['max_amount']) . ".";
    } elseif ($tenure < $product['min_tenure_months'] || $tenure > $product['max_tenure_months']) {
        $error = "Tenure must be between {$product['min_tenure_months']} and {$product['max_tenure_months']} months.";
    } else {
        $rate = $product['interest_rate'];
        $monthlyRate = $rate / 12 / 100;
        $emi = $amount * $monthlyRate * pow(1 + $monthlyRate, $tenure) / (pow(1 + $monthlyRate, $tenure) - 1);
        $total = $emi * $tenure;

        $stmt = $pdo->prepare("INSERT INTO LOANS (customer_id,loan_product_id,loan_amount,tenure_months,interest_rate,emi_amount,total_payable,status,disbursement_account,purpose)
                                VALUES (?,?,?,?,?,?,?, 'Pending', ?, ?)");
        $stmt->execute([$customerId, $productId, $amount, $tenure, $rate, $emi, $total, $disbAccount, $purpose]);

        notify($customerId, 'Loan Application Submitted', "Your {$product['product_name']} application for " . currency($amount) . " is under review.", 'info');
        $success = "Loan application submitted! Estimated EMI: " . currency($emi) . "/month.";
    }
}

$stmt = $pdo->prepare("SELECT l.*, lp.product_name FROM LOANS l JOIN LOAN_PRODUCTS lp ON l.loan_product_id = lp.loan_product_id WHERE l.customer_id = ? ORDER BY l.application_date DESC");
$stmt->execute([$customerId]);
$myLoans = $stmt->fetchAll();

$pageTitle = 'Loans';
$activeNav = 'loans';
require __DIR__ . '/../../includes/header.php';
?>

<div class="page-head">
  <div><h1>Loan Application</h1><div class="sub">Apply for Home, Personal, Car, or Education loans.</div></div>
</div>

<?php if ($error): ?><div class="badge badge-danger" style="display:block;padding:12px 14px;margin-bottom:18px;">⚠️ <?= clean($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="badge badge-success" style="display:block;padding:12px 14px;margin-bottom:18px;">✅ <?= clean($success) ?></div><?php endif; ?>

<div class="grid grid-main">
  <div class="card">
    <div class="card-head"><span class="card-title">New Application</span></div>
    <form method="POST" id="loanForm">
      <?= csrf_field() ?>
      <div class="field">
        <label>Loan Product</label>
        <select class="input" name="loan_product_id" id="loanProduct" required>
          <?php foreach ($products as $p): ?>
            <option value="<?= $p['loan_product_id'] ?>"
              data-min="<?= $p['min_amount'] ?>" data-max="<?= $p['max_amount'] ?>"
              data-mintenure="<?= $p['min_tenure_months'] ?>" data-maxtenure="<?= $p['max_tenure_months'] ?>"
              data-rate="<?= $p['interest_rate'] ?>">
              <?= clean($p['product_name']) ?> (<?= $p['interest_rate'] ?>% p.a.)
            </option>
          <?php endforeach; ?>
        </select>
        <div class="hint" id="rangeHint"></div>
      </div>
      <div class="field"><label>Loan Amount</label>
        <div class="input-group"><span class="input-prefix"><?= APP_CURRENCY ?></span><input class="input" type="number" name="amount" id="loanAmount" required></div>
      </div>
      <div class="field"><label>Tenure (months)</label><input class="input" type="number" name="tenure" id="loanTenure" required></div>
      <div class="field"><label>Disbursement Account</label>
        <select class="input" name="disbursement_account">
          <?php foreach ($accounts as $a): ?><option value="<?= $a['AccountNumber'] ?>"><?= mask_account($a['AccountNumber']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Purpose</label><textarea class="input" name="purpose"></textarea></div>
      <div class="card" style="background:var(--surface-2);padding:14px;margin-bottom:16px;">
        <div class="flex justify-between text-sm"><span class="text-dim">Estimated EMI</span><strong id="emiPreview" class="mono">--</strong></div>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Submit Application</button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><span class="card-title">Your Loans</span></div>
    <?php if (empty($myLoans)): ?><div class="empty-state">No loan applications yet.</div>
    <?php else: foreach ($myLoans as $l): ?>
      <div style="padding:12px 0;border-bottom:1px solid var(--border-soft);">
        <div class="flex justify-between items-center">
          <strong class="text-sm"><?= clean($l['product_name']) ?></strong>
          <span class="badge <?= $l['status']==='Active'?'badge-success':($l['status']==='Rejected'?'badge-danger':'badge-warning') ?>"><?= clean($l['status']) ?></span>
        </div>
        <div class="text-dim text-xs mt-8"><?= currency($l['loan_amount']) ?> · <?= $l['tenure_months'] ?> months · EMI <?= currency($l['emi_amount']) ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<script>
function updateLoanCalc() {
  const sel = document.getElementById('loanProduct');
  const opt = sel.options[sel.selectedIndex];
  const min = parseFloat(opt.dataset.min), max = parseFloat(opt.dataset.max);
  const minT = parseInt(opt.dataset.mintenure), maxT = parseInt(opt.dataset.maxtenure);
  const rate = parseFloat(opt.dataset.rate);
  document.getElementById('rangeHint').textContent = `Amount: ${min.toLocaleString()}–${max.toLocaleString()} · Tenure: ${minT}–${maxT} months`;

  const amount = parseFloat(document.getElementById('loanAmount').value) || 0;
  const tenure = parseInt(document.getElementById('loanTenure').value) || 0;
  if (amount > 0 && tenure > 0) {
    const mr = rate / 12 / 100;
    const emi = amount * mr * Math.pow(1+mr, tenure) / (Math.pow(1+mr, tenure) - 1);
    document.getElementById('emiPreview').textContent = '<?= APP_CURRENCY ?> ' + emi.toFixed(2);
  }
}
document.getElementById('loanProduct').addEventListener('change', updateLoanCalc);
document.getElementById('loanAmount').addEventListener('input', updateLoanCalc);
document.getElementById('loanTenure').addEventListener('input', updateLoanCalc);
updateLoanCalc();
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
