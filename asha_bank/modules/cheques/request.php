<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/language.php';
require_once __DIR__ . '/../../includes/functions.php';
require_customer();

$customerId = $_SESSION['customer_id'];
$accounts = get_accounts($customerId);
$success = ''; $error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $accountNum = $_POST['account_number'];
    $leaves = (int)$_POST['leaves'];
    $address = trim($_POST['delivery_address']);

    $owns = false;
    foreach ($accounts as $a) if ($a['AccountNumber'] == $accountNum) $owns = true;

    if (!$owns) { $error = 'Invalid account.'; }
    else {
        $pdo->prepare("INSERT INTO CHEQUE_BOOK_REQUESTS (customer_id,account_number,number_of_leaves,delivery_address,status) VALUES (?,?,?,?, 'Pending')")
            ->execute([$customerId, $accountNum, $leaves, $address]);
        notify($customerId, 'Cheque Book Requested', "Your request for a $leaves-leaf cheque book has been submitted.", 'info');
        $success = 'Cheque book request submitted successfully.';
    }
}

$stmt = $pdo->prepare("SELECT * FROM CHEQUE_BOOK_REQUESTS WHERE customer_id = ? ORDER BY request_date DESC");
$stmt->execute([$customerId]);
$requests = $stmt->fetchAll();

$stmt = $pdo->prepare("SELECT ch.* FROM CHEQUES ch JOIN ACCOUNT a ON ch.account_number = a.AccountNumber WHERE a.CustomerID = ? ORDER BY ch.issue_date DESC");
$stmt->execute([$customerId]);
$cheques = $stmt->fetchAll();

$pageTitle = 'Cheques';
$activeNav = 'cheques';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-head">
  <div><h1>Cheque Book</h1><div class="sub">Request a new cheque book or track issued cheques.</div></div>
</div>

<?php if ($error): ?><div class="badge badge-danger" style="display:block;padding:12px 14px;margin-bottom:18px;">⚠️ <?= clean($error) ?></div><?php endif; ?>
<?php if ($success): ?><div class="badge badge-success" style="display:block;padding:12px 14px;margin-bottom:18px;">✅ <?= clean($success) ?></div><?php endif; ?>

<div class="grid grid-main">
  <div class="card">
    <div class="card-head"><span class="card-title">Request Cheque Book</span></div>
    <form method="POST">
      <?= csrf_field() ?>
      <div class="field">
        <label>Account</label>
        <select class="input" name="account_number" required>
          <?php foreach ($accounts as $a): ?><option value="<?= $a['AccountNumber'] ?>"><?= mask_account($a['AccountNumber']) ?></option><?php endforeach; ?>
        </select>
      </div>
      <div class="field">
        <label>Number of Leaves</label>
        <select class="input" name="leaves"><option value="25">25 Leaves</option><option value="50" selected>50 Leaves</option><option value="100">100 Leaves</option></select>
      </div>
      <div class="field"><label>Delivery Address</label><textarea class="input" name="delivery_address" required></textarea></div>
      <button type="submit" class="btn btn-primary btn-block">Request Cheque Book</button>
    </form>
  </div>

  <div class="card">
    <div class="card-head"><span class="card-title">Request History</span></div>
    <?php if (empty($requests)): ?><div class="empty-state">No requests yet.</div>
    <?php else: foreach ($requests as $r): ?>
      <div class="flex justify-between items-center" style="padding:12px 0;border-bottom:1px solid var(--border-soft);">
        <div>
          <div class="text-sm" style="font-weight:600;"><?= $r['number_of_leaves'] ?> Leaves</div>
          <div class="text-dim text-xs"><?= mask_account($r['account_number']) ?> · <?= time_ago($r['request_date']) ?></div>
        </div>
        <span class="badge <?= $r['status']==='Delivered'?'badge-success':($r['status']==='Cancelled'?'badge-danger':'badge-warning') ?>"><?= clean($r['status']) ?></span>
      </div>
    <?php endforeach; endif; ?>
  </div>
</div>

<div class="card mt-16">
  <div class="card-head"><span class="card-title">Issued Cheques</span></div>
  <?php if (empty($cheques)): ?><div class="empty-state">No cheques issued yet.</div>
  <?php else: ?>
  <div class="table-wrap"><table class="data-table">
    <thead><tr><th>Cheque No.</th><th>Payee</th><th>Amount</th><th>Issue Date</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($cheques as $c): ?>
      <tr>
        <td class="mono"><?= clean($c['cheque_number']) ?></td>
        <td><?= clean($c['payee_name'] ?: '—') ?></td>
        <td class="mono"><?= $c['amount'] ? currency($c['amount']) : '—' ?></td>
        <td><?= date('M j, Y', strtotime($c['issue_date'])) ?></td>
        <td><span class="badge <?= $c['status']==='Cleared'?'badge-success':($c['status']==='Bounced'?'badge-danger':'badge-warning') ?>"><?= clean($c['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
