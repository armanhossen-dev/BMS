<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/language.php';
require_once __DIR__ . '/../includes/functions.php';
requireAuth('staff');

$staffId = (int)($_SESSION['staff_id'] ?? $_SESSION['user_id'] ?? 0);
$stmt = $pdo->prepare("SELECT * FROM STAFF WHERE staff_id = ?");
$stmt->execute([$staffId]);
$staff = $stmt->fetch();
if (!$staff) {
    redirect('logout.php');
}

$tab = sanitize($_GET['tab'] ?? 'overview');
if ($tab === 'dashboard') $tab = 'overview';
$activePage = ($tab === 'overview') ? 'dashboard' : $tab;
$pageTitle  = 'Staff Portal · ' . ucfirst($tab);

/* ─────────────────────────────────────────────────────────────
   POST ACTIONS
   ───────────────────────────────────────────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // Action 1: KYC Decision (Verify or Reject)
    if ($action === 'kyc_decide') {
        $kycId    = (int)$_POST['kyc_id'];
        $decision = ($_POST['decision'] === 'verified') ? 'verified' : 'rejected';
        $reason   = trim($_POST['reason'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM KYC_VERIFICATIONS WHERE kyc_id = ?");
        $stmt->execute([$kycId]);
        $kyc = $stmt->fetch();

        if ($kyc) {
            $pdo->prepare("UPDATE KYC_VERIFICATIONS SET status = ?, rejection_reason = ?, verified_by = ?, verified_at = NOW() WHERE kyc_id = ?")
                ->execute([$decision, $decision === 'rejected' ? $reason : null, $staffId, $kycId]);
            
            $notifTitle = ($decision === 'verified') ? 'KYC Verified' : 'KYC Submission Rejected';
            $notifMsg   = ($decision === 'verified') 
                ? 'Your National Identity verification has been approved. All banking limits are unlocked.' 
                : 'Your KYC submission was rejected. Reason: ' . ($reason ?: 'Documentation unclear');
            
            notify($kyc['customer_id'], $notifTitle, $notifMsg, $decision === 'verified' ? 'success' : 'danger');
            setToast('KYC marked as ' . ucfirst($decision) . '.', $decision === 'verified' ? 'success' : 'warning', 3000, 'KYC Updated');
        }
        header('Location: ' . BASE_URL . '/staff/dashboard.php?tab=kyc');
        exit;
    }

    // Action 2: Reply to Customer Feedback
    if ($action === 'feedback_reply') {
        $fid   = (int)$_POST['feedback_id'];
        $reply = trim($_POST['reply'] ?? '');

        $stmt = $pdo->prepare("SELECT * FROM FEEDBACK WHERE feedback_id = ?");
        $stmt->execute([$fid]);
        $fb = $stmt->fetch();

        if ($fb && $reply !== '') {
            $pdo->prepare("UPDATE FEEDBACK SET admin_reply = ?, status = 'resolved', resolved_at = NOW() WHERE feedback_id = ?")
                ->execute([$reply, $fid]);
            
            notify($fb['customer_id'], 'Reply to Support Ticket', 'Staff replied to "' . $fb['subject'] . '".', 'info');
            setToast('Reply posted and ticket marked as resolved.', 'success', 3000, 'Ticket Replied');
        }
        header('Location: ' . BASE_URL . '/staff/dashboard.php?tab=feedback');
        exit;
    }

    // Action 3: Send Message to Admin
    if ($action === 'send_message') {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');

        if ($subject && $message) {
            $pdo->prepare("INSERT INTO STAFF_MESSAGES (staff_id, sender_id, subject, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())")
                ->execute([$staffId, $staffId, $subject, $message]);
            
            setToast('Message transmitted directly to Admin Control.', 'success', 3000, 'Message Sent');
        } else {
            setToast('Please provide both subject and message.', 'danger', 3000, 'Error');
        }
        header('Location: ' . BASE_URL . '/staff/dashboard.php?tab=messages');
        exit;
    }

    // Action 4: Change Staff Password
    if ($action === 'change_password') {
        $currentPass = $_POST['current_password'] ?? '';
        $newPass     = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        if (!password_verify($currentPass, $staff['password_hash'])) {
            setToast('Current password does not match.', 'danger', 3500, 'Auth Error');
        } elseif (strlen($newPass) < 6) {
            setToast('New password must be at least 6 characters.', 'danger', 3500, 'Weak Password');
        } elseif ($newPass !== $confirmPass) {
            setToast('New passwords do not match.', 'danger', 3500, 'Password Mismatch');
        } else {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $pdo->prepare("UPDATE STAFF SET password_hash = ? WHERE staff_id = ?")->execute([$newHash, $staffId]);
            setToast('Your password has been successfully updated.', 'success', 3500, 'Security Updated');
        }
        header('Location: ' . BASE_URL . '/staff/dashboard.php?tab=profile');
        exit;
    }
}

/* ─────────────────────────────────────────────────────────────
   DATA QUERIES
   ───────────────────────────────────────────────────────────── */
