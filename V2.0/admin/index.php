<?php
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/language.php';
require_once __DIR__ . '/../includes/functions.php';
require_admin();

$tab = $_GET['tab'] ?? 'overview';

/* ---- Actions ---- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'toggle_customer') {
        $cid = (int)$_POST['customer_id'];
        $stmt = $pdo->prepare("SELECT IsActive FROM CUSTOMER WHERE CustomerID = ?");
        $stmt->execute([$cid]);
        $active = $stmt->fetchColumn();
        $new = $active ? 0 : 1;
        $pdo->prepare("UPDATE CUSTOMER SET IsActive = ? WHERE CustomerID = ?")->execute([$new, $cid]);
        notify($cid, $new ? 'Account Reactivated' : 'Account Deactivated',
               $new ? 'Your account has been reactivated. Welcome back!' : 'Your account has been deactivated by the bank. Contact support for details.',
               $new ? 'success' : 'danger');
        header('Location: ' . BASE_URL . '/admin/index.php?tab=customers'); exit;
    }

    if ($action === 'toggle_staff') {
        $sid = (int)$_POST['staff_id'];
        $pdo->prepare("UPDATE STAFF SET is_active = NOT is_active WHERE staff_id = ?")->execute([$sid]);
        header('Location: ' . BASE_URL . '/admin/index.php?tab=staff'); exit;
    }

    if ($action === 'add_staff') {
        $fn = trim($_POST['first_name']); $ln = trim($_POST['last_name']);
        $email = trim($_POST['email']); $username = trim($_POST['username']);
        $pass = $_POST['password']; $role = $_POST['role'];
        $stmt = $pdo->prepare("INSERT INTO STAFF (first_name,last_name,email,username,password_hash,role,join_date,is_active) VALUES (?,?,?,?,?,?,CURDATE(),1)");
        $stmt->execute([$fn,$ln,$email,$username,password_hash($pass, PASSWORD_BCRYPT),$role]);
        header('Location: ' . BASE_URL . '/admin/index.php?tab=staff'); exit;
    }

    if ($action === 'delete_staff') {
        $sid = (int)$_POST['staff_id'];
        $pdo->prepare("DELETE FROM STAFF WHERE staff_id = ?")->execute([$sid]);
        header('Location: ' . BASE_URL . '/admin/index.php?tab=staff'); exit;
    }

    if ($action === 'broadcast') {
        $title = trim($_POST['title']); $message = trim($_POST['message']); $type = $_POST['type'];
        $ids = $pdo->query("SELECT CustomerID FROM CUSTOMER WHERE IsActive = 1")->fetchAll(PDO::FETCH_COLUMN);
        $stmt = $pdo->prepare("INSERT INTO NOTIFICATIONS (customer_id,title,message,type) VALUES (?,?,?,?)");
        foreach ($ids as $cid) $stmt->execute([$cid, $title, $message, $type]);
        flash('page', 'Broadcast sent to ' . count($ids) . ' customers.', 'success');
        header('Location: ' . BASE_URL . '/admin/index.php?tab=broadcast'); exit;
    }

    if ($action === 'reply_message') {
        $mid = (int)$_POST['message_id']; $reply = trim($_POST['admin_reply']);
        $decision = $_POST['decision'] ?? 'read';
        $pdo->prepare("UPDATE STAFF_MESSAGES SET admin_reply=?, status=?, replied_by=?, replied_at=NOW() WHERE message_id=?")
            ->execute([$reply, $decision, $_SESSION['admin_id'], $mid]);
        header('Location: ' . BASE_URL . '/admin/index.php?tab=staff_messages'); exit;
    }

    if ($action === 'reactivation_decide') {
        $rid = (int)$_POST['request_id'];
        $decision = $_POST['decision'] === 'approved' ? 'approved' : 'rejected';
        $reply = trim($_POST['admin_reply'] ?? '');
        $eta = trim($_POST['eta'] ?? '');
        $stmt = $pdo->prepare("SELECT * FROM REACTIVATION_REQUESTS WHERE request_id = ?");
        $stmt->execute([$rid]);
        $req = $stmt->fetch();
        if ($req) {
            $pdo->prepare("UPDATE REACTIVATION_REQUESTS SET status=?, admin_reply=?, estimated_timeframe=?, reviewed_by=?, reviewed_at=NOW() WHERE request_id=?")
                ->execute([$decision, $reply, $eta, $_SESSION['admin_id'], $rid]);
            if ($decision === 'approved') {
                $pdo->prepare("UPDATE CUSTOMER SET IsActive = 1 WHERE CustomerID = ?")->execute([$req['customer_id']]);
            }
            notify($req['customer_id'], 'Reactivation ' . ucfirst($decision), $reply ?: ('Your reactivation request was ' . $decision . '.'), $decision==='approved'?'success':'danger');
        }
        header('Location: ' . BASE_URL . '/admin/index.php?tab=reactivations'); exit;
    }
}

/* ---- Stats for overview ---- */
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM CUSTOMER")->fetchColumn();
$totalReserve = $pdo->query("SELECT SUM(AvailableBalance) FROM ACCOUNT")->fetchColumn() ?: 0;
$totalTxns = $pdo->query("SELECT COUNT(*) FROM TRANSACTION")->fetchColumn();
$totalStaff = $pdo->query("SELECT COUNT(*) FROM STAFF WHERE is_active=1")->fetchColumn();

