<?php
require_once 'config/db.php';
requireAuth('client');

$userId = $_SESSION['user_id'];

// Check account status
$statusMsg = getAccountStatusMessage($pdo, $userId);

// Get full account info
$account = getUserAccount($pdo, $userId);
if (!$account) { setToast('Account not found.', 'danger'); redirect('logout.php'); }

$balance = (float)($account['AvailableBalance'] ?? 0);
$tier    = getCardTier($balance);

// Get card
$cardStmt = $pdo->prepare("SELECT * FROM CARDS WHERE CustomerID = ? AND IsActive = 1 LIMIT 1");
$cardStmt->execute([$userId]);
$card = $cardStmt->fetch();

// Get unread notifications
$unreadCount = getUnreadNotifications($pdo, $userId);

// Mark notification read (AJAX)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'notif_count') {
    header('Content-Type: application/json');
    echo json_encode(['count' => $unreadCount]);
    exit;
}

// Get recent transactions (last 10)
$txnStmt = $pdo->prepare(
    "SELECT t.*, tt.TypeName
     FROM TRANSACTION t
     JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
     WHERE t.FromCustomerID = ? OR t.ToCustomerID = ?
     ORDER BY t.TransactionDate DESC
     LIMIT 10"
);
$txnStmt->execute([$userId, $userId]);
$transactions = $txnStmt->fetchAll();

// Stats
$totalSent     = (float)$pdo->query("SELECT COALESCE(SUM(TransactionAmount),0) FROM TRANSACTION WHERE FromCustomerID=$userId AND TransactionStatus='Completed'")->fetchColumn();
$totalReceived = (float)$pdo->query("SELECT COALESCE(SUM(TransactionAmount),0) FROM TRANSACTION WHERE ToCustomerID=$userId AND TransactionStatus='Completed'")->fetchColumn();
$txnCount      = (int)$pdo->query("SELECT COUNT(*) FROM TRANSACTION WHERE FromCustomerID=$userId OR ToCustomerID=$userId")->fetchColumn();

// KYC status
$kycStmt = $pdo->prepare("SELECT * FROM KYC_VERIFICATIONS WHERE customer_id = ? ORDER BY submitted_at DESC LIMIT 1");
$kycStmt->execute([$userId]);
$kyc = $kycStmt->fetch();

// Nominee
$nomineeStmt = $pdo->prepare("SELECT * FROM NOMINEE WHERE CustomerID = ? LIMIT 1");
$nomineeStmt->execute([$userId]);
$nominee = $nomineeStmt->fetch();

// Notifications (latest 5)
$notifStmt = $pdo->prepare("SELECT * FROM NOTIFICATIONS WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5");
$notifStmt->execute([$userId]);
$notifications = $notifStmt->fetchAll();

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
include 'includes/header.php';
?>

<div class="page-content">

<?php if ($statusMsg): ?>
<div class="alert alert-warning" role="alert" style="margin-bottom:20px;">
    <i class='bx bx-error-circle'></i>
    <span><?= e($statusMsg) ?></span>
</div>
<?php endif; ?>

<!-- Page header -->
<div class="page-header">
    <div>
        <h1>Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', $_SESSION['username'])[0]) ?>.</h1>
        <p>Here's your financial overview for <?= date('l, d F Y') ?>.</p>
    </div>
    <a href="deposit.php" class="btn btn-primary" style="flex-shrink:0;">
        <i class='bx bx-plus'></i> Add money
    </a>
</div>

<!-- ── Top row: Balance card + Quick stats ───────────────────── -->
<div style="display:grid;grid-template-columns:340px 1fr;gap:20px;margin-bottom:20px;align-items:start;">

    <!-- Balance card -->
    <div class="bank-card reveal"
         style="background:linear-gradient(145deg,<?= e($tier['color']) ?> 0%,<?= e(adjustBrightness($tier['color'] ?? '#185FA5', 20)) ?> 100%);">
        <div>
            <div class="bank-card-chip"></div>
            <div class="bank-card-number">
                <?= $card ? maskCardNumber($card['CardNumber']) : '**** **** **** ****' ?>
            </div>
        </div>
        <div class="bank-card-bottom">
            <div>
                <div class="bank-card-holder"><?= e($account['FirstName'] . ' ' . $account['LastName']) ?></div>
                <div class="bank-card-expiry">VALID <?= $card ? date('m/y', strtotime($card['ExpiryDate'])) : '—' ?></div>
            </div>
            <div style="text-align:right;">
                <div class="bank-card-tier"><?= e($tier['name']) ?></div>
                <div class="bank-card-network">VISA</div>
            </div>
        </div>
    </div>

    <!-- Stats grid -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
        <div class="stat-card reveal">
            <div class="stat-card-icon" style="background:rgba(22,163,74,0.08);">
                <i class='bx bx-wallet' style="font-size:20px;color:#16A34A;"></i>
            </div>
            <div class="stat-card-label">Available Balance</div>
            <div class="stat-card-value" style="color:#16A34A;"><?= formatBDT($balance) ?></div>
            <div class="stat-card-sub"><?= e($account['AccountType'] ?? 'Savings') ?> · <?= e($account['AccountStatus']) ?></div>
        </div>
        <div class="stat-card reveal">
            <div class="stat-card-icon" style="background:rgba(59,130,246,0.08);">
                <i class='bx bx-trending-up' style="font-size:20px;color:#3B82F6;"></i>
            </div>
            <div class="stat-card-label">Total Received</div>
            <div class="stat-card-value"><?= formatBDT($totalReceived) ?></div>
            <div class="stat-card-sub">All-time credits</div>
        </div>
        <div class="stat-card reveal">
            <div class="stat-card-icon" style="background:rgba(239,68,68,0.08);">
                <i class='bx bx-trending-down' style="font-size:20px;color:#EF4444;"></i>
            </div>
            <div class="stat-card-label">Total Sent</div>
            <div class="stat-card-value"><?= formatBDT($totalSent) ?></div>
            <div class="stat-card-sub">All-time debits</div>
        </div>
        <div class="stat-card reveal">
            <div class="stat-card-icon" style="background:rgba(245,158,11,0.08);">
                <i class='bx bx-transfer' style="font-size:20px;color:#F59E0B;"></i>
            </div>
            <div class="stat-card-label">Transactions</div>
            <div class="stat-card-value"><?= $txnCount ?></div>
            <div class="stat-card-sub">Total count</div>
        </div>
    </div>