$pendingKyc      = (int)$pdo->query("SELECT COUNT(*) FROM KYC_VERIFICATIONS WHERE status = 'pending'")->fetchColumn();
$todayTxns       = (int)$pdo->query("SELECT COUNT(*) FROM TRANSACTION WHERE DATE(TransactionDate) = CURDATE()")->fetchColumn();
$todayVolume     = (float)$pdo->query("SELECT COALESCE(SUM(TransactionAmount),0) FROM TRANSACTION WHERE DATE(TransactionDate) = CURDATE()")->fetchColumn();
$pendingFeedback = (int)$pdo->query("SELECT COUNT(*) FROM FEEDBACK WHERE status = 'pending'")->fetchColumn();
$totalCustomers  = (int)$pdo->query("SELECT COUNT(*) FROM CUSTOMER WHERE IsActive = 1")->fetchColumn();

// KYC Queue
$kycList = $pdo->query(
    "SELECT k.*, c.FirstName, c.LastName, c.Email, c.Phone 
     FROM KYC_VERIFICATIONS k
     JOIN CUSTOMER c ON k.customer_id = c.CustomerID
     WHERE k.status = 'pending' 
     ORDER BY k.submitted_at DESC"
)->fetchAll();

// Customer Feedback List
$feedbackList = $pdo->query(
    "SELECT f.*, c.FirstName, c.LastName, c.Email 
     FROM FEEDBACK f
     JOIN CUSTOMER c ON f.customer_id = c.CustomerID
     ORDER BY (f.status = 'pending') DESC, f.created_at DESC 
     LIMIT 40"
)->fetchAll();

// Staff Outbox Messages
$stmt = $pdo->prepare("SELECT * FROM STAFF_MESSAGES WHERE staff_id = ? ORDER BY created_at DESC LIMIT 30");
$stmt->execute([$staffId]);
$myMessages = $stmt->fetchAll();

// Transactions Ledger (tab === 'transactions' or overview)
$searchTxn = sanitize($_GET['q'] ?? '');
$sqlTxn = "SELECT t.*, tt.TypeName, 
                  c1.FirstName as FromFirst, c1.LastName as FromLast,
                  c2.FirstName as ToFirst, c2.LastName as ToLast
           FROM TRANSACTION t
           JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
           LEFT JOIN CUSTOMER c1 ON t.FromCustomerID = c1.CustomerID
           LEFT JOIN CUSTOMER c2 ON t.ToCustomerID = c2.CustomerID";
