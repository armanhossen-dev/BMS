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
$card = $pdo->prepare("SELECT * FROM CARDS WHERE CustomerID = ? AND IsActive = 1 LIMIT 1");
$card->execute([$userId]);
$card = $card->fetch();

// Get unread notifications
$unreadCount = getUnreadNotifications($pdo, $userId);

// Mark notification read (AJAX)
if (isset($_GET['ajax']) && $_GET['ajax'] === 'notif_count') {
    header('Content-Type: application/json');
    echo json_encode(['count' => $unreadCount]);
    exit;
}

// Get recent transactions (last 10)
$txns = $pdo->prepare(
    "SELECT t.*, tt.TypeName
     FROM TRANSACTION t
     JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
     WHERE t.FromCustomerID = ? OR t.ToCustomerID = ?
     ORDER BY t.TransactionDate DESC
     LIMIT 10"
);
$txns->execute([$userId, $userId]);
$transactions = $txns->fetchAll();

// Stats
$totalSent = (float)$pdo->prepare("SELECT COALESCE(SUM(TransactionAmount),0) FROM TRANSACTION WHERE FromCustomerID = ? AND TransactionStatus='Completed'")->execute([$userId]) ? $pdo->query("SELECT COALESCE(SUM(TransactionAmount),0) FROM TRANSACTION WHERE FromCustomerID=$userId AND TransactionStatus='Completed'")->fetchColumn() : 0;
$totalReceived = (float)$pdo->query("SELECT COALESCE(SUM(TransactionAmount),0) FROM TRANSACTION WHERE ToCustomerID=$userId AND TransactionStatus='Completed'")->fetchColumn();
$txnCount = (int)$pdo->query("SELECT COUNT(*) FROM TRANSACTION WHERE FromCustomerID=$userId OR ToCustomerID=$userId")->fetchColumn();

// KYC status
$kyc = $pdo->prepare("SELECT * FROM KYC_VERIFICATIONS WHERE customer_id = ? ORDER BY submitted_at DESC LIMIT 1");
$kyc->execute([$userId]);
$kyc = $kyc->fetch();

// Nominee
$nominee = $pdo->prepare("SELECT * FROM NOMINEE WHERE CustomerID = ? LIMIT 1");
$nominee->execute([$userId]);
$nominee = $nominee->fetch();

// Notifications (latest 5)
$notifs = $pdo->prepare("SELECT * FROM NOTIFICATIONS WHERE customer_id = ? ORDER BY created_at DESC LIMIT 5");
$notifs->execute([$userId]);
$notifications = $notifs->fetchAll();

$pageTitle  = 'Dashboard';
$activePage = 'dashboard';
include 'includes/header.php';
?>

