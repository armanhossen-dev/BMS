<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/language.php';
require_once __DIR__ . '/../../includes/functions.php';
require_customer();

$customerId = $_SESSION['customer_id'];
$stmt = $pdo->prepare("SELECT bp.*, pr.provider_name, bc.category_name FROM BILL_PAYMENTS bp
                        JOIN BILL_PROVIDERS pr ON bp.provider_id = pr.provider_id
                        JOIN BILL_CATEGORIES bc ON pr.category_id = bc.category_id
                        WHERE bp.customer_id = ? ORDER BY bp.payment_date DESC");
$stmt->execute([$customerId]);
$bills = $stmt->fetchAll();

$pageTitle = 'Bill History';
$activeNav = 'bills';
require __DIR__ . '/../../includes/header.php';
?>
<div class="page-head">
  <div><h1>Bill Payment History</h1><div class="sub">All your past utility and subscription payments.</div></div>
  <a href="<?= BASE_URL ?>/modules/bills/pay.php" class="btn btn-primary">+ Pay a Bill</a>
</div>

<div class="card">
  <?php if (empty($bills)): ?><div class="empty-state">No bill payments yet.</div>
  <?php else: ?>
  <div class="table-wrap"><table class="data-table">
    <thead><tr><th>Provider</th><th>Category</th><th>Account</th><th>Amount</th><th>Date</th><th>Status</th></tr></thead>
    <tbody>
    <?php foreach ($bills as $b): ?>
      <tr>
        <td><?= clean($b['provider_name']) ?></td>
        <td><?= clean($b['category_name']) ?></td>
        <td class="mono"><?= clean($b['account_number']) ?></td>
        <td class="mono"><?= currency($b['amount']) ?></td>
        <td><?= date('M j, Y', strtotime($b['payment_date'])) ?></td>
        <td><span class="badge <?= $b['status']==='Completed'?'badge-success':'badge-warning' ?>"><?= clean($b['status']) ?></span></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../../includes/footer.php'; ?>
