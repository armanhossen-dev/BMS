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
    $fromAccount = $_POST['from_account'] ?? '';
    $toAccount   = trim($_POST['to_account'] ?? '');
    $amount      = (float)($_POST['amount'] ?? 0);
    $description = trim($_POST['description'] ?? 'Fund Transfer');

    // Ownership check
    $ownsFrom = false;
    foreach ($accounts as $a) if ($a['AccountNumber'] == $fromAccount) $ownsFrom = true;

    if (!$ownsFrom) {
        $error = 'Invalid source account.';
    } elseif ($toAccount === '' || !ctype_digit($toAccount)) {
        $error = 'Enter a valid destination account number.';
    } elseif ($fromAccount == $toAccount) {
        $error = 'You cannot transfer to the same account.';
    } elseif ($amount <= 0) {
        $error = 'Enter a valid amount.';
    } else {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("SELECT * FROM ACCOUNT WHERE AccountNumber = ? FOR UPDATE");
            $stmt->execute([$fromAccount]);
            $from = $stmt->fetch();

            $stmt = $pdo->prepare("SELECT * FROM ACCOUNT WHERE AccountNumber = ? FOR UPDATE");
            $stmt->execute([$toAccount]);
            $to = $stmt->fetch();

            if (!$to || $to['AccountStatus'] !== 'Active') {
                throw new Exception('Destination account not found or inactive.');
            } elseif ($from['AccountStatus'] !== 'Active') {
                throw new Exception('Your account is not active.');
            } elseif ($from['AvailableBalance'] < $amount) {
                throw new Exception('Insufficient balance.');
            }

            $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance - ?, LastTransactionDate = NOW() WHERE AccountNumber = ?")
                ->execute([$amount, $fromAccount]);
            $pdo->prepare("UPDATE ACCOUNT SET AvailableBalance = AvailableBalance + ?, LastTransactionDate = NOW() WHERE AccountNumber = ?")
                ->execute([$amount, $toAccount]);

            $ref = gen_reference('TXN');
            $pdo->prepare("INSERT INTO TRANSACTION (TransactionTypeID,TransactionAmount,FromAccountNumber,ToAccountNumber,FromCustomerID,ToCustomerID,Description,TransactionStatus,ReferenceNumber)
                            VALUES (3,?,?,?,?,?,?, 'Completed', ?)")
                ->execute([$amount, $fromAccount, $toAccount, $customerId, $to['CustomerID'], $description, $ref]);

            notify($customerId, 'Transfer Sent', "You sent " . currency($amount) . " to account ending " . substr($toAccount,-4) . ".", 'info');
            notify($to['CustomerID'], 'Money Received', "You received " . currency($amount) . " from a transfer.", 'success');
            if ($amount > 100000) {
                notify($customerId, 'Large Transaction Alert', "A large transfer of " . currency($amount) . " was processed.", 'warning');
            }

            $pdo->commit();
            $success = "Transfer of " . currency($amount) . " completed successfully. Ref: $ref";
            $accounts = get_accounts($customerId);
        } catch (Exception $e) {
            $pdo->rollBack();
            $error = $e->getMessage();
        }
    }
}

$pageTitle = 'Send Money';
$activeNav = 'transfer';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div><h1>Send Money</h1><div class="sub">Transfer funds to any Asha Bank account instantly.</div></div>
</div>

<div class="grid grid-main">
  <div class="card">
    <?php if ($error): ?><div class="badge badge-danger" style="display:block;padding:12px 14px;margin-bottom:18px;">⚠️ <?= clean($error) ?></div><?php endif; ?>
    <?php if ($success): ?><div class="badge badge-success" style="display:block;padding:12px 14px;margin-bottom:18px;">✅ <?= clean($success) ?></div><?php endif; ?>

    <form method="POST">
      <?= csrf_field() ?>
      <div class="field">
        <label>From Account</label>
        <select class="input" name="from_account" required>
          <?php foreach ($accounts as $a): ?>
            <option value="<?= $a['AccountNumber'] ?>"><?= clean($a['ProductName']) ?> — <?= mask_account($a['AccountNumber']) ?> (<?= currency($a['AvailableBalance']) ?>)</option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>To Account Number</label>
        <input class="input mono" type="text" name="to_account" placeholder="11-digit account number" required pattern="\d{6,20}">
      </div>
      <div class="field">
        <label>Amount</label>
        <div class="input-group">
          <span class="input-prefix"><?= APP_CURRENCY ?></span>
          <input class="input" type="number" step="0.01" min="1" name="amount" placeholder="0.00" required>
        </div>
      </div>
      <div class="field">
        <label>Note (optional)</label>
        <input class="input" type="text" name="description" placeholder="What's this for?" maxlength="100">
      </div>
      <button type="submit" class="btn btn-primary btn-block">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Send Money
      </button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><span class="card-title">Tips</span></div>
    <ul class="text-sm text-muted" style="padding-left:18px;line-height:2;">
      <li>Transfers between Asha Bank accounts are instant and free.</li>
      <li>Double-check the account number before confirming.</li>
      <li>Transfers above <?= currency(100000) ?> trigger a security alert.</li>
    </ul>
  </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