<div class="page-content">

    <!-- Account status warning -->
    <?php if ($statusMsg): ?>
    <div class="alert alert-warning" role="alert" style="margin-bottom: 24px;">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
        <span><?= e($statusMsg) ?></span>
    </div>
    <?php endif; ?>

    <!-- Page header -->
    <div class="page-header">
        <h1>Good <?= date('H') < 12 ? 'morning' : (date('H') < 17 ? 'afternoon' : 'evening') ?>, <?= e(explode(' ', $_SESSION['username'])[0]) ?>.</h1>
        <p>Here's your financial overview for <?= date('l, d F Y') ?>.</p>
    </div>

    <!-- Top row: bank card + quick stats -->
    <div style="display:grid;grid-template-columns:340px 1fr;gap:24px;margin-bottom:24px;align-items:start;">

        <!-- Bank Card -->
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

        <!-- Stats -->
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="stat-card reveal">
                <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--green)" stroke-width="2"><path d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20z"/><line x1="12" y1="8" x2="12" y2="16"/><line x1="8" y1="12" x2="16" y2="12"/></svg>
                </div>
                <div class="stat-card-label">Available Balance</div>
                <div class="stat-card-value" style="font-size:22px;color:var(--green);"><?= formatBDT($balance) ?></div>
                <div class="stat-card-sub"><?= e($account['AccountType'] ?? 'Savings') ?> · <?= e($account['AccountStatus']) ?></div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--blue)" stroke-width="2"><polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/></svg>
                </div>
                <div class="stat-card-label">Total Received</div>
                <div class="stat-card-value" style="font-size:22px;"><?= formatBDT($totalReceived) ?></div>
                <div class="stat-card-sub">All-time credits</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--red)" stroke-width="2"><polyline points="23 18 13.5 8.5 8.5 13.5 1 6"/><polyline points="17 18 23 18 23 12"/></svg>
                </div>
                <div class="stat-card-label">Total Sent</div>
                <div class="stat-card-value" style="font-size:22px;"><?= formatBDT($totalSent) ?></div>
                <div class="stat-card-sub">All-time debits</div>
            </div>
            <div class="stat-card reveal">
                <div class="stat-card-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="var(--amber)" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                </div>
                <div class="stat-card-label">Transactions</div>
                <div class="stat-card-value" style="font-size:22px;"><?= $txnCount ?></div>
                <div class="stat-card-sub">Total count</div>
            </div>
        </div>
    </div>

    <!-- Account details row -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-bottom:24px;" class="reveal">
        <div style="background:var(--surface);border:1px solid var(--cream-3);border-radius:12px;padding:16px;">
            <div style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--ink-muted);margin-bottom:8px;">Account Number</div>
            <div style="font-size:15px;font-weight:600;font-family:monospace;color:var(--ink);"><?= e($account['AccountNumber']) ?></div>
        </div>
        <div style="background:var(--surface);border:1px solid var(--cream-3);border-radius:12px;padding:16px;">
            <div style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--ink-muted);margin-bottom:8px;">Branch</div>
            <div style="font-size:15px;font-weight:600;color:var(--ink);"><?= e($account['BranchName'] ?? '—') ?></div>
            <div style="font-size:12px;color:var(--ink-faint);"><?= e($account['IFSCCode'] ?? '') ?></div>
        </div>
        <div style="background:var(--surface);border:1px solid var(--cream-3);border-radius:12px;padding:16px;">
            <div style="font-size:11px;font-weight:600;letter-spacing:.08em;text-transform:uppercase;color:var(--ink-muted);margin-bottom:8px;">KYC Status</div>
            <?php if ($kyc): ?>
            <span class="badge badge-<?= $kyc['status']==='verified' ? 'success' : ($kyc['status']==='rejected' ? 'danger' : 'warning') ?>">
                <?= ucfirst($kyc['status']) ?>
            </span>
            <?php else: ?>
            <span class="badge badge-neutral">Not submitted</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="card reveal" style="margin-bottom:24px;">
        <div class="card-header">
            <h4>Quick Actions</h4>
        </div>
        <div class="card-body">
            <div class="quick-actions">
                <a href="deposit.php" class="quick-action">
                    <div class="qa-icon" style="background:var(--green-light);color:var(--green);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                    </div>
                    <span class="qa-label">Deposit</span>
                </a>
                <a href="withdraw.php" class="quick-action">
                    <div class="qa-icon" style="background:var(--red-light);color:var(--red);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                    </div>
                    <span class="qa-label">Withdraw</span>
                </a>
                <a href="transfer.php" class="quick-action">
                    <div class="qa-icon" style="background:var(--blue-light);color:var(--blue);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
                    </div>
                    <span class="qa-label">Transfer</span>
                </a>
                <a href="cards.php" class="quick-action">
                    <div class="qa-icon" style="background:var(--amber-light);color:var(--amber);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
                    </div>
                    <span class="qa-label">Cards</span>
                </a>
                <a href="notifications.php" class="quick-action">
                    <div class="qa-icon" style="background:var(--cream-2);color:var(--ink-muted);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
                    </div>
                    <span class="qa-label">Alerts</span>
                </a>
                <a href="feedback.php" class="quick-action">
                    <div class="qa-icon" style="background:var(--cream-2);color:var(--ink-muted);">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
                    </div>
                    <span class="qa-label">Support</span>
                </a>
            </div>
        </div>
    </div>

    <!-- Transactions + Notifications -->
    <div style="display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;">

        <!-- Recent Transactions -->
        <div class="card reveal">
            <div class="card-header">
                <h4>Recent Transactions</h4>
                <a href="transactions.php" class="btn btn-ghost btn-sm">View all →</a>
            </div>
            <div class="card-body" style="padding:0 24px;">
                <?php if (empty($transactions)): ?>
                <div class="empty-state" style="padding:40px 0;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
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
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
                        <?php elseif ($iconClass === 'debit'): ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
                        <?php else: ?>
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/></svg>
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
                    <div style="padding:24px;text-align:center;color:var(--ink-faint);font-size:13px;">No notifications</div>
                    <?php else: ?>
                    <?php foreach ($notifications as $n):
                        $ntype = $n['type'] ?? 'info';
                        $colors = ['success'=>'var(--green)','warning'=>'var(--amber)','danger'=>'var(--red)','info'=>'var(--blue)'];
                        $color  = $colors[$ntype] ?? 'var(--ink-muted)';
                    ?>
                    <div style="display:flex;gap:12px;padding:14px 16px;border-bottom:1px solid var(--cream-3);<?= !$n['is_read'] ? 'background:var(--surface-2);' : '' ?>">
                        <div style="width:8px;height:8px;border-radius:50%;background:<?= $color ?>;flex-shrink:0;margin-top:5px;"></div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:13px;font-weight:500;color:var(--ink-2);"><?= e($n['title']) ?></div>
                            <div style="font-size:12px;color:var(--ink-faint);margin-top:2px;"><?= timeAgo($n['created_at']) ?></div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <div style="padding:10px 16px;">
                        <a href="notifications.php" style="font-size:13px;color:var(--red);text-decoration:none;font-weight:500;">View all notifications →</a>
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
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;border-bottom:1px solid var(--cream-3);">
                        <span style="font-size:12px;color:var(--ink-muted);"><?= e($k) ?></span>
                        <span style="font-size:12px;font-weight:500;color:var(--ink);"><?= e($v) ?></span>
                    </div>
                    <?php endforeach; ?>
                    <?php if ($nominee): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;padding:7px 0;">
                        <span style="font-size:12px;color:var(--ink-muted);">Nominee</span>
                        <span style="font-size:12px;font-weight:500;color:var(--ink);"><?= e($nominee['NomineeName']) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Helper: lighten a hex color
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
