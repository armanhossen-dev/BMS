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
$cardStmt = $pdo->prepare("SELECT * FROM CARDS WHERE CustomerID = ? AND CardStatus = 'Active' LIMIT 1");
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

<!-- ── Top Overview Grid: Big Credit Card + Analytics Rows ────────────── -->
<div class="dashboard-overview-grid reveal" style="display:grid;grid-template-columns:390px 1fr;gap:20px;margin-bottom:20px;align-items:stretch;">

    <!-- Left: Big and Perfect Credit Card -->
    <div class="bank-card"
         style="background:linear-gradient(135deg, <?= e($tier['color']) ?> 0%, <?= e(adjustBrightness($tier['color'] ?? '#185FA5', 25)) ?> 60%, <?= e(adjustBrightness($tier['color'] ?? '#185FA5', -15)) ?> 100%); width:100%; max-width:390px; min-height:246px; border-radius:18px; padding:22px 24px; display:flex; flex-direction:column; justify-content:space-between; position:relative; overflow:hidden; box-shadow:0 18px 36px -10px rgba(0,0,0,0.38), 0 0 0 1px rgba(255,255,255,0.12) inset;">
        <!-- Specular shine overlays -->
        <div style="position:absolute;top:-40%;right:-20%;width:240px;height:240px;background:radial-gradient(circle,rgba(255,255,255,0.12) 0%,transparent 70%);border-radius:50%;pointer-events:none;"></div>
        <div style="position:absolute;bottom:-40%;left:-20%;width:200px;height:200px;background:radial-gradient(circle,rgba(255,255,255,0.06) 0%,transparent 70%);border-radius:50%;pointer-events:none;"></div>

        <!-- Card Top: Brand & Contactless Icon -->
        <div style="display:flex;justify-content:space-between;align-items:center;position:relative;z-index:2;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:28px;height:28px;border-radius:8px;background:rgba(255,255,255,0.16);display:flex;align-items:center;justify-content:center;backdrop-filter:blur(4px);">
                    <svg viewBox="0 0 24 24" fill="none" stroke="#FFFFFF" stroke-width="2.2" stroke-linecap="round" width="16" height="16"><path d="M4 16a8 8 0 0 1 16 0"/><path d="M12 4v2M4.9 7.9l1.4 1.4M19.1 7.9l-1.4 1.4M2 20h20"/></svg>
                </div>
                <span style="font-weight:700;font-size:15px;letter-spacing:-0.02em;color:#FFFFFF;">Asha Bank</span>
            </div>
            <i class='bx bx-rfid' style="font-size:24px;color:rgba(255,255,255,0.85);transform:rotate(90deg);" title="Contactless Payment"></i>
        </div>

        <!-- Card Middle: Realistic EMV Chip & Embossed Card Number -->
        <div style="position:relative;z-index:2;margin:12px 0 8px;">
            <div class="bank-card-chip" style="width:42px;height:30px;border-radius:6px;background:linear-gradient(135deg,#FFE382 0%,#D4A843 50%,#9E751D 100%);border:1px solid rgba(0,0,0,0.25);position:relative;overflow:hidden;box-shadow:inset 0 1px 2px rgba(255,255,255,0.4);">
                <div style="position:absolute;inset:0;background:repeating-linear-gradient(90deg,transparent,transparent 12px,rgba(0,0,0,0.15) 12px,rgba(0,0,0,0.15) 14px);opacity:0.6;"></div>
                <div style="position:absolute;top:50%;left:0;right:0;height:1px;background:rgba(0,0,0,0.25);"></div>
            </div>
            <div class="bank-card-number" style="font-family:'SF Mono','Fira Code',monospace;font-size:18.5px;font-weight:600;letter-spacing:0.18em;color:#FFFFFF;text-shadow:0 1px 2px rgba(0,0,0,0.6);margin-top:12px;margin-bottom:0;">
                <?= $card ? maskCardNumber($card['CardNumber']) : '•••• •••• •••• ••••' ?>
            </div>
        </div>

        <!-- Card Bottom: Cardholder, Expiry, Tier & Visa -->
        <div style="display:flex;justify-content:space-between;align-items:flex-end;position:relative;z-index:2;">
            <div>
                <div style="font-size:8.5px;text-transform:uppercase;letter-spacing:0.09em;color:rgba(255,255,255,0.55);margin-bottom:2px;">Cardholder</div>
                <div style="font-size:13.5px;font-weight:600;color:#FFFFFF;letter-spacing:0.03em;text-transform:uppercase;max-width:140px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                    <?= e($account['FirstName'] . ' ' . $account['LastName']) ?>
                </div>
            </div>
            <div style="text-align:center;padding:0 8px;">
                <div style="font-size:8.5px;text-transform:uppercase;letter-spacing:0.09em;color:rgba(255,255,255,0.55);margin-bottom:2px;">Expires</div>
                <div style="font-size:13px;font-weight:600;color:#FFFFFF;letter-spacing:0.05em;">
                    <?= $card ? date('m/y', strtotime($card['ExpiryDate'])) : '12/30' ?>
                </div>
            </div>
            <div style="text-align:right;">
                <div style="font-size:9.5px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:rgba(255,255,255,0.75);margin-bottom:2px;">
                    <?= e($tier['name']) ?>
                </div>
                <div style="font-size:19px;font-weight:900;letter-spacing:0.04em;color:#FFFFFF;font-style:italic;line-height:1;">
                    VISA
                </div>
            </div>
        </div>
    </div>

    <!-- Right: Analytics Rows (Row 1: 3 columns, Row 2: 4 columns) -->
    <div style="display:flex;flex-direction:column;gap:14px;justify-content:space-between;">

        <!-- Row 1: 3 Columns (Available Balance, Total Received, Total Sent) -->
        <div class="overview-row-3" style="display:grid;grid-template-columns:repeat(3, 1fr);gap:14px;">
            <div class="stat-card" style="padding:14px 16px;min-height:114px;display:flex;flex-direction:column;justify-content:space-between;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;">
                    <div class="stat-card-label" style="margin-bottom:0;">Available Balance</div>
                    <div class="stat-card-icon" style="position:static;width:30px;height:30px;font-size:17px;background:rgba(22,163,74,0.08);color:#16A34A;">
                        <i class='bx bx-wallet'></i>
                    </div>
                </div>
                <div>
                    <div class="stat-card-value" style="font-size:22px;color:#16A34A;margin-bottom:2px;letter-spacing:-0.03em;">
                        <?= formatBDT($balance) ?>
                    </div>
                    <div class="stat-card-sub" style="font-size:11.5px;"><?= e($account['AccountType'] ?? 'Savings') ?> · <?= e($account['AccountStatus']) ?></div>
                </div>
            </div>

            <div class="stat-card" style="padding:14px 16px;min-height:114px;display:flex;flex-direction:column;justify-content:space-between;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;">
                    <div class="stat-card-label" style="margin-bottom:0;">Total Received</div>
                    <div class="stat-card-icon" style="position:static;width:30px;height:30px;font-size:17px;background:rgba(59,130,246,0.08);color:#3B82F6;">
                        <i class='bx bx-trending-up'></i>
                    </div>
                </div>
                <div>
                    <div class="stat-card-value" style="font-size:22px;margin-bottom:2px;letter-spacing:-0.03em;">
                        <?= formatBDT($totalReceived) ?>
                    </div>
                    <div class="stat-card-sub" style="font-size:11.5px;">All-time credits</div>
                </div>
            </div>

            <div class="stat-card" style="padding:14px 16px;min-height:114px;display:flex;flex-direction:column;justify-content:space-between;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;">
                    <div class="stat-card-label" style="margin-bottom:0;">Total Sent</div>
                    <div class="stat-card-icon" style="position:static;width:30px;height:30px;font-size:17px;background:rgba(239,68,68,0.08);color:#EF4444;">
                        <i class='bx bx-trending-down'></i>
                    </div>
                </div>
                <div>
                    <div class="stat-card-value" style="font-size:22px;margin-bottom:2px;letter-spacing:-0.03em;">
                        <?= formatBDT($totalSent) ?>
                    </div>
                    <div class="stat-card-sub" style="font-size:11.5px;">All-time debits</div>
                </div>
            </div>
        </div>

        <!-- Row 2: 4 Columns (Transactions, Account Number, Branch, KYC Status) -->
        <div class="overview-row-4" style="display:grid;grid-template-columns:repeat(4, 1fr);gap:14px;">
            <div class="stat-card" style="padding:14px 16px;min-height:114px;display:flex;flex-direction:column;justify-content:space-between;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;">
                    <div class="stat-card-label" style="margin-bottom:0;">Transactions</div>
                    <div class="stat-card-icon" style="position:static;width:30px;height:30px;font-size:17px;background:rgba(245,158,11,0.08);color:#F59E0B;">
                        <i class='bx bx-transfer'></i>
                    </div>
                </div>
                <div>
                    <div class="stat-card-value" style="font-size:22px;margin-bottom:2px;letter-spacing:-0.03em;">
                        <?= $txnCount ?>
                    </div>
                    <div class="stat-card-sub" style="font-size:11.5px;">Total count</div>
                </div>
            </div>

            <div class="stat-card" style="padding:14px 16px;min-height:114px;display:flex;flex-direction:column;justify-content:space-between;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;">
                    <div class="stat-card-label" style="margin-bottom:0;">Account Number</div>
                    <div class="stat-card-icon" style="position:static;width:30px;height:30px;font-size:17px;background:rgba(100,116,139,0.08);color:#64748B;">
                        <i class='bx bx-credit-card-front'></i>
                    </div>
                </div>
                <div>
                    <div class="stat-card-value" style="font-size:15px;font-family:'SF Mono','Fira Code',monospace;font-weight:600;margin-bottom:2px;letter-spacing:0.02em;">
                        <?= e($account['AccountNumber']) ?>
                    </div>
                    <div class="stat-card-sub" style="font-size:11.5px;"><?= e($account['AccountType'] ?? 'Savings') ?> Account</div>
                </div>
            </div>

            <div class="stat-card" style="padding:14px 16px;min-height:114px;display:flex;flex-direction:column;justify-content:space-between;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;">
                    <div class="stat-card-label" style="margin-bottom:0;">Branch</div>
                    <div class="stat-card-icon" style="position:static;width:30px;height:30px;font-size:17px;background:rgba(2,132,199,0.08);color:#0284C7;">
                        <i class='bx bx-buildings'></i>
                    </div>
                </div>
                <div>
                    <div class="stat-card-value" style="font-size:13.5px;font-weight:600;margin-bottom:2px;line-height:1.25;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;" title="<?= e($account['BranchName'] ?? '—') ?>">
                        <?= e($account['BranchName'] ?? '—') ?>
                    </div>
                    <div class="stat-card-sub" style="font-size:11.5px;"><?= e($account['IFSCCode'] ?? 'Corporate Branch') ?></div>
                </div>
            </div>

            <div class="stat-card" style="padding:14px 16px;min-height:114px;display:flex;flex-direction:column;justify-content:space-between;">
                <div style="display:flex;align-items:flex-start;justify-content:space-between;">
                    <div class="stat-card-label" style="margin-bottom:0;">KYC Status</div>
                    <div class="stat-card-icon" style="position:static;width:30px;height:30px;font-size:17px;background:rgba(22,163,74,0.08);color:#16A34A;">
                        <i class='bx bx-shield-quarter'></i>
                    </div>
                </div>
                <div>
                    <div style="margin-bottom:4px;">
                        <?php if ($kyc): ?>
                        <span class="badge badge-<?= $kyc['status']==='verified' ? 'success' : ($kyc['status']==='rejected' ? 'danger' : 'warning') ?>" style="display:inline-flex;align-items:center;gap:4px;padding:3px 8px;font-size:11px;">
                            <i class='bx bx-<?= $kyc['status']==='verified' ? 'check-shield' : 'shield-x' ?>'></i>
                            <?= ucfirst($kyc['status']) ?>
                        </span>
                        <?php else: ?>
                        <span class="badge badge-neutral" style="padding:3px 8px;font-size:11px;">Not submitted</span>
                        <?php endif; ?>
                    </div>
                    <div class="stat-card-sub" style="font-size:11.5px;"><?= $kyc && $kyc['status']==='verified' ? 'Identity verified' : 'Identity review' ?></div>
                </div>
            </div>
        </div>

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
                <div class="qa-icon" style="background:rgba(147,51,234,0.08);color:#9333EA;">
                    <i class='bx bx-bell'></i>
                </div>
                <span class="qa-label">Alerts</span>
            </a>
            <a href="feedback.php" class="quick-action">
                <div class="qa-icon" style="background:rgba(14,165,233,0.08);color:#0EA5E9;">
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
