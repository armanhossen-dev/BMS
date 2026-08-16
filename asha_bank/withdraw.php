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
    $method = $_POST['method'] ?? 'atm';

    $account = null;
    foreach ($accounts as $a) if ($a['AccountNumber'] == $accountNum) $account = $a;

    if (!$account) { $error = 'Invalid account.'; }
    elseif ($amount <= 0) { $error = 'Enter a valid amount.'; }
    elseif ($amount > $account['AvailableBalance']) { $error = 'Insufficient balance.'; }
    else {
        try {
            $pdo->beginTransaction();
            $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance - ?, LastTransactionDate = NOW() WHERE AccountNumber = ?")
                ->execute([$amount, $accountNum]);
            $ref = gen_reference('WDL');
            $pdo->prepare("INSERT INTO TRANSACTION (TransactionTypeID,TransactionAmount,FromAccountNumber,FromCustomerID,Description,TransactionStatus,ReferenceNumber)
                            VALUES (2,?,?,?,?, 'Completed', ?)")
                ->execute([$amount, $accountNum, $customerId, "Withdrawal via " . strtoupper($method), $ref]);
            notify($customerId, 'Withdrawal Successful', currency($amount) . " was withdrawn via " . strtoupper($method) . ".", 'info');
            $pdo->commit();
            $success = "Withdrawal of " . currency($amount) . " successful. Ref: $ref";
            $accounts = get_accounts($customerId);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = 'Withdrawal failed. Please try again.';
        }
    }
}

$pageTitle = 'Withdraw';
$activeNav = 'withdraw';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div><h1>Withdraw Funds</h1><div class="sub">Cash out via ATM, branch, or mobile banking.</div></div>
</div>

<div class="grid grid-main">
  <div class="card">
    <?php if ($error): ?><div class="badge badge-danger" style="display:block;padding:12px 14px;margin-bottom:18px;">⚠️ <?= clean($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="badge badge-success" style="display:block;padding:12px 14px;margin-bottom:18px;">✅ <?= clean($success) ?></div><?php endif; ?>

    <form method="POST">
      <?= csrf_field() ?>
      <div class="field">
        <label>From Account</label>
        <select class="input" name="account" required>
          <?php foreach ($accounts as $a): ?>
            <option value="<?= $a['AccountNumber'] ?>"><?= clean($a['ProductName']) ?> — <?= mask_account($a['AccountNumber']) ?> (<?= currency($a['AvailableBalance']) ?>)</option>
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
        <label>Withdrawal Method</label>
        <select class="input" name="method">
          <option value="atm">ATM</option>
          <option value="branch">Branch Counter</option>
          <option value="mobile">Mobile Banking</option>
        </select>
      </div>
      <button type="submit" class="btn btn-primary btn-block">Withdraw Now</button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><span class="card-title">Good to know</span></div>
    <ul class="text-sm text-muted" style="padding-left:18px;line-height:2;">
      <li>Withdrawals are deducted immediately from your available balance.</li>
      <li>Your account becomes Dormant automatically if balance reaches zero.</li>
      <li>Large withdrawals may require branch verification.</li>
    </ul>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
