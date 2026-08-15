<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/language.php';
require_once __DIR__ . '/includes/functions.php';
require_customer();

$customerId = $_SESSION['customer_id'];
$customer = get_customer($customerId);
$accounts = get_accounts($customerId);
$primary = $accounts[0] ?? null;

$totalBalance = array_sum(array_column($accounts, 'AvailableBalance'));
$tier = tier_for_balance($totalBalance);
$next = tier_next($totalBalance);
$stars = tier_stars($tier);

// Recent transactions (last 6)
$stmt = $pdo->prepare("SELECT t.*, tt.TypeName FROM TRANSACTION t
                        JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
                        WHERE t.FromCustomerID = ? OR t.ToCustomerID = ?
                        ORDER BY t.TransactionDate DESC LIMIT 6");
$stmt->execute([$customerId, $customerId]);
$recentTxns = $stmt->fetchAll();

// This month stats
$stmt = $pdo->prepare("SELECT
    SUM(CASE WHEN ToCustomerID = ? THEN TransactionAmount ELSE 0 END) AS received,
    SUM(CASE WHEN FromCustomerID = ? THEN TransactionAmount ELSE 0 END) AS sent,
    COUNT(*) AS total
    FROM TRANSACTION WHERE (FromCustomerID = ? OR ToCustomerID = ?)
    AND MONTH(TransactionDate) = MONTH(CURDATE()) AND YEAR(TransactionDate) = YEAR(CURDATE())");
$stmt->execute([$customerId, $customerId, $customerId, $customerId]);
$monthStats = $stmt->fetch();

// Last 7 days transaction volume (for chart)
$stmt = $pdo->prepare("SELECT DATE(TransactionDate) d,
    SUM(CASE WHEN ToCustomerID = ? THEN TransactionAmount ELSE 0 END) credit,
    SUM(CASE WHEN FromCustomerID = ? THEN TransactionAmount ELSE 0 END) debit
    FROM TRANSACTION WHERE (FromCustomerID = ? OR ToCustomerID = ?) AND TransactionDate >= CURDATE() - INTERVAL 6 DAY
    GROUP BY DATE(TransactionDate) ORDER BY d");
$stmt->execute([$customerId, $customerId, $customerId, $customerId]);
$chartRaw = $stmt->fetchAll();
$chartMap = [];
foreach ($chartRaw as $r) $chartMap[$r['d']] = $r;
$chartLabels = []; $chartCredit = []; $chartDebit = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i day"));
    $chartLabels[] = date('D', strtotime($d));
    $chartCredit[] = (float)($chartMap[$d]['credit'] ?? 0);
    $chartDebit[]  = (float)($chartMap[$d]['debit'] ?? 0);
}

// KYC status
$stmt = $pdo->prepare("SELECT status FROM KYC_VERIFICATIONS WHERE customer_id = ? ORDER BY submitted_at DESC LIMIT 1");
$stmt->execute([$customerId]);
$kycStatus = $stmt->fetchColumn() ?: 'pending';

// Card
$stmt = $pdo->prepare("SELECT * FROM CARDS WHERE CustomerID = ? LIMIT 1");
$stmt->execute([$customerId]);
$card = $stmt->fetch();

$showKycPopup = !empty($_SESSION['show_kyc_popup']);
unset($_SESSION['show_kyc_popup']);

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
require __DIR__ . '/includes/header.php';
?>

<div class="page-head">
  <div>
    <h1><?= t('welcome_back') ?>, <?= clean($customer['FirstName']) ?>! 👋</h1>
    <div class="sub">Here's what's happening with your money today.</div>
  </div>
  <div class="flex gap-12">
    <?php if ($kycStatus !== 'verified'): ?>
      <span class="badge badge-warning">KYC <?= ucfirst($kycStatus) ?></span>
    <?php else: ?>
      <span class="badge badge-success">KYC Verified</span>
    <?php endif; ?>
    <a href="<?= BASE_URL ?>/transfer.php" class="btn btn-primary">
      <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
      <?= t('send_money') ?>
    </a>
  </div>
</div>

<div class="grid grid-4">
  <div class="card stat-card">
    <div class="icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
    <div class="stat-label"><?= t('available_balance') ?></div>
    <div class="stat-value mono"><?= currency($totalBalance) ?></div>
    <div class="stat-delta up">Across <?= count($accounts) ?> account<?= count($accounts)===1?'':'s' ?></div>
  </div>
  <div class="card stat-card">
    <div class="icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg></div>
    <div class="stat-label">Received this month</div>
    <div class="stat-value mono"><?= currency($monthStats['received'] ?? 0) ?></div>
    <div class="stat-delta up">↑ Credits</div>
  </div>
  <div class="card stat-card">
    <div class="icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg></div>
    <div class="stat-label">Sent this month</div>
    <div class="stat-value mono"><?= currency($monthStats['sent'] ?? 0) ?></div>
    <div class="stat-delta down">↓ Debits</div>
  </div>
  <div class="card stat-card">
    <div class="icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 2l2.9 6.26L22 9.27l-5 4.87L18.2 21 12 17.27 5.8 21 7 14.14l-5-4.87 7.1-1.01z"/></svg></div>
    <div class="stat-label">Your Tier</div>
    <div class="stat-value" style="color:var(--accent);"><?= clean($tier) ?></div>
    <div class="tier-stars mt-8">
      <?php for ($i=1;$i<=5;$i++): ?>
        <svg viewBox="0 0 24 24" class="<?= $i<=$stars?'filled':'empty' ?>" stroke="currentColor" stroke-width="1.5"><polygon points="12 2 15 9 22 9.5 17 14.5 18.5 22 12 18 5.5 22 7 14.5 2 9.5 9 9"/></svg>
      <?php endfor; ?>
    </div>
  </div>
</div>

<div class="grid grid-main mt-16">
  <div class="card">
    <div class="card-head">
      <span class="card-title">Transaction Volume</span>
      <span class="text-dim text-xs">Last 7 days</span>
    </div>
    <div class="chart-canvas-wrap">
      <canvas id="volumeChart" style="width:100%;height:100%;"></canvas>
    </div>
    <div class="flex gap-16 mt-16" style="justify-content:center;">
      <div class="flex items-center gap-8 text-xs text-dim"><span style="width:8px;height:8px;border-radius:50%;background:var(--accent);display:inline-block;"></span> Received</div>
      <div class="flex items-center gap-8 text-xs text-dim"><span style="width:8px;height:8px;border-radius:50%;background:var(--info);display:inline-block;"></span> Sent</div>
    </div>
  </div>

  <div class="card">
    <div class="card-head">
      <span class="card-title">Your Card</span>
      <a href="<?= BASE_URL ?>/cards.php" class="link">Manage</a>
    </div>
    <?php if ($card): ?>
    <div class="bank-card <?= tier_class($tier) ?>">
      <div class="card-top">
        <div class="card-chip"></div>
        <div class="card-brand">VISA</div>
      </div>
      <div class="card-number mono"><?= mask_card($card['CardNumber']) ?></div>
      <div class="card-bottom">
        <div class="card-holder"><?= clean($tier) ?> Card<strong><?= clean($customer['FirstName'] . ' ' . $customer['LastName']) ?></strong></div>
        <div class="text-xs mono" style="opacity:.85;">EXP <?= date('m/y', strtotime($card['ExpiryDate'])) ?></div>
      </div>
    </div>
    <?php endif; ?>

    <?php if ($next): ?>
    <div class="tier-progress">
      <div class="flex justify-between text-xs text-dim">
        <span>Next: <?= clean($next['name']) ?></span>
        <span><?= currency($next['need']) ?> to go</span>
      </div>
      <div class="tier-bar"><div class="tier-bar-fill" data-width="<?= min(100, ($totalBalance / $next['target']) * 100) ?>%"></div></div>
    </div>
    <?php else: ?>
      <div class="badge badge-warning mt-16">🏆 You've reached the top tier!</div>
    <?php endif; ?>
  </div>
</div>

<div class="grid grid-main mt-16">
  <div class="card">
    <div class="card-head">
      <span class="card-title"><?= t('recent_transactions') ?></span>
      <a href="#" class="link"><?= t('view_all') ?></a>
    </div>
    <?php if (empty($recentTxns)): ?>
      <div class="empty-state">No transactions yet. Make your first transfer!</div>
    <?php else: foreach ($recentTxns as $tx):
        $isCredit = $tx['ToCustomerID'] == $customerId;
    ?>
      <div class="txn-row">
        <div class="txn-icon <?= $isCredit?'credit':'debit' ?>">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <?php if ($isCredit): ?><polyline points="19 12 12 19 5 12"/><line x1="12" y1="5" x2="12" y2="19"/>
            <?php else: ?><polyline points="5 12 12 5 19 12"/><line x1="12" y1="19" x2="12" y2="5"/><?php endif; ?>
          </svg>
        </div>
        <div class="txn-info">
          <div class="txn-title"><?= clean($tx['TypeName']) ?></div>
          <div class="txn-sub"><?= clean($tx['Description'] ?: $tx['ReferenceNumber']) ?> · <?= time_ago($tx['TransactionDate']) ?></div>
        </div>
        <div class="txn-amount <?= $isCredit?'credit':'debit' ?>"><?= $isCredit?'+':'-' ?> <?= currency($tx['TransactionAmount']) ?></div>
      </div>
    <?php endforeach; endif; ?>
  </div>

  <div class="card">
    <div class="card-head"><span class="card-title">Your Accounts</span></div>
    <?php foreach ($accounts as $acc): ?>
      <div class="flex justify-between items-center" style="padding:12px 0;border-bottom:1px solid var(--border-soft);">
        <div>
          <div class="text-sm" style="font-weight:600;"><?= clean($acc['ProductName']) ?></div>
          <div class="text-xs text-dim mono"><?= mask_account($acc['AccountNumber']) ?></div>
        </div>
        <div class="text-right">
          <div class="mono" style="font-weight:600;"><?= currency($acc['AvailableBalance']) ?></div>
          <span class="badge <?= $acc['AccountStatus']==='Active'?'badge-success':'badge-warning' ?>" style="margin-top:4px;"><?= clean($acc['AccountStatus']) ?></span>
        </div>
      </div>
    <?php endforeach; ?>
    <a href="<?= BASE_URL ?>/deposit.php" class="btn btn-secondary btn-block mt-16">+ Add Funds</a>
  </div>
</div>

<script>
drawLineChart('volumeChart',
  <?= json_encode($chartLabels) ?>,
  [
    { data: <?= json_encode($chartCredit) ?>, color: '#d4a657' },
    { data: <?= json_encode($chartDebit) ?>, color: '#60a5fa' }
  ]
);
</script>

<?php if ($showKycPopup && $kycStatus !== 'verified'): ?>
<div class="modal-overlay open" id="kycModal">
  <div class="modal">
    <div class="modal-head">
      <h3 style="font-size:17px;">Complete your KYC</h3>
      <span class="modal-close" data-modal-close>✕</span>
    </div>
    <p class="text-muted text-sm">Verify your identity to unlock transfers, cards, and loans. It only takes a minute.</p>
    <div class="flex gap-12 mt-24">
      <button class="btn btn-secondary" style="flex:1;" data-modal-close>Later</button>
      <a href="<?= BASE_URL ?>/profile.php#kyc" class="btn btn-primary" style="flex:1;">Verify Now</a>
    </div>
  </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