$last7 = $pdo->query("SELECT DATE(TransactionDate) d, COUNT(*) c FROM TRANSACTION WHERE TransactionDate >= CURDATE() - INTERVAL 6 DAY GROUP BY DATE(TransactionDate)")->fetchAll();
$last7map = []; foreach ($last7 as $r) $last7map[$r['d']] = $r['c'];
$dailyLabels = []; $dailyData = [];
for ($i=6;$i>=0;$i--) { $d = date('Y-m-d', strtotime("-$i day")); $dailyLabels[] = date('D',strtotime($d)); $dailyData[] = (int)($last7map[$d] ?? 0); }

$last6m = $pdo->query("SELECT DATE_FORMAT(TransactionDate,'%Y-%m') m,
    SUM(CASE WHEN TransactionTypeID=1 THEN TransactionAmount ELSE 0 END) dep,
    SUM(CASE WHEN TransactionTypeID=2 THEN TransactionAmount ELSE 0 END) wd
    FROM TRANSACTION WHERE TransactionDate >= CURDATE() - INTERVAL 6 MONTH GROUP BY m")->fetchAll();
$monthMap = []; foreach ($last6m as $r) $monthMap[$r['m']] = $r;
$monthLabels = []; $depData = []; $wdData = [];
for ($i=5;$i>=0;$i--) {
    $m = date('Y-m', strtotime("-$i month"));
    $monthLabels[] = date('M', strtotime($m.'-01'));
    $depData[] = (float)($monthMap[$m]['dep'] ?? 0);
    $wdData[] = (float)($monthMap[$m]['wd'] ?? 0);
}

$topCustomers = $pdo->query("SELECT c.CustomerID, c.FirstName, c.LastName, SUM(t.TransactionAmount) vol
    FROM TRANSACTION t JOIN CUSTOMER c ON c.CustomerID = t.FromCustomerID
    GROUP BY c.CustomerID ORDER BY vol DESC LIMIT 5")->fetchAll();

$recentTxns = $pdo->query("SELECT t.*, tt.TypeName FROM TRANSACTION t JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID=tt.TransactionTypeID ORDER BY t.TransactionDate DESC LIMIT 8")->fetchAll();

$customers = $pdo->query("SELECT * FROM CUSTOMER ORDER BY CreatedAt DESC")->fetchAll();
$staffMembers = $pdo->query("SELECT * FROM STAFF ORDER BY created_at DESC")->fetchAll();
$staffMsgs = $pdo->query("SELECT sm.*, s.first_name, s.last_name FROM STAFF_MESSAGES sm JOIN STAFF s ON sm.staff_id=s.staff_id ORDER BY FIELD(sm.status,'pending','read','approved','rejected'), sm.created_at DESC")->fetchAll();
$reactivations = $pdo->query("SELECT r.*, c.FirstName, c.LastName, c.Email FROM REACTIVATION_REQUESTS r JOIN CUSTOMER c ON r.customer_id=c.CustomerID WHERE r.status='pending' ORDER BY r.created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin · Asha Bank</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script>const BASE_URL = "<?= BASE_URL ?>"; window.CSRF_TOKEN = "<?= csrf_token() ?>";</script>
</head>
<body>
<div class="app-shell">
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <aside class="sidebar" id="sidebar">
    <div class="brand"><div class="brand-mark">A</div><div class="brand-name">Asha<span>Admin</span></div></div>
    <div class="nav-group">
      <a class="nav-item <?= $tab==='overview'?'active':'' ?>" href="?tab=overview">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
        Overview
      </a>
      <a class="nav-item <?= $tab==='customers'?'active':'' ?>" href="?tab=customers">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
        Customers
      </a>
      <a class="nav-item <?= $tab==='staff'?'active':'' ?>" href="?tab=staff">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
        Staff
      </a>
      <a class="nav-item <?= $tab==='staff_messages'?'active':'' ?>" href="?tab=staff_messages">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/></svg>
        Staff Messages
      </a>
      <a class="nav-item <?= $tab==='reactivations'?'active':'' ?>" href="?tab=reactivations">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6"/><path d="M3.51 15a9 9 0 1 0 2.13-9.36L1 10"/></svg>
        Reactivations <?php if(count($reactivations)>0):?><span class="nav-badge"><?=count($reactivations)?></span><?php endif; ?>
      </a>
      <a class="nav-item <?= $tab==='broadcast'?'active':'' ?>" href="?tab=broadcast">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/></svg>
        Broadcast
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
      <div class="topbar-crumb">Admin / <?= ucfirst(str_replace('_',' ',$tab)) ?></div>
      <div class="topbar-spacer"></div>
      <div class="user-chip"><div class="avatar">SA</div><span class="text-sm" style="font-weight:600;">Superadmin</span></div>
    </header>
    <div class="content">

<?php $f = flash('page'); if ($f): ?>
  <div class="badge badge-<?= $f['type'] ?>" style="display:block;padding:12px 14px;margin-bottom:18px;"><?= clean($f['msg']) ?></div>
<?php endif; ?>

<?php if ($tab === 'overview'): ?>
  <div class="page-head"><div><h1>Bank Overview</h1><div class="sub">Real-time snapshot across all branches.</div></div></div>
  <div class="grid grid-4">
    <div class="card stat-card"><div class="icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/></svg></div><div class="stat-label">Total Customers</div><div class="stat-value"><?= number_format($totalCustomers) ?></div></div>
    <div class="card stat-card"><div class="icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg></div><div class="stat-label">Total Reserve</div><div class="stat-value mono"><?= currency($totalReserve) ?></div></div>
    <div class="card stat-card"><div class="icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg></div><div class="stat-label">Total Transactions</div><div class="stat-value"><?= number_format($totalTxns) ?></div></div>
    <div class="card stat-card"><div class="icon-wrap"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="7" width="20" height="14" rx="2"/></svg></div><div class="stat-label">Active Staff</div><div class="stat-value"><?= $totalStaff ?></div></div>
  </div>

  <div class="grid grid-main mt-16">
    <div class="card">
      <div class="card-head"><span class="card-title">Daily Transactions</span><span class="text-dim text-xs">Last 7 days</span></div>
      <div class="chart-canvas-wrap"><canvas id="dailyChart" style="width:100%;height:100%;"></canvas></div>
    </div>
    <div class="card">
      <div class="card-head"><span class="card-title">Top Customers</span></div>
      <?php foreach ($topCustomers as $tc): ?>
        <div class="flex justify-between items-center" style="padding:10px 0;border-bottom:1px solid var(--border-soft);">
          <div class="flex items-center gap-12">
            <div class="avatar"><?= strtoupper(substr($tc['FirstName'],0,1).substr($tc['LastName'],0,1)) ?></div>
            <span class="text-sm"><?= clean($tc['FirstName'].' '.$tc['LastName']) ?></span>
          </div>
          <span class="mono text-sm" style="font-weight:600;"><?= currency($tc['vol']) ?></span>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <div class="grid grid-main mt-16">
    <div class="card">
      <div class="card-head"><span class="card-title">Deposits vs Withdrawals</span><span class="text-dim text-xs">Last 6 months</span></div>
      <div class="chart-canvas-wrap"><canvas id="monthChart" style="width:100%;height:100%;"></canvas></div>
      <div class="flex gap-16 mt-16" style="justify-content:center;">
        <div class="flex items-center gap-8 text-xs text-dim"><span style="width:8px;height:8px;border-radius:50%;background:var(--accent);display:inline-block;"></span> Deposits</div>
        <div class="flex items-center gap-8 text-xs text-dim"><span style="width:8px;height:8px;border-radius:50%;background:var(--info);display:inline-block;"></span> Withdrawals</div>
      </div>
    </div>
    <div class="card">
      <div class="card-head"><span class="card-title">Recent Transactions</span></div>
      <?php foreach (array_slice($recentTxns,0,6) as $tx): ?>
        <div class="txn-row">
          <div class="txn-icon credit"><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/></svg></div>
          <div class="txn-info"><div class="txn-title"><?= clean($tx['TypeName']) ?></div><div class="txn-sub"><?= clean($tx['ReferenceNumber']) ?> · <?= time_ago($tx['TransactionDate']) ?></div></div>
          <div class="txn-amount credit mono"><?= currency($tx['TransactionAmount']) ?></div>
        </div>
      <?php endforeach; ?>
    </div>
  </div>

  <script>
    drawBarChart('dailyChart', <?= json_encode($dailyLabels) ?>, [{ data: <?= json_encode($dailyData) ?>, color: '#d4a657' }]);
    drawBarChart('monthChart', <?= json_encode($monthLabels) ?>, [
      { data: <?= json_encode($depData) ?>, color: '#d4a657' },
      { data: <?= json_encode($wdData) ?>, color: '#60a5fa' }
    ]);
  </script>

<?php elseif ($tab === 'customers'): ?>
  <div class="page-head"><div><h1>Customers</h1><div class="sub">Manage customer status and access.</div></div></div>
  <div class="card">
    <div class="table-wrap"><table class="data-table">
      <thead><tr><th>Name</th><th>Email</th><th>City</th><th>Joined</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php foreach ($customers as $c): ?>
        <tr>
          <td><?= clean($c['FirstName'].' '.$c['LastName']) ?></td>
          <td><?= clean($c['Email']) ?></td>
          <td><?= clean($c['City']) ?></td>
          <td><?= date('M j, Y', strtotime($c['CreatedAt'])) ?></td>
          <td><span class="badge <?= $c['IsActive']?'badge-success':'badge-danger' ?>"><?= $c['IsActive']?'Active':'Inactive' ?></span></td>
          <td>
            <form method="POST" onsubmit="return confirm('<?= $c['IsActive']?'Deactivate':'Activate' ?> this customer?');">
              <?= csrf_field() ?><input type="hidden" name="action" value="toggle_customer"><input type="hidden" name="customer_id" value="<?= $c['CustomerID'] ?>">
              <button class="btn btn-sm <?= $c['IsActive']?'btn-danger':'btn-secondary' ?>"><?= $c['IsActive']?'Deactivate':'Activate' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </div>

<?php elseif ($tab === 'staff'): ?>
  <div class="page-head"><div><h1>Staff Management</h1><div class="sub">Add, toggle, or remove staff accounts.</div></div></div>
  <div class="grid grid-main">
    <div class="card">
      <div class="card-head"><span class="card-title">All Staff</span></div>
      <div class="table-wrap"><table class="data-table">
        <thead><tr><th>Name</th><th>Role</th><th>Status</th><th>Action</th></tr></thead>
        <tbody>
        <?php foreach ($staffMembers as $s): ?>
          <tr>
            <td><?= clean($s['first_name'].' '.$s['last_name']) ?><br><span class="text-dim text-xs"><?= clean($s['email']) ?></span></td>
            <td><span class="badge badge-info"><?= ucfirst($s['role']) ?></span></td>
            <td><span class="badge <?= $s['is_active']?'badge-success':'badge-danger' ?>"><?= $s['is_active']?'Active':'Inactive' ?></span></td>
            <td>
              <div class="flex gap-8">
                <form method="POST"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_staff"><input type="hidden" name="staff_id" value="<?= $s['staff_id'] ?>"><button class="btn btn-sm btn-secondary">Toggle</button></form>
                <form method="POST" onsubmit="return confirm('Delete this staff member permanently?');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_staff"><input type="hidden" name="staff_id" value="<?= $s['staff_id'] ?>"><button class="btn btn-sm btn-danger">Delete</button></form>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </div>
    <div class="card">
      <div class="card-head"><span class="card-title">Add Staff</span></div>
      <form method="POST">
        <?= csrf_field() ?><input type="hidden" name="action" value="add_staff">
        <div class="grid grid-2" style="gap:12px;">
          <div class="field"><label>First Name</label><input class="input" name="first_name" required></div>
          <div class="field"><label>Last Name</label><input class="input" name="last_name" required></div>
        </div>
        <div class="field"><label>Email</label><input class="input" type="email" name="email" required></div>
        <div class="field"><label>Username</label><input class="input" name="username" required></div>
        <div class="field"><label>Password</label><input class="input" type="password" name="password" required></div>
        <div class="field"><label>Role</label>
          <select class="input" name="role"><option value="officer">Officer</option><option value="manager">Manager</option><option value="teller">Teller</option><option value="support">Support</option></select>
        </div>
        <button class="btn btn-primary btn-block">Add Staff Member</button>
      </form>
    </div>
  </div>

<?php elseif ($tab === 'staff_messages'): ?>
  <div class="page-head"><div><h1>Staff Messages</h1><div class="sub">Requests, reports, and suggestions from staff.</div></div></div>
  <div class="card">
    <?php foreach ($staffMsgs as $m): ?>
      <div style="padding:16px 0;border-bottom:1px solid var(--border-soft);">
        <div class="flex justify-between items-center">
          <div><span class="badge badge-info"><?= ucfirst($m['type']) ?></span> <strong style="margin-left:8px;"><?= clean($m['subject']) ?></strong></div>
          <span class="badge <?= $m['status']==='approved'?'badge-success':($m['status']==='rejected'?'badge-danger':'badge-warning') ?>"><?= ucfirst($m['status']) ?></span>
        </div>
        <div class="text-dim text-xs mt-8">From <?= clean($m['first_name'].' '.$m['last_name']) ?> · <?= time_ago($m['created_at']) ?></div>
        <p class="text-sm mt-8"><?= clean($m['message']) ?></p>
        <?php if (!$m['admin_reply']): ?>
        <button class="btn btn-sm btn-secondary mt-8" data-reply-toggle="msgreply-<?= $m['message_id'] ?>">Reply</button>
        <form method="POST" id="msgreply-<?= $m['message_id'] ?>" style="display:none;margin-top:10px;">
          <?= csrf_field() ?><input type="hidden" name="action" value="reply_message"><input type="hidden" name="message_id" value="<?= $m['message_id'] ?>">
          <textarea class="input" name="admin_reply" required></textarea>
          <div class="flex gap-8 mt-8">
            <button class="btn btn-sm btn-secondary" name="decision" value="approved">Approve & Reply</button>
            <button class="btn btn-sm btn-danger" name="decision" value="rejected">Reject & Reply</button>
          </div>
        </form>
        <?php else: ?>
          <div class="card" style="background:var(--surface-2);padding:10px 12px;margin-top:10px;"><?= clean($m['admin_reply']) ?></div>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>

<?php elseif ($tab === 'reactivations'): ?>
  <div class="page-head"><div><h1>Reactivation Requests</h1><div class="sub">Review and approve account reactivations.</div></div></div>
  <div class="card">
    <?php if (empty($reactivations)): ?><div class="empty-state">No pending requests.</div><?php endif; ?>
    <?php foreach ($reactivations as $r): ?>
      <div style="padding:16px 0;border-bottom:1px solid var(--border-soft);">
        <strong><?= clean($r['FirstName'].' '.$r['LastName']) ?></strong> <span class="text-dim text-xs"><?= clean($r['Email']) ?> · <?= time_ago($r['created_at']) ?></span>
        <p class="text-sm mt-8"><?= clean($r['reason']) ?></p>
        <form method="POST" class="mt-16">
          <?= csrf_field() ?><input type="hidden" name="action" value="reactivation_decide"><input type="hidden" name="request_id" value="<?= $r['request_id'] ?>">
          <div class="grid grid-2" style="gap:12px;">
            <div class="field"><label>Reply</label><input class="input" name="admin_reply" placeholder="Message to customer"></div>
            <div class="field"><label>ETA (optional)</label><input class="input" name="eta" placeholder="e.g. 24 hours"></div>
          </div>
          <div class="flex gap-8">
            <button class="btn btn-secondary" name="decision" value="approved">Approve</button>
            <button class="btn btn-danger" name="decision" value="rejected">Reject</button>
          </div>
        </form>
      </div>
    <?php endforeach; ?>
  </div>

<?php elseif ($tab === 'broadcast'): ?>
  <div class="page-head"><div><h1>Broadcast Notification</h1><div class="sub">Send a message to all active customers.</div></div></div>
  <div class="card" style="max-width:520px;">
    <form method="POST">
      <?= csrf_field() ?><input type="hidden" name="action" value="broadcast">
      <div class="field"><label>Type</label>
        <select class="input" name="type"><option value="info">Info</option><option value="success">Success</option><option value="warning">Warning</option><option value="danger">Danger</option></select>
      </div>
      <div class="field"><label>Title</label><input class="input" name="title" required></div>
      <div class="field"><label>Message</label><textarea class="input" name="message" required></textarea></div>
      <button class="btn btn-primary btn-block" data-confirm="Send this notification to all active customers?">Broadcast</button>
    </form>
  </div>
<?php endif; ?>

    </div>
  </div>
</div>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>
</body>
</html>
