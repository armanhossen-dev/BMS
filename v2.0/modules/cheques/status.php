<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/language.php';
require_once __DIR__ . '/../../includes/functions.php';
require_customer();

$customerId = $_SESSION['customer_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['stop_payment'])) {
    verify_csrf();
    $chequeId = (int)$_POST['cheque_id'];
    $reason = trim($_POST['reason'] ?? '');
    $stmt = $pdo->prepare("SELECT ch.* FROM CHEQUES ch JOIN ACCOUNT a ON ch.account_number=a.AccountNumber WHERE ch.cheque_id=? AND a.CustomerID=?");
    $stmt->execute([$chequeId, $customerId]);
    if ($ch = $stmt->fetch()) {
        $pdo->prepare("UPDATE CHEQUES SET status='Stopped', stop_payment_reason=?, stop_payment_date=NOW() WHERE cheque_id=?")
            ->execute([$reason, $chequeId]);
        flash('page', 'Stop payment placed on cheque #' . $ch['cheque_number'] . '.', 'success');
    }
    header('Location: ' . BASE_URL . '/modules/cheques/status.php'); exit;
}

$stmt = $pdo->prepare("SELECT ch.* FROM CHEQUES ch JOIN ACCOUNT a ON ch.account_number = a.AccountNumber WHERE a.CustomerID = ? AND ch.status NOT IN ('Cleared','Cancelled','Stopped') ORDER BY ch.issue_date DESC");
$stmt->execute([$customerId]);
$activeCheques = $stmt->fetchAll();

$pageTitle = 'Cheque Status';
$activeNav = 'cheques';
require __DIR__ . '/../../includes/header.php';
$f = flash('page');
?>
<div class="page-head"><div><h1>Cheque Status</h1><div class="sub">Track clearance or stop payment on issued cheques.</div></div></div>
<?php if ($f): ?><div class="badge badge-<?= $f['type'] ?>" style="display:block;padding:12px 14px;margin-bottom:18px;"><?= clean($f['msg']) ?></div><?php endif; ?>

<div class="card">
  <?php if (empty($activeCheques)): ?><div class="empty-state">No active cheques to track.</div>
  <?php else: foreach ($activeCheques as $c): ?>
    <div class="flex justify-between items-center" style="padding:14px 0;border-bottom:1px solid var(--border-soft);">
      <div>
        <div class="text-sm mono" style="font-weight:600;">#<?= clean($c['cheque_number']) ?></div>
        <div class="text-dim text-xs"><?= clean($c['payee_name'] ?: 'Unassigned') ?> · <?= clean($c['status']) ?></div>
      </div>
      <button class="btn btn-sm btn-danger" data-modal-open="stop-<?= $c['cheque_id'] ?>">Stop Payment</button>
    </div>
    <div class="modal-overlay" id="stop-<?= $c['cheque_id'] ?>">
      <div class="modal">
        <div class="modal-head"><h3 style="font-size:16px;">Stop Payment</h3><span class="modal-close" data-modal-close>✕</span></div>
        <form method="POST">
          <?= csrf_field() ?><input type="hidden" name="cheque_id" value="<?= $c['cheque_id'] ?>">
          <div class="field"><label>Reason</label><textarea class="input" name="reason" required></textarea></div>
          <button type="submit" name="stop_payment" class="btn btn-danger btn-block" data-confirm="Are you sure you want to stop payment on this cheque?">Confirm Stop Payment</button>
        </form>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
