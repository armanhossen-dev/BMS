<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/language.php';
require_once __DIR__ . '/../includes/functions.php';
require_staff();

$staffId = $_SESSION['staff_id'];
$stmt = $pdo->prepare("SELECT * FROM STAFF WHERE staff_id = ?");
$stmt->execute([$staffId]);
$staff = $stmt->fetch();

$tab = $_GET['tab'] ?? 'overview';

/* ---- Actions ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'kyc_decide') {
        $kycId = (int)$_POST['kyc_id'];
        $decision = $_POST['decision'] === 'verified' ? 'verified' : 'rejected';
        $reason = trim($_POST['reason'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM KYC_VERIFICATIONS WHERE kyc_id = ?");
        $stmt->execute([$kycId]);
        $kyc = $stmt->fetch();
        if ($kyc) {
            $pdo->prepare("UPDATE KYC_VERIFICATIONS SET status=?, rejection_reason=?, verified_by=?, verified_at=NOW() WHERE kyc_id=?")
                ->execute([$decision, $decision==='rejected'?$reason:null, $staffId, $kycId]);
            notify($kyc['customer_id'], 'KYC ' . ucfirst($decision), $decision==='verified' ? 'Your KYC has been verified. All features are unlocked.' : ('Your KYC was rejected. Reason: ' . $reason), $decision==='verified'?'success':'danger');
        }
        flash('page','KYC request ' . $decision . '.', 'success');
        header('Location: ' . BASE_URL . '/staff/dashboard.php?tab=kyc'); exit;
    }

    if ($action === 'feedback_reply') {
        $fid = (int)$_POST['feedback_id'];
        $reply = trim($_POST['staff_reply'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM FEEDBACK WHERE feedback_id = ?");
        $stmt->execute([$fid]);
        $fb = $stmt->fetch();
        if ($fb && $reply !== '') {
            $pdo->prepare("UPDATE FEEDBACK SET staff_reply=?, status='replied', replied_by=?, replied_at=NOW() WHERE feedback_id=?")
                ->execute([$reply, $staffId, $fid]);
            notify($fb['customer_id'], 'Reply to your feedback', 'Staff replied to "' . $fb['subject'] . '".', 'info');
        }
        header('Location: ' . BASE_URL . '/staff/dashboard.php?tab=feedback'); exit;
    }

    if ($action === 'send_message') {
        $subject = trim($_POST['subject'] ?? '');
        $message = trim($_POST['message'] ?? '');
        $type = $_POST['type'] ?? 'request';
        if ($subject && $message) {
            $pdo->prepare("INSERT INTO STAFF_MESSAGES (staff_id, subject, message, type) VALUES (?,?,?,?)")
                ->execute([$staffId, $subject, $message, $type]);
        }
        header('Location: ' . BASE_URL . '/staff/dashboard.php?tab=messages'); exit;
    }
}

/* ---- Stats ---- */
$pendingKyc = $pdo->query("SELECT COUNT(*) FROM KYC_VERIFICATIONS WHERE status='pending'")->fetchColumn();
$todayTxns = $pdo->query("SELECT COUNT(*) FROM TRANSACTION WHERE DATE(TransactionDate) = CURDATE()")->fetchColumn();
$pendingFeedback = $pdo->query("SELECT COUNT(*) FROM FEEDBACK WHERE status='pending'")->fetchColumn();

