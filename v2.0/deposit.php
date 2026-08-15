<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/language.php';
require_once __DIR__ . '/includes/functions.php';
require_customer();

$customerId = $_SESSION['customer_id'];
$accounts = get_accounts($customerId);
$error = ''; $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accountNum = $_POST['account'] ?? '';
    $amount = (float)($_POST['amount'] ?? 0);
    $method = $_POST['method'] ?? 'branch';
    $refInput = trim($_POST['method_ref'] ?? '');

    $owns = false;
    foreach ($accounts as $a) if ($a['AccountNumber'] == $accountNum) $owns = true;

    if (!$owns) { $error = 'Invalid account.'; }
    elseif ($amount <= 0) { $error = 'Enter a valid amount.'; }
    else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance + ?, LastTransactionDate = NOW(), AccountStatus='Active' WHERE AccountNumber = ?")
                ->execute([$amount, $accountNum]);
            $ref = gen_reference('DEP');
            $desc = "Deposit via " . ucfirst($method) . ($refInput ? " ($refInput)" : '');
            $pdo->prepare("INSERT INTO TRANSACTION (TransactionTypeID,TransactionAmount,ToAccountNumber,ToCustomerID,Description,TransactionStatus,ReferenceNumber)
                            VALUES (1,?,?,?,?, 'Completed', ?)")
                ->execute([$amount, $accountNum, $customerId, $desc, $ref]);
            notify($customerId, 'Deposit Successful', currency($amount) . " was deposited via " . ucfirst($method) . ".", 'success');
            $pdo->commit();
            $success = "Deposit of " . currency($amount) . " successful. Ref: $ref";
            $accounts = get_accounts($customerId);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Deposit failed. Please try again.';
        }
    }
}

$pageTitle = 'Deposit';
$activeNav = 'deposit';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div><h1>Deposit Funds</h1><div class="sub">Add money to your account via mobile banking or branch.</div></div>
</div>

<div class="grid grid-main">
  <div class="card">
    <?php if ($error): ?><div class="badge badge-danger" style="display:block;padding:12px 14px;margin-bottom:18px;">⚠️ <?= clean($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="badge badge-success" style="display:block;padding:12px 14px;margin-bottom:18px;">✅ <?= clean($success) ?></div><?php endif; ?>

    <form method="POST">
      <?= csrf_field() ?>
      <div class="field">
        <label>To Account</label>
        <select class="input" name="account" required>
          <?php foreach ($accounts as $a): ?>
            <option value="<?= $a['AccountNumber'] ?>"><?= clean($a['ProductName']) ?> — <?= mask_account($a['AccountNumber']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Amount</label>
        <div class="input-group">
          <span class="input-prefix"><?= APP_CURRENCY ?></span>
          <input class="input" type="number" step="0.01" min="1" name="amount" placeholder="0.00" required>
        </div>
      </div>
      <div class="field">
        <label>Deposit Method</label>
        <select class="input" name="method" data-method-select>
          <option value="bkash">bKash</option>
          <option value="nagad">Nagad</option>
          <option value="rocket">Rocket</option>
          <option value="upay">Upay</option>
          <option value="branch">Branch (Cash)</option>
        </select>
      </div>

      <div data-method-group="bkash" class="field"><label>bKash Transaction ID</label><input class="input" name="method_ref" placeholder="e.g. 8N7K2X9P"></div>
      <div data-method-group="nagad" class="field" style="display:none;"><label>Nagad Transaction ID</label><input class="input" name="method_ref"></div>
      <div data-method-group="rocket" class="field" style="display:none;"><label>Rocket Transaction ID</label><input class="input" name="method_ref"></div>
      <div data-method-group="upay" class="field" style="display:none;"><label>Upay Transaction ID</label><input class="input" name="method_ref"></div>
      <div data-method-group="branch" class="field" style="display:none;"><label>Teller Reference (optional)</label><input class="input" name="method_ref"></div>

      <button type="submit" class="btn btn-primary btn-block">Deposit Now</button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><span class="card-title">Supported Methods</span></div>
    <div class="grid grid-2" style="gap:12px;">
      <?php foreach (['bKash','Nagad','Rocket','Upay'] as $m): ?>
        <div class="card" style="padding:14px;text-align:center;background:var(--surface-2);">
          <div style="font-weight:700;font-family:var(--font-display);"><?= $m ?></div>
        </div>
      <?php endforeach; ?>
    </div>
    <p class="text-dim text-xs mt-16">Deposits reflect instantly in this demo environment. In production, mobile banking deposits are confirmed via provider webhook.</p>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