</div>

<!-- ── Account info strip ──────────────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:20px;" class="reveal">
    <div style="background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:12px;padding:16px;">
        <div style="font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px;">Account Number</div>
        <div style="font-size:15px;font-weight:600;font-family:monospace;color:var(--text-primary);"><?= e($account['AccountNumber']) ?></div>
    </div>
    <div style="background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:12px;padding:16px;">
        <div style="font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px;">Branch</div>
        <div style="font-size:15px;font-weight:600;color:var(--text-primary);"><?= e($account['BranchName'] ?? '—') ?></div>
        <div style="font-size:12px;color:var(--text-muted);"><?= e($account['IFSCCode'] ?? '') ?></div>
    </div>
    <div style="background:var(--bg-card);border:1px solid var(--border-subtle);border-radius:12px;padding:16px;">
        <div style="font-size:11px;font-weight:600;letter-spacing:.07em;text-transform:uppercase;color:var(--text-muted);margin-bottom:8px;">KYC Status</div>
        <?php if ($kyc): ?>
        <span class="badge badge-<?= $kyc['status']==='verified' ? 'success' : ($kyc['status']==='rejected' ? 'danger' : 'warning') ?>">
            <i class='bx bx-<?= $kyc['status']==='verified' ? 'check-shield' : 'shield-x' ?>'></i>
            <?= ucfirst($kyc['status']) ?>
        </span>
        <?php else: ?>
        <span class="badge badge-neutral">Not submitted</span>
        <?php endif; ?>
    </div>
</div>

<!-- ── Quick Actions ────────────────────────────────────────── -->
<div class="card reveal" style="margin-bottom:20px;">
    <div class="card-header">
        <h4>Quick Actions</h4>
    </div>
    <div class="card-body">
        <div class="quick-actions">
            <a href="deposit.php" class="quick-action">
                <div class="qa-icon" style="background:rgba(22,163,74,0.1);color:#16A34A;">
                    <i class='bx bx-log-in-circle'></i>
                </div>
                <span class="qa-label">Deposit</span>
            </a>
            <a href="withdraw.php" class="quick-action">
                <div class="qa-icon" style="background:rgba(239,68,68,0.1);color:#EF4444;">
                    <i class='bx bx-log-out-circle'></i>
                </div>
                <span class="qa-label">Withdraw</span>
            </a>
            <a href="transfer.php" class="quick-action">
                <div class="qa-icon" style="background:rgba(59,130,246,0.1);color:#3B82F6;">
                    <i class='bx bx-transfer'></i>
                </div>
                <span class="qa-label">Transfer</span>
            </a>
            <a href="cards.php" class="quick-action">
                <div class="qa-icon" style="background:rgba(245,158,11,0.1);color:#F59E0B;">
                    <i class='bx bx-credit-card'></i>
                </div>
                <span class="qa-label">Cards</span>
            </a>
            <a href="notifications.php" class="quick-action">
                <div class="qa-icon" style="background:var(--bg-muted);color:var(--text-secondary);">
                    <i class='bx bx-bell'></i>
                </div>
                <span class="qa-label">Alerts</span>
            </a>
            <a href="feedback.php" class="quick-action">
                <div class="qa-icon" style="background:var(--bg-muted);color:var(--text-secondary);">
                    <i class='bx bx-support'></i>
                </div>
                <span class="qa-label">Support</span>
            </a>
        </div>
    </div>
</div>