$paramsTxn = [];
if ($searchTxn) {
    $sqlTxn .= " WHERE t.ReferenceNumber LIKE ? OR t.FromAccountNumber LIKE ? OR t.ToAccountNumber LIKE ?";
    $paramsTxn = ["%$searchTxn%", "%$searchTxn%", "%$searchTxn%"];
}
$sqlTxn .= " ORDER BY t.TransactionDate DESC LIMIT 25";
$stmtTxn = $pdo->prepare($sqlTxn);
$stmtTxn->execute($paramsTxn);
$txns = $stmtTxn->fetchAll();

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-content">

    <!-- ── TAB: OVERVIEW ──────────────────────────────────────── -->
    <?php if ($tab === 'overview'): ?>
    
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
            <h1>Welcome, <?= clean($staff['first_name'] . ' ' . $staff['last_name']) ?></h1>
            <p><?= ucfirst($staff['role'] ?? 'Officer') ?> · <?= clean($staff['department'] ?? 'Operations') ?> · Daily operations overview for <?= date('l, d F Y') ?>.</p>
        </div>
        <div style="display:flex;align-items:center;gap:10px;">
            <span class="badge badge-success" style="padding:6px 12px;font-size:12px;">
                <i class='bx bx-check-shield'></i> Staff Session Active
            </span>
        </div>
    </div>

    <!-- Stat Cards Grid -->
    <div class="grid grid-4" style="gap:16px;margin-bottom:24px;">
        <div class="stat-card reveal">
            <div class="stat-card-icon" style="background:rgba(220,38,38,0.1);color:#DC2626;">
                <i class='bx bx-shield-quarter'></i>
            </div>
            <div class="stat-card-label">Pending KYC</div>
            <div class="stat-card-value"><?= number_format($pendingKyc) ?></div>
            <div class="stat-card-sub" style="color:var(--text-muted);">Awaiting verification</div>
        </div>

        <div class="stat-card reveal">
            <div class="stat-card-icon" style="background:rgba(217,119,6,0.1);color:#D97706;">
                <i class='bx bx-message-dots'></i>
            </div>
            <div class="stat-card-label">Pending Tickets</div>
            <div class="stat-card-value"><?= number_format($pendingFeedback) ?></div>
            <div class="stat-card-sub" style="color:var(--text-muted);">Customer queries</div>
        </div>

        <div class="stat-card reveal">
            <div class="stat-card-icon" style="background:rgba(37,99,235,0.1);color:#2563EB;">
                <i class='bx bx-transfer-alt'></i>
            </div>
            <div class="stat-card-label">Today's Txns</div>
            <div class="stat-card-value"><?= number_format($todayTxns) ?></div>
            <div class="stat-card-sub" style="color:var(--text-muted);">Volume: <?= currency($todayVolume) ?></div>
        </div>

        <div class="stat-card reveal">
            <div class="stat-card-icon" style="background:rgba(22,163,74,0.1);color:#16A34A;">
                <i class='bx bx-group'></i>
            </div>
            <div class="stat-card-label">Active Customers</div>
            <div class="stat-card-value"><?= number_format($totalCustomers) ?></div>
            <div class="stat-card-sub" style="color:var(--text-muted);">Across branches</div>
        </div>
    </div>

    <!-- Quick Shortcuts Banner -->
    <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(260px, 1fr));gap:16px;margin-bottom:28px;">
        <a href="?tab=kyc" class="card" style="padding:18px 20px;text-decoration:none;display:flex;align-items:center;gap:14px;border:1px solid var(--border);transition:transform var(--dur);">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(220,38,38,0.08);color:#DC2626;display:flex;align-items:center;justify-content:center;font-size:22px;">
                <i class='bx bx-user-check'></i>
            </div>
            <div>
                <div style="font-weight:600;font-size:14px;color:var(--text-primary);">Review KYC Queue</div>
                <div style="font-size:12px;color:var(--text-muted);"><?= $pendingKyc ?> submission(s) pending approval</div>
            </div>
        </a>

        <a href="?tab=feedback" class="card" style="padding:18px 20px;text-decoration:none;display:flex;align-items:center;gap:14px;border:1px solid var(--border);transition:transform var(--dur);">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(217,119,6,0.08);color:#D97706;display:flex;align-items:center;justify-content:center;font-size:22px;">
                <i class='bx bx-support'></i>
            </div>
            <div>
                <div style="font-weight:600;font-size:14px;color:var(--text-primary);">Respond to Inquiries</div>
                <div style="font-size:12px;color:var(--text-muted);"><?= $pendingFeedback ?> query ticket(s) awaiting response</div>
            </div>
        </a>

        <a href="?tab=transactions" class="card" style="padding:18px 20px;text-decoration:none;display:flex;align-items:center;gap:14px;border:1px solid var(--border);transition:transform var(--dur);">
            <div style="width:40px;height:40px;border-radius:10px;background:rgba(37,99,235,0.08);color:#2563EB;display:flex;align-items:center;justify-content:center;font-size:22px;">
                <i class='bx bx-list-check'></i>
            </div>
            <div>
                <div style="font-weight:600;font-size:14px;color:var(--text-primary);">Transactions Ledger</div>
                <div style="font-size:12px;color:var(--text-muted);">Audit incoming &amp; outgoing funds</div>
            </div>
        </a>
    </div>

    <!-- Recent Transactions Table Preview -->
    <div class="card reveal" style="padding:0;overflow:hidden;border:1px solid var(--border);">
        <div class="card-header" style="background:var(--bg-subtle);padding:16px 20px;display:flex;justify-content:space-between;align-items:center;">
            <div style="display:flex;align-items:center;gap:10px;">
                <div style="width:30px;height:30px;border-radius:8px;background:var(--bg-base);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:16px;">
                    <i class='bx bx-trending-up'></i>
                </div>
                <h3 style="margin:0;font-size:15px;font-weight:600;">Recent Bank Transactions</h3>
            </div>
            <a href="?tab=transactions" class="btn btn-outline btn-xs">View All &rarr;</a>
        </div>
        <div class="card-body" style="padding:0;">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Time</th>
                            <th>From</th>
                            <th>To</th>
                            <th>Type</th>
                            <th style="text-align:right;">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($txns)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-muted);">No transactions recorded.</td></tr>
                        <?php else: foreach (array_slice($txns, 0, 8) as $t): ?>
                        <tr>
                            <td class="mono" style="font-weight:600;"><?= e($t['ReferenceNumber']) ?></td>
                            <td style="color:var(--text-muted);font-size:13px;"><?= time_ago($t['TransactionDate']) ?></td>
                            <td><?= $t['FromFirst'] ? e($t['FromFirst'] . ' ' . $t['FromLast']) : ($t['FromAccountNumber'] ? e($t['FromAccountNumber']) : 'Bank') ?></td>
                            <td><?= $t['ToFirst'] ? e($t['ToFirst'] . ' ' . $t['ToLast']) : ($t['ToAccountNumber'] ? e($t['ToAccountNumber']) : 'Self / Branch') ?></td>
                            <td><span class="badge badge-outline" style="font-size:11px;"><?= e($t['TypeName']) ?></span></td>
                            <td style="text-align:right;font-weight:600;font-family:var(--font-mono);"><?= currency($t['TransactionAmount']) ?></td>
                            <td><span class="badge badge-success" style="font-size:11px;"><?= e($t['TransactionStatus']) ?></span></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── TAB: KYC REVIEW ────────────────────────────────────── -->
    <?php elseif ($tab === 'kyc'): ?>
    
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
            <h1>KYC Verification Queue</h1>
            <p>Review customer NID submissions, verify biometric credentials, or state rejection reasons.</p>
        </div>
        <span class="badge badge-outline" style="padding:6px 12px;font-size:12px;">
            <?= count($kycList) ?> Pending Verification
        </span>
    </div>

    <div class="card reveal" style="padding:0;overflow:hidden;border:1px solid var(--border);">
        <div class="card-body" style="padding:0;">
            <?php if (empty($kycList)): ?>
            <div style="padding:56px 20px;text-align:center;">
                <div style="width:60px;height:60px;border-radius:16px;background:var(--bg-muted);display:inline-flex;align-items:center;justify-content:center;font-size:30px;color:var(--success);margin-bottom:14px;">
                    <i class='bx bx-check-double'></i>
                </div>
                <h3 style="font-size:17px;font-weight:600;margin-bottom:6px;">All Caught Up!</h3>
                <p style="color:var(--text-muted);font-size:14px;max-width:380px;margin:0 auto;">
                    There are no pending KYC documents waiting for staff verification right now.
                </p>
            </div>
            <?php else: ?>
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Customer Name</th>
                            <th>NID / Identity Number</th>
                            <th>Phone Contact</th>
                            <th>Submitted</th>
                            <th style="text-align:right;">Verification Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($kycList as $k): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;color:var(--text-primary);"><?= clean($k['FirstName'] . ' ' . $k['LastName']) ?></div>
                                <div style="font-size:12px;color:var(--text-muted);"><?= clean($k['Email']) ?></div>
                            </td>
                            <td>
                                <div class="mono" style="font-weight:600;"><?= clean($k['nid_number']) ?></div>
                                <div style="font-size:11px;color:var(--text-muted);"><?= clean($k['id_type'] ?? 'National ID') ?></div>
                            </td>
                            <td><?= clean($k['Phone'] ?? 'N/A') ?></td>
                            <td style="color:var(--text-muted);font-size:13px;"><?= time_ago($k['submitted_at']) ?></td>
                            <td style="text-align:right;">
                                <div style="display:inline-flex;gap:8px;">
                                    <!-- Verify Form -->
                                    <form method="POST" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="kyc_decide">
                                        <input type="hidden" name="kyc_id" value="<?= $k['kyc_id'] ?>">
                                        <input type="hidden" name="decision" value="verified">
                                        <button type="submit" class="btn btn-sm btn-primary"
                                                data-confirm="Verify and activate KYC for <?= clean($k['FirstName']) ?>? This unlocks all banking features."
                                                data-confirm-title="Approve KYC"
                                                data-confirm-btn="Yes, Approve"
                                                data-confirm-class="btn-primary"
                                                data-confirm-icon="bx-check-circle">
                                            <i class='bx bx-check'></i> Verify
                                        </button>
                                    </form>

                                    <!-- Inspect Modal Trigger -->
                                    <button type="button" class="btn btn-sm btn-ghost" data-modal-open="viewKycModal-<?= $k['kyc_id'] ?>" title="Inspect Verification Details">
                                        <i class='bx bx-show'></i> Details
                                    </button>

                                    <!-- Verify Form -->
                                    <form method="POST" style="margin:0;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="kyc_decide">
                                        <input type="hidden" name="kyc_id" value="<?= $k['kyc_id'] ?>">
                                        <input type="hidden" name="decision" value="verified">
                                        <button type="submit" class="btn btn-sm btn-primary"
                                                data-confirm="Verify and activate KYC for <?= clean($k['FirstName']) ?>? This unlocks all banking features."
                                                data-confirm-title="Approve KYC"
                                                data-confirm-btn="Yes, Approve"
                                                data-confirm-class="btn-primary"
                                                data-confirm-icon="bx-check-circle">
                                            <i class='bx bx-check'></i> Verify
                                        </button>
                                    </form>

                                    <!-- Reject Trigger Modal -->
                                    <button type="button" class="btn btn-sm btn-danger" data-modal-open="rejectKycModal-<?= $k['kyc_id'] ?>">
                                        <i class='bx bx-x'></i> Reject
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- KYC Modals (Placed cleanly outside table) -->
            <?php foreach ($kycList as $k): ?>
            <!-- Modal: Inspect KYC -->
            <div class="modal-overlay" id="viewKycModal-<?= $k['kyc_id'] ?>" style="display:none;">
                <div class="modal" style="max-width:540px;" role="dialog" aria-modal="true">
                    <div class="modal-header">
                        <div style="display:flex;align-items:center;gap:10px;">
                            <div style="width:32px;height:32px;border-radius:8px;background:rgba(37,99,235,0.1);color:#2563EB;display:flex;align-items:center;justify-content:center;font-size:18px;">
                                <i class='bx bx-id-card'></i>
                            </div>
                            <h3 style="margin:0;font-size:16px;">KYC Dossier · <?= clean($k['FirstName'] . ' ' . $k['LastName']) ?></h3>
                        </div>
                        <button class="modal-close" type="button" data-modal-close="viewKycModal-<?= $k['kyc_id'] ?>">&times;</button>
                    </div>
                    <div class="modal-body" style="padding:var(--sp-6);">
                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;margin-bottom:18px;font-size:13px;background:var(--bg-subtle);padding:14px;border-radius:var(--r);border:1px solid var(--border);">
                            <div>
                                <span style="color:var(--text-muted);display:block;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;">Customer</span>
                                <strong><?= clean($k['FirstName'] . ' ' . $k['LastName']) ?></strong>
                            </div>
                            <div>
                                <span style="color:var(--text-muted);display:block;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;">Document Type</span>
                                <strong><?= clean($k['id_type'] ?? 'National ID') ?></strong>
                            </div>
                            <div>
                                <span style="color:var(--text-muted);display:block;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;">ID / NID Number</span>
                                <strong class="mono"><?= clean($k['nid_number']) ?></strong>
                            </div>
                            <div>
                                <span style="color:var(--text-muted);display:block;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;">Phone Number</span>
                                <strong><?= clean($k['Phone'] ?? 'N/A') ?></strong>
                            </div>
                            <div>
                                <span style="color:var(--text-muted);display:block;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;">Email Address</span>
                                <strong><?= clean($k['Email']) ?></strong>
                            </div>
                            <div>
                                <span style="color:var(--text-muted);display:block;font-size:11px;text-transform:uppercase;letter-spacing:0.04em;">Submission Date</span>
                                <strong><?= date('M d, Y H:i', strtotime($k['submitted_at'])) ?></strong>
                            </div>
                        </div>

                        <div style="border-top:1px solid var(--border);padding-top:14px;">
                            <div style="font-size:12px;font-weight:600;margin-bottom:8px;color:var(--text-secondary);">Attached Document Previews</div>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <div style="background:var(--bg-base);border:1px dashed var(--border);border-radius:var(--r);padding:20px;text-align:center;">
                                    <i class='bx bx-image' style="font-size:28px;color:var(--text-muted);display:block;margin-bottom:6px;"></i>
                                    <span style="font-size:12px;color:var(--text-muted);">Front ID Scan<br>(Validated)</span>
                                </div>
                                <div style="background:var(--bg-base);border:1px dashed var(--border);border-radius:var(--r);padding:20px;text-align:center;">
                                    <i class='bx bx-image' style="font-size:28px;color:var(--text-muted);display:block;margin-bottom:6px;"></i>
                                    <span style="font-size:12px;color:var(--text-muted);">Back ID Scan<br>(Validated)</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer" style="padding:var(--sp-4) var(--sp-6);display:flex;justify-content:space-between;align-items:center;">
                        <button type="button" class="btn btn-outline btn-sm" data-modal-close="viewKycModal-<?= $k['kyc_id'] ?>">Close</button>
                        <div style="display:flex;gap:8px;">
                            <button type="button" class="btn btn-sm btn-danger" data-modal-close="viewKycModal-<?= $k['kyc_id'] ?>" onclick="Modal.open('rejectKycModal-<?= $k['kyc_id'] ?>');">
                                <i class='bx bx-x'></i> Reject
                            </button>
                            <form method="POST" style="margin:0;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="kyc_decide">
                                <input type="hidden" name="kyc_id" value="<?= $k['kyc_id'] ?>">
                                <input type="hidden" name="decision" value="verified">
                                <button type="submit" class="btn btn-sm btn-primary">
                                    <i class='bx bx-check'></i> Approve &amp; Verify
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal: Reject KYC -->
            <div class="modal-overlay" id="rejectKycModal-<?= $k['kyc_id'] ?>" style="display:none;">
                <div class="modal" style="max-width:440px;" role="dialog" aria-modal="true">
                    <div class="modal-header">
                        <h3 style="margin:0;font-size:16px;">Reject KYC Submission</h3>
                        <button class="modal-close" type="button" data-modal-close="rejectKycModal-<?= $k['kyc_id'] ?>">&times;</button>
                    </div>
                    <form method="POST">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="kyc_decide">
                        <input type="hidden" name="kyc_id" value="<?= $k['kyc_id'] ?>">
                        <input type="hidden" name="decision" value="rejected">
                        <div class="modal-body" style="padding:var(--sp-6);">
                            <p style="font-size:14px;color:var(--text-secondary);margin-bottom:16px;">
                                Specify why the KYC document for <strong><?= clean($k['FirstName'] . ' ' . $k['LastName']) ?></strong> is being rejected. The customer will receive this message.
                            </p>
                            <div class="form-group">
                                <label class="form-label" for="reason_<?= $k['kyc_id'] ?>">Rejection Reason <span class="required">*</span></label>
                                <textarea class="form-control" id="reason_<?= $k['kyc_id'] ?>" name="reason" rows="3"
                                          placeholder="e.g. Document image is blurry or NID number mismatch..." required></textarea>
                            </div>
                        </div>
                        <div class="modal-footer" style="padding:var(--sp-4) var(--sp-6);display:flex;justify-content:flex-end;gap:10px;">
                            <button type="button" class="btn btn-outline btn-sm" data-modal-close="rejectKycModal-<?= $k['kyc_id'] ?>">Cancel</button>
                            <button type="submit" class="btn btn-danger btn-sm">Confirm Rejection</button>
                        </div>
                    </form>
                </div>
            </div>
            <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </div>

    <!-- ── TAB: CUSTOMER FEEDBACK ─────────────────────────────── -->
    <?php elseif ($tab === 'feedback'): ?>
    
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
            <h1>Customer Support Tickets</h1>
            <p>Review customer feedback, complaints, and general service inquiries.</p>
        </div>
        <span class="badge badge-outline" style="padding:6px 12px;font-size:12px;">
            <?= count($feedbackList) ?> Total Tickets
        </span>
    </div>

    <div class="card reveal" style="padding:0;overflow:hidden;border:1px solid var(--border);">
        <div class="card-body" style="padding:22px;">
            <?php if (empty($feedbackList)): ?>
            <div style="padding:48px 20px;text-align:center;color:var(--text-muted);">
                <i class='bx bx-check-circle' style="font-size:36px;color:var(--success);margin-bottom:10px;display:block;"></i>
                No customer feedback tickets found.
            </div>
            <?php else: ?>
            <div style="display:flex;flex-direction:column;gap:20px;">
                <?php foreach ($feedbackList as $f): 
                    $isPending = ($f['status'] === 'pending');
                ?>
                <div style="background:var(--bg-base);border:1px solid var(--border);border-radius:var(--r);padding:20px;">
                    <div style="display:flex;justify-content:space-between;align-items:flex-start;flex-wrap:wrap;gap:12px;margin-bottom:10px;">
                        <div>
                            <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                                <span class="badge badge-outline" style="font-size:10px;"><?= ucfirst($f['type']) ?></span>
                                <h4 style="margin:0;font-size:15px;font-weight:600;color:var(--text-primary);"><?= clean($f['subject']) ?></h4>
                            </div>
                            <div style="font-size:12px;color:var(--text-muted);">
                                Customer: <strong><?= clean($f['FirstName'] . ' ' . $f['LastName']) ?></strong> (<?= clean($f['Email']) ?>) · <?= time_ago($f['created_at']) ?>
                            </div>
                        </div>
                        <span class="badge <?= $isPending ? 'badge-warning' : 'badge-success' ?>" style="font-size:11px;">
                            <i class='bx <?= $isPending ? 'bx-time' : 'bx-check-circle' ?>'></i>
                            <?= $isPending ? 'Needs Reply' : 'Resolved' ?>
                        </span>
                    </div>

                    <!-- Customer Message -->
                    <div style="font-size:13.5px;color:var(--text-secondary);background:var(--bg-subtle);border:1px solid var(--border-soft);border-radius:var(--r-sm);padding:12px 14px;line-height:1.5;">
                        <?= nl2br(clean($f['message'])) ?>
                    </div>

                    <!-- Staff Reply Display OR Reply Form -->
                    <?php if (!empty($f['admin_reply'])): ?>
                    <div style="margin-top:12px;background:var(--bg-muted);border-left:3px solid var(--success);border-radius:0 var(--r-sm) var(--r-sm) 0;padding:12px 14px;">
                        <div style="font-size:12px;font-weight:600;color:var(--text-primary);display:flex;align-items:center;gap:6px;margin-bottom:4px;">
                            <i class='bx bx-headphone' style="color:var(--success);"></i> Staff Official Response
                            <?php if (!empty($f['resolved_at'])): ?>
                            <span style="font-weight:400;color:var(--text-muted);font-size:11px;">· <?= time_ago($f['resolved_at']) ?></span>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:13px;color:var(--text-primary);line-height:1.5;">
                            <?= nl2br(clean($f['admin_reply'])) ?>
                        </div>
                    </div>
                    <?php else: ?>
                    <div style="margin-top:14px;">
                        <form method="POST" style="margin:0;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="feedback_reply">
                            <input type="hidden" name="feedback_id" value="<?= $f['feedback_id'] ?>">
                            
                            <div style="display:flex;gap:10px;align-items:flex-end;">
                                <div style="flex:1;">
                                    <textarea class="form-control" name="reply" rows="2" placeholder="Write official staff response to the customer..." required style="min-height:70px;"></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm" style="height:42px;flex-shrink:0;">
                                    <i class='bx bx-send'></i> Send Reply
                                </button>
                            </div>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- ── TAB: TRANSACTIONS LEDGER ───────────────────────────── -->
    <?php elseif ($tab === 'transactions'): ?>
    
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
            <h1>Transactions Ledger</h1>
            <p>Live transaction stream across customer accounts, deposits, transfers, and ATM withdrawals.</p>
        </div>
        <form method="GET" style="display:flex;gap:8px;align-items:center;margin:0;">
            <input type="hidden" name="tab" value="transactions">
            <div class="search-bar" style="width:260px;">
                <i class='bx bx-search'></i>
                <input type="text" name="q" placeholder="Reference or Account..." value="<?= e($searchTxn) ?>">
            </div>
            <button type="submit" class="btn btn-outline btn-sm">Filter</button>
        </form>
    </div>

    <div class="card reveal" style="padding:0;overflow:hidden;border:1px solid var(--border);">
        <div class="card-body" style="padding:0;">
            <div class="table-wrap">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Date &amp; Time</th>
                            <th>Sender</th>
                            <th>Recipient</th>
                            <th>Type</th>
                            <th style="text-align:right;">Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($txns)): ?>
                        <tr><td colspan="7" style="text-align:center;padding:48px;color:var(--text-muted);">No matching transactions found.</td></tr>
                        <?php else: foreach ($txns as $t): ?>
                        <tr>
                            <td class="mono" style="font-weight:600;"><?= e($t['ReferenceNumber']) ?></td>
                            <td style="color:var(--text-muted);font-size:13px;"><?= date('M d, Y H:i', strtotime($t['TransactionDate'])) ?></td>
                            <td><?= $t['FromFirst'] ? e($t['FromFirst'] . ' ' . $t['FromLast']) : ($t['FromAccountNumber'] ? e($t['FromAccountNumber']) : 'Asha Bank Central') ?></td>
                            <td><?= $t['ToFirst'] ? e($t['ToFirst'] . ' ' . $t['ToLast']) : ($t['ToAccountNumber'] ? e($t['ToAccountNumber']) : 'Branch / Self') ?></td>
                            <td><span class="badge badge-outline" style="font-size:11px;"><?= e($t['TypeName']) ?></span></td>
                            <td style="text-align:right;font-weight:600;font-family:var(--font-mono);font-size:14px;"><?= currency($t['TransactionAmount']) ?></td>
                            <td><span class="badge badge-success" style="font-size:11px;"><?= e($t['TransactionStatus']) ?></span></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- ── TAB: MESSAGES TO ADMIN ─────────────────────────────── -->
    <?php elseif ($tab === 'messages'): ?>
    
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
            <h1>Internal Messages to Admin</h1>
            <p>Send internal branch requests, operational notifications, or operational escalation reports directly to management.</p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(320px, 420px) 1fr;gap:24px;align-items:start;">
        
        <!-- Compose Message -->
        <div class="card reveal" style="padding:0;overflow:hidden;border:1px solid var(--border);">
            <div class="card-header" style="background:var(--bg-subtle);padding:16px 20px;">
                <h3 style="margin:0;font-size:15px;font-weight:600;">Compose Message to Admin</h3>
            </div>
            <div class="card-body" style="padding:22px 20px;">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="send_message">

                    <div class="form-group">
                        <label class="form-label" for="msgSubject">Subject <span class="required">*</span></label>
                        <input type="text" class="form-control" id="msgSubject" name="subject" placeholder="e.g. Branch cash replenishment request" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="msgBody">Message Content <span class="required">*</span></label>
                        <textarea class="form-control" id="msgBody" name="message" rows="5" placeholder="Detail your operational inquiry or request..." required></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-full" style="justify-content:center;">
                        <i class='bx bx-send'></i> Send to Admin
                    </button>
                </form>
            </div>
        </div>

        <!-- Outbox History -->
        <div class="card reveal" style="padding:0;overflow:hidden;border:1px solid var(--border);">
            <div class="card-header" style="background:var(--bg-subtle);padding:16px 20px;display:flex;justify-content:space-between;align-items:center;">
                <h3 style="margin:0;font-size:15px;font-weight:600;">Sent Messages History</h3>
                <span class="badge badge-outline" style="font-size:11px;"><?= count($myMessages) ?> Sent</span>
            </div>
            <div class="card-body" style="padding:20px;">
                <?php if (empty($myMessages)): ?>
                <div style="padding:40px;text-align:center;color:var(--text-muted);">
                    No sent messages yet. Use the form on the left to reach out to the Admin.
                </div>
                <?php else: ?>
                <div style="display:flex;flex-direction:column;gap:14px;">
                    <?php foreach ($myMessages as $m): ?>
                    <div style="background:var(--bg-subtle);border:1px solid var(--border);border-radius:var(--r);padding:16px;">
                        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                            <strong style="font-size:14px;color:var(--text-primary);"><?= clean($m['subject']) ?></strong>
                            <span class="badge <?= $m['is_read'] ? 'badge-success' : 'badge-outline' ?>" style="font-size:10px;">
                                <?= $m['is_read'] ? 'Read by Admin' : 'Delivered' ?>
                            </span>
                        </div>
                        <p style="font-size:13px;color:var(--text-secondary);margin:0 0 8px;line-height:1.5;">
                            <?= nl2br(clean($m['message'])) ?>
                        </p>
                        <div style="font-size:11px;color:var(--text-muted);">
                            Sent <?= time_ago($m['created_at']) ?> · <?= date('M d, Y H:i', strtotime($m['created_at'])) ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── TAB: STAFF PROFILE ─────────────────────────────────── -->
    <?php elseif ($tab === 'profile'): ?>
    
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;">
        <div>
            <h1>Staff Officer Profile</h1>
            <p>Your institutional credentials, branch assignment, and account security.</p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns:minmax(320px, 420px) 1fr;gap:24px;align-items:start;">
        
        <!-- Profile Overview Card -->
        <div class="card reveal" style="padding:0;overflow:hidden;border:1px solid var(--border);">
            <div class="card-header" style="background:var(--bg-subtle);padding:16px 20px;">
                <h3 style="margin:0;font-size:15px;font-weight:600;">Officer Credentials</h3>
            </div>
            <div class="card-body" style="padding:22px 20px;">
                <div style="text-align:center;margin-bottom:20px;">
                    <div class="avatar avatar-xl" style="margin:0 auto 12px;font-size:24px;background:var(--accent);color:var(--accent-fg);">
                        <?= strtoupper(substr($staff['first_name'],0,1) . substr($staff['last_name'],0,1)) ?>
                    </div>
                    <h3 style="font-size:17px;font-weight:700;margin:0 0 4px;"><?= clean($staff['first_name'] . ' ' . $staff['last_name']) ?></h3>
                    <div style="font-size:13px;color:var(--text-muted);"><?= ucfirst($staff['role'] ?? 'Officer') ?> · <?= clean($staff['department'] ?? 'Operations') ?></div>
                </div>

                <div style="display:flex;flex-direction:column;gap:12px;font-size:13px;border-top:1px solid var(--border);padding-top:16px;">
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">Staff ID:</span>
                        <span class="mono" style="font-weight:600;"><?= $staff['staff_id'] ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">Username:</span>
                        <span class="mono" style="font-weight:600;"><?= clean($staff['username']) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">Official Email:</span>
                        <span style="font-weight:500;"><?= clean($staff['email']) ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">Phone Contact:</span>
                        <span style="font-weight:500;"><?= clean($staff['phone'] ?? 'N/A') ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;">
                        <span style="color:var(--text-muted);">Joined On:</span>
                        <span style="font-weight:500;"><?= date('F d, Y', strtotime($staff['join_date'] ?? $staff['created_at'])) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Security / Password Update -->
        <div class="card reveal" style="padding:0;overflow:hidden;border:1px solid var(--border);">
            <div class="card-header" style="background:var(--bg-subtle);padding:16px 20px;">
                <h3 style="margin:0;font-size:15px;font-weight:600;">Update Password</h3>
            </div>
            <div class="card-body" style="padding:22px 20px;">
                <form method="POST">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="change_password">

                    <div class="form-group">
                        <label class="form-label" for="current_password">Current Password <span class="required">*</span></label>
                        <input type="password" class="form-control" id="current_password" name="current_password" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="new_password">New Password <span class="required">*</span></label>
                        <input type="password" class="form-control" id="new_password" name="new_password" minlength="6" required>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:4px;">Minimum 6 characters.</div>
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="confirm_password">Confirm New Password <span class="required">*</span></label>
                        <input type="password" class="form-control" id="confirm_password" name="confirm_password" minlength="6" required>
                    </div>

                    <button type="submit" class="btn btn-primary" style="margin-top:8px;">
                        <i class='bx bx-lock-alt'></i> Save New Password
                    </button>
                </form>
            </div>
        </div>
    </div>

    <?php endif; ?>

</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>
