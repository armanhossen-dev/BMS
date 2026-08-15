<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/language.php';
require_once __DIR__ . '/includes/functions.php';
require_customer();

$customerId = $_SESSION['customer_id'];
$customer = get_customer($customerId);
$accounts = get_accounts($customerId);
$totalBalance = array_sum(array_column($accounts, 'AvailableBalance'));
$tier = tier_for_balance($totalBalance);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_card'])) {
    verify_csrf();
    $cardId = (int)$_POST['card_id'];
    $stmt = $pdo->prepare("SELECT * FROM CARDS WHERE CardID = ? AND CustomerID = ?");
    $stmt->execute([$cardId, $customerId]);
    if ($card = $stmt->fetch()) {
        $newStatus = $card['CardStatus'] === 'Active' ? 'Blocked' : 'Active';
        $pdo->prepare("UPDATE CARDS SET CardStatus = ? WHERE CardID = ?")->execute([$newStatus, $cardId]);
        notify($customerId, 'Card ' . $newStatus, "Your card ending in " . substr($card['CardNumber'], -4) . " is now $newStatus.", $newStatus==='Active'?'success':'warning');
        flash('page', "Card ending in " . substr($card['CardNumber'], -4) . " is now $newStatus.", 'success');
    }
    header('Location: ' . BASE_URL . '/cards.php');
    exit;
}

$stmt = $pdo->prepare("SELECT c.*, a.AvailableBalance FROM CARDS c LEFT JOIN ACCOUNT a ON c.AccountNumber = a.AccountNumber WHERE c.CustomerID = ?");
$stmt->execute([$customerId]);
$cards = $stmt->fetchAll();

$pageTitle = 'Cards';
$activeNav = 'cards';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div><h1>Your Cards</h1><div class="sub">Manage and secure your debit cards.</div></div>
</div>

<div class="grid grid-3">
  <?php foreach ($cards as $card):
      $cardTier = tier_for_balance($card['AvailableBalance'] ?? 0);
  ?>
  <div class="card">
    <div class="bank-card <?= tier_class($cardTier) ?>" data-card-modal="modal-<?= $card['CardID'] ?>">
      <div class="card-top">
        <div class="card-chip"></div>
        <div class="card-brand">VISA</div>
      </div>
      <div class="card-number mono"><?= mask_card($card['CardNumber']) ?></div>
      <div class="card-bottom">
        <div class="card-holder"><?= clean($cardTier) ?> · <?= clean($card['CardType']) ?><strong><?= clean($customer['FirstName'] . ' ' . $customer['LastName']) ?></strong></div>
        <div class="text-xs mono" style="opacity:.85;">EXP <?= date('m/y', strtotime($card['ExpiryDate'])) ?></div>
      </div>
    </div>
    <div class="flex justify-between items-center mt-16">
      <span class="badge <?= $card['CardStatus']==='Active'?'badge-success':'badge-danger' ?>"><?= clean($card['CardStatus']) ?></span>
      <form method="POST" onsubmit="return confirm('<?= $card['CardStatus']==='Active'?'Block':'Unblock' ?> this card?');">
        <?= csrf_field() ?>
        <input type="hidden" name="card_id" value="<?= $card['CardID'] ?>">
        <button type="submit" name="toggle_card" class="btn btn-sm <?= $card['CardStatus']==='Active'?'btn-danger':'btn-secondary' ?>">
          <?= $card['CardStatus']==='Active' ? 'Block Card' : 'Unblock' ?>
        </button>
      </form>
    </div>
    <div class="text-dim text-xs mt-16">Daily limit: <?= currency($card['DailyLimit']) ?></div>
  </div>

  <!-- Card detail modal -->
  <div class="modal-overlay" id="modal-<?= $card['CardID'] ?>">
    <div class="modal">
      <div class="modal-head"><h3 style="font-size:16px;">Card Details</h3><span class="modal-close" data-modal-close>✕</span></div>
      <div class="bank-card <?= tier_class($cardTier) ?>" style="margin-bottom:18px;">
        <div class="card-top"><div class="card-chip"></div><div class="card-brand">VISA</div></div>
        <div class="card-number mono"><?= chunk_split($card['CardNumber'], 4, ' ') ?></div>
        <div class="card-bottom">
          <div class="card-holder">CVV: <?= clean($card['CVV']) ?><strong><?= clean($customer['FirstName'] . ' ' . $customer['LastName']) ?></strong></div>
          <div class="text-xs mono" style="opacity:.85;">EXP <?= date('m/y', strtotime($card['ExpiryDate'])) ?></div>
        </div>
      </div>
      <div class="text-sm text-muted">Linked account: <span class="mono"><?= mask_account($card['AccountNumber']) ?></span></div>
    </div>
  </div>
  <?php endforeach; ?>
</div>

<?php if (empty($cards)): ?>
  <div class="card empty-state">You don't have any cards yet.</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