<!-- ── Transactions + Notifications ─────────────────────────── -->
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">

    <!-- Recent Transactions -->
    <div class="card reveal">
        <div class="card-header">
            <h4>Recent Transactions</h4>
            <a href="transactions.php" class="btn btn-ghost btn-sm">View all →</a>
        </div>
        <div class="card-body" style="padding:0 24px;">
            <?php if (empty($transactions)): ?>
            <div class="empty-state" style="padding:40px 0;">
                <i class='bx bx-transfer' style="font-size:40px;color:var(--text-muted);"></i>
                <h3>No transactions yet</h3>
                <p>Make your first deposit to get started.</p>
            </div>
            <?php else: ?>
            <?php foreach ($transactions as $txn):
                $isCredit = $txn['ToCustomerID'] == $userId;
                $type     = strtolower($txn['TypeName'] ?? 'transfer');
                $iconClass = $isCredit ? 'credit' : 'debit';
                if (strpos($type, 'transfer') !== false) $iconClass = 'transfer';
            ?>
            <div class="txn-item">
                <div class="txn-icon <?= $iconClass ?>">
                    <?php if ($iconClass === 'credit'): ?>
                    <i class='bx bx-log-in-circle'></i>
                    <?php elseif ($iconClass === 'debit'): ?>
                    <i class='bx bx-log-out-circle'></i>
                    <?php else: ?>
                    <i class='bx bx-transfer'></i>
                    <?php endif; ?>
                </div>
                <div class="txn-info">
                    <div class="txn-description"><?= e($txn['Description'] ?: $txn['TypeName']) ?></div>
                    <div class="txn-date"><?= formatDate($txn['TransactionDate'], 'd M Y, g:i A') ?></div>
                </div>
                <div style="text-align:right;">
                    <div class="txn-amount <?= $isCredit ? 'credit' : 'debit' ?>">
                        <?= $isCredit ? '+' : '-' ?><?= formatBDT($txn['TransactionAmount']) ?>
                    </div>
                    <div class="txn-ref"><?= e($txn['ReferenceNumber'] ?? '') ?></div>
                </div>
            </div>
            <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sidebar: Notifications + Account Info -->
    <div style="display:flex;flex-direction:column;gap:16px;">

        <!-- Notifications -->
        <div class="card reveal">
            <div class="card-header">
                <h4>Notifications</h4>
                <?php if ($unreadCount): ?>
                <span class="badge badge-danger"><?= $unreadCount ?> new</span>
                <?php endif; ?>
            </div>
            <div class="card-body" style="padding:0;">
                <?php if (empty($notifications)): ?>
                <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:13px;">No notifications</div>
                <?php else: ?>
                <?php foreach ($notifications as $n):
                    $ntype = $n['type'] ?? 'info';
                    $colors = ['success'=>'#16A34A','warning'=>'#F59E0B','danger'=>'#EF4444','info'=>'#3B82F6'];
                    $color  = $colors[$ntype] ?? 'var(--text-muted)';
                ?>
                <div style="display:flex;gap:12px;padding:12px 16px;border-bottom:1px solid var(--border-subtle);<?= !$n['is_read'] ? 'background:var(--bg-muted);' : '' ?>">
                    <div style="width:6px;height:6px;border-radius:50%;background:<?= $color ?>;flex-shrink:0;margin-top:6px;"></div>
                    <div style="flex:1;min-width:0;">
                        <div style="font-size:13px;font-weight:500;color:var(--text-primary);"><?= e($n['title']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= timeAgo($n['created_at']) ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
                <div style="padding:10px 16px;">
                    <a href="notifications.php" style="font-size:13px;color:var(--text-primary);text-decoration:none;font-weight:500;">View all notifications →</a>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Account info -->
        <div class="card reveal">
            <div class="card-header"><h4>Account Info</h4></div>
            <div class="card-body" style="padding:16px;">
                <?php $rows = [
                    ['Product', $account['ProductName'] ?? '—'],
                    ['Type',    $account['AccountType'] ?? '—'],
                    ['Min Balance', formatBDT($account['MinBalance'] ?? 0)],
                    ['Interest Rate', ($account['InterestRate'] ?? 0) . '% p.a.'],
                    ['Opened', formatDate($account['OpeningDate'] ?? '')],
                    ['Status', ucfirst($account['AccountStatus'])],
                ]; foreach ($rows as [$k,$v]): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--border-subtle);">
                    <span style="font-size:12px;color:var(--text-muted);"><?= e($k) ?></span>
                    <span style="font-size:12px;font-weight:500;color:var(--text-primary);"><?= e($v) ?></span>
                </div>
                <?php endforeach; ?>
                <?php if ($nominee): ?>
                <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;">
                    <span style="font-size:12px;color:var(--text-muted);">Nominee</span>
                    <span style="font-size:12px;font-weight:500;color:var(--text-primary);"><?= e($nominee['NomineeName']) ?></span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

</div><!-- end .page-content -->

<?php
function adjustBrightness(string $hex, int $steps): string {
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
    $r = max(0, min(255, hexdec(substr($hex,0,2)) + $steps));
    $g = max(0, min(255, hexdec(substr($hex,2,2)) + $steps));
    $b = max(0, min(255, hexdec(substr($hex,4,2)) + $steps));
    return '#' . sprintf('%02x%02x%02x', $r, $g, $b);
}
include 'includes/footer.php';
?>
