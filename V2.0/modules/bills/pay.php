<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/language.php';
require_once __DIR__ . '/../../includes/functions.php';
require_customer();

$customerId = $_SESSION['customer_id'];
$accounts = get_accounts($customerId);
$categories = $pdo->query("SELECT * FROM BILL_CATEGORIES WHERE is_active = 1")->fetchAll();
$providers = $pdo->query("SELECT * FROM BILL_PROVIDERS WHERE is_active = 1")->fetchAll();
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $providerId = (int)$_POST['provider_id'];
    $accNum = trim($_POST['account_number']);
    $amount = (float)$_POST['amount'];
    $fromAccount = $_POST['from_account'];
    $isRecurring = isset($_POST['is_recurring']) ? 1 : 0;

    $stmt = $pdo->prepare("SELECT * FROM ACCOUNT WHERE AccountNumber = ? AND CustomerID = ?");
    $stmt->execute([$fromAccount, $customerId]);
    $acc = $stmt->fetch();

    if (!$acc) { $error = 'Invalid account.'; }
    elseif ($amount <= 0) { $error = 'Enter a valid amount.'; }
    elseif ($acc['AvailableBalance'] < $amount) { $error = 'Insufficient balance.'; }
    else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance - ? WHERE AccountNumber = ?")->execute([$amount, $fromAccount]);
            $ref = gen_reference('BILL');
            $pdo->prepare("INSERT INTO TRANSACTION (TransactionTypeID,TransactionAmount,FromAccountNumber,FromCustomerID,Description,TransactionStatus,ReferenceNumber) VALUES (6,?,?,?,?, 'Completed', ?)")
                ->execute([$amount, $fromAccount, $customerId, 'Bill Payment', $ref]);
            $txnId = $pdo->lastInsertId();
            $pdo->prepare("INSERT INTO BILL_PAYMENTS (customer_id,provider_id,account_number,amount,payment_date,reference_number,status,transaction_id,is_recurring)
                            VALUES (?,?,?,?,NOW(),?, 'Completed', ?, ?)")
                ->execute([$customerId, $providerId, $accNum, $amount, $ref, $txnId, $isRecurring]);
            notify($customerId, 'Bill Paid', "Payment of " . currency($amount) . " completed successfully.", 'success');
            $pdo->commit();
            $success = "Bill payment of " . currency($amount) . " successful. Ref: $ref";
            $accounts = get_accounts($customerId);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Payment failed. Please try again.';
        }
    }
}

$pageTitle = 'Bill Pay';
$activeNav = 'bills';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-head">
  <div><h1>Bill Payment</h1><div class="sub">Pay utilities, mobile recharge, and subscriptions.</div></div>
  <a href="<?= BASE_URL ?>/modules/bills/history.php" class="btn btn-secondary">View History</a>
</div>

<?php if ($error): ?><div class="badge badge-danger" style="display:block;padding:12px 14px;margin-bottom:18px;">⚠️ <?= clean($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="badge badge-success" style="display:block;padding:12px 14px;margin-bottom:18px;">✅ <?= clean($success) ?></div><?php endif; ?>

<div class="grid grid-main">
  <div class="card">
    <form method="POST">
      <?= csrf_field() ?>
      <div class="field">
        <label>Category</label>
        <select class="input" id="categorySelect">
          <?php foreach ($categories as $c): ?><option value="<?= $c['category_id'] ?>"><?= clean($c['category_name']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Provider</label>
        <select class="input" name="provider_id" id="providerSelect" required>
          <?php foreach ($providers as $p): ?>
            <option value="<?= $p['provider_id'] ?>" data-cat="<?= $p['category_id'] ?>"><?= clean($p['provider_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field"><label>Bill / Meter / Mobile Account Number</label><input class="input" name="account_number" required></div>
      <div class="field"><label>Amount</label>
        <div class="input-group"><span class="input-prefix"><?= APP_CURRENCY ?></span><input class="input" type="number" step="0.01" name="amount" required></div>
      </div>
      <div class="field">
        <label>Pay From</label>
        <select class="input" name="from_account" required>
          <?php foreach ($accounts as $a): ?><option value="<?= $a['AccountNumber'] ?>"><?= mask_account($a['AccountNumber']) ?> (<?= currency($a['AvailableBalance']) ?>)</option><?php endforeach; ?>
        </select>
      </div>
      <label class="flex items-center gap-8 text-sm mt-8" style="margin-bottom:16px;"><input type="checkbox" name="is_recurring"> Set as recurring monthly auto-pay</label>
      <button type="submit" class="btn btn-primary btn-block">Pay Bill</button>
    </form>
  </div>
  <div class="card">
    <div class="card-head"><span class="card-title">Categories</span></div>
    <div class="grid grid-2" style="gap:12px;">
      <?php foreach ($categories as $c): ?>
        <div class="card" style="padding:14px;text-align:center;background:var(--surface-2);">
          <div class="text-sm" style="font-weight:600;"><?= clean($c['category_name']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<script>
const providerOpts = Array.from(document.getElementById('providerSelect').options);
function filterProviders() {
  const cat = document.getElementById('categorySelect').value;
  const select = document.getElementById('providerSelect');
  select.innerHTML = '';
  providerOpts.filter(o => o.dataset.cat === cat).forEach(o => select.appendChild(o.cloneNode(true)));
}
document.getElementById('categorySelect').addEventListener('change', filterProviders);
filterProviders();
</script>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