$kycList = $pdo->query("SELECT k.*, c.FirstName, c.LastName, c.Email FROM KYC_VERIFICATIONS k
                         JOIN CUSTOMER c ON k.customer_id = c.CustomerID
                         WHERE k.status = 'pending' ORDER BY k.submitted_at DESC")->fetchAll();

$feedbackList = $pdo->query("SELECT f.*, c.FirstName, c.LastName FROM FEEDBACK f
                              JOIN CUSTOMER c ON f.customer_id = c.CustomerID
                              ORDER BY FIELD(f.status,'pending','read','replied','resolved'), f.created_at DESC LIMIT 30")->fetchAll();

$stmt = $pdo->prepare("SELECT * FROM STAFF_MESSAGES WHERE staff_id = ? ORDER BY created_at DESC");
$stmt->execute([$staffId]);
$myMessages = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Staff Portal · Asha Bank</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script>const BASE_URL = "<?= BASE_URL ?>"; window.CSRF_TOKEN = "<?= csrf_token() ?>";</script>
</head>
<body>
<div class="app-shell">
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <aside class="sidebar" id="sidebar">
    <div class="brand"><div class="brand-mark">A</div><div class="brand-name">Asha<span>Staff</span></div></div>
    <div class="nav-group">
      <a class="nav-item <?= $tab==='overview'?'active':'' ?>" href="?tab=overview">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
        Overview
      </a>
      <a class="nav-item <?= $tab==='kyc'?'active':'' ?>" href="?tab=kyc">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        KYC Review <?php if($pendingKyc>0):?><span class="nav-badge"><?=$pendingKyc?></span><?php endif; ?>
      </a>
      <a class="nav-item <?= $tab==='feedback'?'active':'' ?>" href="?tab=feedback">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        Feedback <?php if($pendingFeedback>0):?><span class="nav-badge"><?=$pendingFeedback?></span><?php endif; ?>
      </a>
      <a class="nav-item <?= $tab==='messages'?'active':'' ?>" href="?tab=messages">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Messages to Admin
      </a>
    </div>
    <div class="nav-group" style="margin-top:auto;margin-bottom:0;">
      <a class="nav-item" href="<?= BASE_URL ?>/logout.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        Logout
      </a>
    </div>
  </aside>

  <div class="main-col">
    <header class="topbar">
      <button class="icon-btn" id="menuToggle" aria-label="Menu"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg></button>
      <div class="topbar-crumb">Staff Portal / <?= ucfirst($tab) ?></div>
      <div class="topbar-spacer"></div>
      <div class="user-chip"><div class="avatar"><?= strtoupper(substr($staff['first_name'],0,1).substr($staff['last_name'],0,1)) ?></div><span class="text-sm" style="font-weight:600;"><?= clean($staff['first_name']) ?></span></div>
    </header>
    <div class="content">

<?php $f = flash('page'); if ($f): ?>
  <div class="badge badge-<?= $f['type'] ?>" style="display:block;padding:12px 14px;margin-bottom:18px;"><?= clean($f['msg']) ?></div>
<?php endif; ?>

<?php if ($tab === 'overview'): ?>
  <div class="page-head"><div><h1>Welcome, <?= clean($staff['first_name']) ?></h1><div class="sub">Here's what needs your attention today.</div></div></div>
  <div class="grid grid-3">
    <div class="card stat-card">
      <div class="icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg></div>
      <div class="stat-label">Pending KYC</div><div class="stat-value"><?= $pendingKyc ?></div>
    </div>
    <div class="card stat-card">
      <div class="icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div>
      <div class="stat-label">Today's Transactions</div><div class="stat-value"><?= $todayTxns ?></div>
    </div>
    <div class="card stat-card">
      <div class="icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7"/></svg></div>
      <div class="stat-label">Pending Feedback</div><div class="stat-value"><?= $pendingFeedback ?></div>
    </div>
  </div>

<?php elseif ($tab === 'kyc'): ?>
  <div class="page-head"><div><h1>KYC Review</h1><div class="sub">Verify or reject pending customer submissions.</div></div></div>
  <div class="card">
    <?php if (empty($kycList)): ?><div class="empty-state">No pending KYC requests 🎉</div><?php else: ?>
    <div class="table-wrap"><table class="data-table">
      <thead><tr><th>Customer</th><th>NID</th><th>Phone</th><th>Submitted</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($kycList as $k): ?>
        <tr>
          <td><?= clean($k['FirstName'].' '.$k['LastName']) ?><br><span class="text-dim text-xs"><?= clean($k['Email']) ?></span></td>
          <td class="mono"><?= clean($k['nid_number']) ?></td>
          <td><?= clean($k['phone_number']) ?></td>
          <td><?= time_ago($k['submitted_at']) ?></td>
          <td>
            <div class="flex gap-8">
              <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="kyc_decide"><input type="hidden" name="kyc_id" value="<?= $k['kyc_id'] ?>"><input type="hidden" name="decision" value="verified">
                <button class="btn btn-sm btn-secondary" data-confirm="Verify this customer's KYC?">Verify</button></form>
              <button class="btn btn-sm btn-danger" data-modal-open="reject-<?= $k['kyc_id'] ?>">Reject</button>
            </div>
          </td>
        </tr>
        <div class="modal-overlay" id="reject-<?= $k['kyc_id'] ?>">
          <div class="modal">
            <div class="modal-head"><h3 style="font-size:16px;">Reject KYC</h3><span class="modal-close" data-modal-close>✕</span></div>
            <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="kyc_decide"><input type="hidden" name="kyc_id" value="<?= $k['kyc_id'] ?>"><input type="hidden" name="decision" value="rejected">
              <div class="field"><label>Reason</label><textarea class="input" name="reason" required></textarea></div>
              <button class="btn btn-danger btn-block">Confirm Rejection</button>
            </form>
          </div>
        </div>
      <?php endforeach; ?>
      </tbody>
    </table></div>
    <?php endif; ?>
  </div>

<?php elseif ($tab === 'feedback'): ?>
  <div class="page-head"><div><h1>Customer Feedback</h1><div class="sub">Respond to feedback, complaints, and issues.</div></div></div>
  <div class="card">
    <?php foreach ($feedbackList as $f): ?>
      <div style="padding:16px 0;border-bottom:1px solid var(--border-soft);">
        <div class="flex justify-between items-center">
          <div>
            <span class="badge badge-info"><?= ucfirst($f['type']) ?></span>
            <strong style="margin-left:8px;"><?= clean($f['subject']) ?></strong>
          </div>
          <span class="badge <?= $f['status']==='resolved'?'badge-success':($f['status']==='pending'?'badge-warning':'badge-muted') ?>"><?= ucfirst($f['status']) ?></span>
        </div>
        <div class="text-dim text-xs mt-8"><?= clean($f['FirstName'].' '.$f['LastName']) ?> · <?= time_ago($f['created_at']) ?></div>
        <p class="text-sm mt-8"><?= clean($f['message']) ?></p>
        <?php if ($f['staff_reply']): ?>
          <div class="card" style="background:var(--surface-2);padding:10px 12px;margin-top:10px;"><span class="text-xs text-dim">Reply sent:</span> <?= clean($f['staff_reply']) ?></div>
        <?php else: ?>
          <button class="btn btn-sm btn-secondary mt-8" data-reply-toggle="reply-<?= $f['feedback_id'] ?>">Reply</button>
          <form method="POST" id="reply-<?= $f['feedback_id'] ?>" style="display:none;margin-top:10px;">
            <?= csrf_field() ?><input type="hidden" name="action" value="feedback_reply"><input type="hidden" name="feedback_id" value="<?= $f['feedback_id'] ?>">
            <textarea class="input" name="staff_reply" placeholder="Type your reply..." required></textarea>
            <button class="btn btn-primary btn-sm mt-8">Send Reply</button>
          </form>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

<?php elseif ($tab === 'messages'): ?>
  <div class="page-head"><div><h1>Messages to Admin</h1><div class="sub">Send requests, reports, or suggestions to management.</div></div></div>
  <div class="grid grid-main">
    <div class="card">
      <form method="POST">
        <?= csrf_field() ?><input type="hidden" name="action" value="send_message">
        <div class="field"><label>Type</label>
          <select class="input" name="type"><option value="request">Request</option><option value="report">Report</option><option value="suggestion">Suggestion</option><option value="issue">Issue</option></select>
        </div>
        <div class="field"><label>Subject</label><input class="input" name="subject" required></div>
        <div class="field"><label>Message</label><textarea class="input" name="message" required></textarea></div>
        <button class="btn btn-primary btn-block">Send to Admin</button>
      </form>
    </div>
    <div class="card">
      <div class="card-head"><span class="card-title">Sent Messages</span></div>
      <?php foreach ($myMessages as $m): ?>
        <div style="padding:12px 0;border-bottom:1px solid var(--border-soft);">
          <div class="flex justify-between"><strong class="text-sm"><?= clean($m['subject']) ?></strong><span class="badge badge-muted"><?= ucfirst($m['status']) ?></span></div>
          <?php if ($m['admin_reply']): ?><div class="text-dim text-xs mt-8">Admin: <?= clean($m['admin_reply']) ?></div><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

    </div>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
