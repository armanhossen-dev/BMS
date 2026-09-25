<?php
require_once '../config/db.php';
requireAuth('admin');

$tab = sanitize($_GET['tab'] ?? 'dashboard');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 15;
$search = sanitize($_GET['q'] ?? '');

// ── Dashboard stats ─────────────────────────────────────────
$stats = [];
if ($tab === 'dashboard' || $tab === '') {
    $stats = [
        'customers'    => (int)$pdo->query("SELECT COUNT(*) FROM CUSTOMER WHERE IsActive=1")->fetchColumn(),
        'accounts'     => (int)$pdo->query("SELECT COUNT(*) FROM ACCOUNT WHERE AccountStatus='Active'")->fetchColumn(),
        'staff'        => (int)$pdo->query("SELECT COUNT(*) FROM STAFF WHERE is_active=1")->fetchColumn(),
        'total_balance'=> (float)$pdo->query("SELECT COALESCE(SUM(AvailableBalance),0) FROM ACCOUNT WHERE AccountStatus='Active'")->fetchColumn(),
        'txn_today'    => (int)$pdo->query("SELECT COUNT(*) FROM TRANSACTION WHERE DATE(TransactionDate)=CURDATE()")->fetchColumn(),
        'txn_vol_today'=> (float)$pdo->query("SELECT COALESCE(SUM(TransactionAmount),0) FROM TRANSACTION WHERE DATE(TransactionDate)=CURDATE()")->fetchColumn(),
        'pending_kyc'  => (int)$pdo->query("SELECT COUNT(*) FROM KYC_VERIFICATIONS WHERE status='pending'")->fetchColumn(),
        'branches'     => (int)$pdo->query("SELECT COUNT(*) FROM BRANCH")->fetchColumn(),
    ];
}

// ── Customers tab ───────────────────────────────────────────
$customers = [];
$totalCustomers = 0;
if ($tab === 'customers') {
    $where = $search ? "WHERE (c.FirstName LIKE ? OR c.LastName LIKE ? OR c.Email LIKE ? OR c.NationalID LIKE ?)" : "";
    $countSql = "SELECT COUNT(*) FROM CUSTOMER c $where";
    $params = $search ? ["%$search%","%$search%","%$search%","%$search%"] : [];
    $totalCustomers = (int)$pdo->prepare($countSql)->execute($params) ? 0 : 0;
    // Simpler approach:
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM CUSTOMER c $where");
    $countStmt->execute($params);
    $totalCustomers = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($totalCustomers / $perPage);
    $offset = ($page - 1) * $perPage;
    $sql = "SELECT c.*, a.AccountNumber, a.AvailableBalance, a.AccountStatus
            FROM CUSTOMER c
            LEFT JOIN ACCOUNT a ON c.CustomerID = a.CustomerID
            $where ORDER BY c.CustomerID DESC LIMIT $perPage OFFSET $offset";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $customers = $stmt->fetchAll();
}

// ── Transactions tab ────────────────────────────────────────
$transactions = [];
$totalTxns = 0;
if ($tab === 'transactions') {
    $where = $search ? "WHERE (t.ReferenceNumber LIKE ? OR CONCAT(c.FirstName,' ',c.LastName) LIKE ?)" : "";
    $params = $search ? ["%$search%","%$search%"] : [];
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM TRANSACTION t LEFT JOIN CUSTOMER c ON t.FromCustomerID=c.CustomerID $where");
    $countStmt->execute($params);
    $totalTxns = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($totalTxns / $perPage);
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare(
        "SELECT t.*, tt.TypeName,
                CONCAT(fc.FirstName,' ',fc.LastName) AS from_name,
                CONCAT(tc.FirstName,' ',tc.LastName) AS to_name
         FROM TRANSACTION t
         JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID = tt.TransactionTypeID
         LEFT JOIN CUSTOMER fc ON t.FromCustomerID = fc.CustomerID
         LEFT JOIN CUSTOMER tc ON t.ToCustomerID = tc.CustomerID
         $where ORDER BY t.TransactionDate DESC LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $transactions = $stmt->fetchAll();
}

// ── KYC tab ─────────────────────────────────────────────────
$kycList = [];
if ($tab === 'kyc') {
    $stmt = $pdo->prepare(
        "SELECT k.*, CONCAT(c.FirstName,' ',c.LastName) AS customer_name, c.Email
         FROM KYC_VERIFICATIONS k
         JOIN CUSTOMER c ON k.customer_id = c.CustomerID
         ORDER BY CASE k.status WHEN 'pending' THEN 0 ELSE 1 END, k.submitted_at DESC
         LIMIT 50"
    );
    $stmt->execute();
    $kycList = $stmt->fetchAll();
}

// ── Staff tab ────────────────────────────────────────────────
$staffList = [];
if ($tab === 'staff') {
    $stmt = $pdo->query("SELECT * FROM STAFF ORDER BY created_at DESC LIMIT 50");
    $staffList = $stmt->fetchAll();
}

// ── Reports ──────────────────────────────────────────────────
$reports = [];
if ($tab === 'reports') {
    $reports = [
        'monthly' => $pdo->query(
            "SELECT DATE_FORMAT(TransactionDate,'%Y-%m') AS month,
                    COUNT(*) AS count,
                    SUM(TransactionAmount) AS total
             FROM TRANSACTION WHERE TransactionDate >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
             GROUP BY month ORDER BY month DESC LIMIT 12"
        )->fetchAll(),
        'by_type' => $pdo->query(
            "SELECT tt.TypeName, COUNT(*) AS count, SUM(t.TransactionAmount) AS total
             FROM TRANSACTION t JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID=tt.TransactionTypeID
             GROUP BY tt.TypeName"
        )->fetchAll(),
    ];
}

// ── Handle KYC action ────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['kyc_action'])) {
    verifyCsrf();
    $kycId  = (int)$_POST['kyc_id'];
    $action = $_POST['kyc_action'] === 'approve' ? 'verified' : 'rejected';
    $note   = sanitize($_POST['admin_note'] ?? '');
    $pdo->prepare("UPDATE KYC_VERIFICATIONS SET status=?, admin_note=?, reviewed_at=NOW(), reviewed_by=? WHERE id=?")
        ->execute([$action, $note, $_SESSION['user_id'], $kycId]);
    // Notify customer
    $row = $pdo->query("SELECT customer_id FROM KYC_VERIFICATIONS WHERE id=$kycId")->fetch();
    if ($row) {
        sendNotification($pdo, $row['customer_id'], 'KYC ' . ucfirst($action),
            'Your KYC verification has been ' . $action . ($note ? '. Note: ' . $note : '') . '.',
            $action === 'verified' ? 'success' : 'danger');
    }
    setToast('KYC status updated to ' . $action . '.', 'success');
    redirect('index.php?tab=kyc');
}

// ── Handle customer toggle ───────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_customer'])) {
    verifyCsrf();
    $cid    = (int)$_POST['customer_id'];
    $active = (int)$_POST['active'];
    $pdo->prepare("UPDATE CUSTOMER SET IsActive=? WHERE CustomerID=?")->execute([$active, $cid]);
    setToast('Customer account ' . ($active ? 'activated' : 'deactivated') . '.', $active ? 'success' : 'warning');
    redirect('index.php?tab=customers');
}

// ── Handle broadcast notification ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['broadcast'])) {
    verifyCsrf();
    $title   = sanitize($_POST['notif_title'] ?? '');
    $message = sanitize($_POST['notif_message'] ?? '');
    $type    = sanitize($_POST['notif_type'] ?? 'info');
    if ($title && $message) {
        $customers = $pdo->query("SELECT CustomerID FROM CUSTOMER WHERE IsActive=1")->fetchAll(\PDO::FETCH_COLUMN);
        $stmt = $pdo->prepare("INSERT INTO NOTIFICATIONS (customer_id, title, message, type, created_at) VALUES (?, ?, ?, ?, NOW())");
        foreach ($customers as $cid) {
            $stmt->execute([$cid, $title, $message, $type]);
        }
        setToast('Notification sent to ' . count($customers) . ' customers.', 'success');
    }
    redirect('index.php?tab=notifications');
}

$pageTitle  = 'Admin Panel';
$activePage = 'dashboard';
include '../includes/header.php';
?>

<div class="page-content">

<!-- Page header -->
<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;">
    <div>
        <h1>Admin Dashboard</h1>
        <p>Monitor and manage all banking operations.</p>
    </div>
    <div style="font-size:13px;color:var(--ink-muted);">
        <?= date('l, d F Y') ?> &bull; <?= date('g:i A') ?>
    </div>
</div>

<!-- Tabs -->
<div class="tabs">
    <?php
    $tabs = [
        'dashboard'     => ['Dashboard',     'home'],
        'customers'     => ['Customers',     'users'],
        'transactions'  => ['Transactions',  'activity'],
        'accounts'      => ['Accounts',      'credit-card'],
        'staff'         => ['Staff',         'briefcase'],
        'kyc'           => ['KYC Requests',  'shield'],
        'notifications' => ['Notifications', 'bell'],
        'reports'       => ['Reports',       'bar-chart-2'],
    ];
    foreach ($tabs as $slug => [$label, $icon]):
        $isActive = ($tab === $slug) || ($slug === 'dashboard' && !$tab);
    ?>
    <a href="?tab=<?= $slug ?>" class="tab-link <?= $isActive ? 'active' : '' ?>">
        <?= navIcon($icon) ?>
        <?= $label ?>
        <?php if ($slug === 'kyc' && $stats['pending_kyc'] ?? 0): ?>
        <span class="badge badge-danger" style="font-size:9px;padding:2px 6px;"><?= $stats['pending_kyc'] ?></span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<?php // ── DASHBOARD TAB ── ?>
<?php if (!$tab || $tab === 'dashboard'): ?>
<div class="stats-grid reveal" style="margin-bottom:24px;">
    <?php $statCards = [
        ['Total Customers', number_format($stats['customers']), 'Active accounts', 'users', 'var(--blue)'],
        ['Total Deposits',  formatBDT($stats['total_balance']), 'Across all accounts', 'trending-up', 'var(--green)'],
        ['Txns Today',      number_format($stats['txn_today']), formatBDT($stats['txn_vol_today']) . ' volume', 'activity', 'var(--amber)'],
        ['Pending KYC',     number_format($stats['pending_kyc']), 'Awaiting review', 'shield', 'var(--red)'],
        ['Active Staff',    number_format($stats['staff']), 'Employees', 'briefcase', 'var(--ink-muted)'],
        ['Branches',        number_format($stats['branches']), 'Network locations', 'map-pin', 'var(--blue)'],
    ];
    foreach ($statCards as [$label, $value, $sub, $icon, $color]): ?>
    <div class="stat-card">
        <div class="stat-card-icon">
            <svg viewBox="0 0 24 24" fill="none" stroke="<?= $color ?>" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" width="20" height="20">
                <?php $paths = [
                    'users'       => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
                    'trending-up' => '<polyline points="23 6 13.5 15.5 8.5 10.5 1 18"/><polyline points="17 6 23 6 23 12"/>',
                    'activity'    => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
                    'shield'      => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
                    'briefcase'   => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
                    'map-pin'     => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
                ]; echo $paths[$icon] ?? ''; ?>
            </svg>
        </div>
        <div class="stat-card-label"><?= $label ?></div>
        <div class="stat-card-value"><?= $value ?></div>
        <div class="stat-card-sub"><?= $sub ?></div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Recent transactions -->
<div class="card reveal">
    <div class="card-header">
        <h4>Recent Transactions</h4>
        <a href="?tab=transactions" class="btn btn-ghost btn-sm">View all →</a>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Date</th><th>Reference</th><th>From</th><th>To</th><th>Type</th><th>Amount</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php
                $recent = $pdo->query(
                    "SELECT t.*, tt.TypeName,
                            CONCAT(fc.FirstName,' ',fc.LastName) AS from_name,
                            CONCAT(tc.FirstName,' ',tc.LastName) AS to_name
                     FROM TRANSACTION t
                     JOIN TRANSACTIONTYPE tt ON t.TransactionTypeID=tt.TransactionTypeID
                     LEFT JOIN CUSTOMER fc ON t.FromCustomerID=fc.CustomerID
                     LEFT JOIN CUSTOMER tc ON t.ToCustomerID=tc.CustomerID
                     ORDER BY t.TransactionDate DESC LIMIT 10"
                )->fetchAll();
                foreach ($recent as $txn): ?>
                <tr>
                    <td><?= formatDate($txn['TransactionDate'], 'd M Y, g:i A') ?></td>
                    <td><code style="font-size:12px;"><?= e($txn['ReferenceNumber']) ?></code></td>
                    <td><?= e($txn['from_name'] ?: '—') ?></td>
                    <td><?= e($txn['to_name']   ?: '—') ?></td>
                    <td><span class="badge badge-info"><?= e($txn['TypeName']) ?></span></td>
                    <td><strong><?= formatBDT($txn['TransactionAmount']) ?></strong></td>
                    <td><span class="badge badge-<?= $txn['TransactionStatus']==='Completed'?'success':'warning' ?>"><?= e($txn['TransactionStatus']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'customers'): ?>
<!-- ── CUSTOMERS TAB ── -->
<div class="card reveal">
    <div class="card-header">
        <h4>Customers (<?= number_format($totalCustomers) ?>)</h4>
        <div class="search-bar" style="width:280px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="customerSearch" placeholder="Search customers…" value="<?= e($search) ?>" onkeydown="if(event.key==='Enter')window.location='?tab=customers&q='+encodeURIComponent(this.value)">
        </div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>ID</th><th>Name</th><th>Email</th><th>Account</th><th>Balance</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($customers as $c): ?>
                <tr>
                    <td>#<?= $c['CustomerID'] ?></td>
                    <td><strong><?= e($c['FirstName'] . ' ' . $c['LastName']) ?></strong></td>
                    <td><?= e($c['Email']) ?></td>
                    <td><code style="font-size:12px;"><?= e($c['AccountNumber'] ?? '—') ?></code></td>
                    <td><?= formatBDT($c['AvailableBalance'] ?? 0) ?></td>
                    <td>
                        <span class="badge badge-<?= $c['IsActive'] ? 'success' : 'danger' ?>">
                            <?= $c['IsActive'] ? 'Active' : 'Inactive' ?>
                        </span>
                    </td>
                    <td>
                        <form method="POST" action="?tab=customers" style="display:inline;">
                            <?= csrfField() ?>
                            <input type="hidden" name="customer_id" value="<?= $c['CustomerID'] ?>">
                            <input type="hidden" name="active" value="<?= $c['IsActive'] ? '0' : '1' ?>">
                            <button type="submit" name="toggle_customer" value="1"
                                    class="btn btn-sm <?= $c['IsActive'] ? 'btn-outline' : 'btn-primary' ?>"
                                    onclick="return confirm('<?= $c['IsActive'] ? 'Deactivate' : 'Activate' ?> this customer?')">
                                <?= $c['IsActive'] ? 'Deactivate' : 'Activate' ?>
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <!-- Pagination -->
    <?php if (($totalPages ?? 1) > 1): ?>
    <div class="card-footer">
        <div class="pagination">
            <?php for ($i = 1; $i <= ($totalPages ?? 1); $i++): ?>
            <a href="?tab=customers&page=<?= $i ?>&q=<?= urlencode($search) ?>"
               class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'transactions'): ?>
<!-- ── TRANSACTIONS TAB ── -->
<div class="card reveal">
    <div class="card-header">
        <h4>All Transactions (<?= number_format($totalTxns) ?>)</h4>
        <div class="search-bar" style="width:280px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" placeholder="Search by ref or name…" value="<?= e($search) ?>" onkeydown="if(event.key==='Enter')window.location='?tab=transactions&q='+encodeURIComponent(this.value)">
        </div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Date</th><th>Reference</th><th>From</th><th>To</th><th>Type</th><th>Amount</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($transactions as $txn): ?>
                <tr>
                    <td><?= formatDate($txn['TransactionDate'], 'd M Y, g:i A') ?></td>
                    <td><code style="font-size:12px;"><?= e($txn['ReferenceNumber']) ?></code></td>
                    <td><?= e($txn['from_name'] ?: '—') ?></td>
                    <td><?= e($txn['to_name']   ?: '—') ?></td>
                    <td><span class="badge badge-info"><?= e($txn['TypeName']) ?></span></td>
                    <td><strong><?= formatBDT($txn['TransactionAmount']) ?></strong></td>
                    <td><span class="badge badge-<?= $txn['TransactionStatus']==='Completed'?'success':'warning' ?>"><?= e($txn['TransactionStatus']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (($totalPages ?? 1) > 1): ?>
    <div class="card-footer">
        <div class="pagination">
            <?php for ($i = 1; $i <= ($totalPages ?? 1); $i++): ?>
            <a href="?tab=transactions&page=<?= $i ?>&q=<?= urlencode($search) ?>"
               class="page-link <?= $i === $page ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'kyc'): ?>
<!-- ── KYC TAB ── -->
<div class="card reveal">
    <div class="card-header"><h4>KYC Verifications</h4></div>
    <?php if (empty($kycList)): ?>
    <div class="empty-state">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        <h3>No KYC submissions</h3>
        <p>All requests have been processed.</p>
    </div>
    <?php else: ?>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Customer</th><th>Email</th><th>Document</th><th>Submitted</th><th>Status</th><th>Actions</th></tr>
            </thead>
            <tbody>
                <?php foreach ($kycList as $kyc): ?>
                <tr>
                    <td><strong><?= e($kyc['customer_name']) ?></strong></td>
                    <td><?= e($kyc['Email']) ?></td>
                    <td><?= e($kyc['document_type'] ?? '—') ?></td>
                    <td><?= formatDate($kyc['submitted_at'], 'd M Y') ?></td>
                    <td><span class="badge badge-<?= $kyc['status']==='verified'?'success':($kyc['status']==='rejected'?'danger':'warning') ?>"><?= ucfirst($kyc['status']) ?></span></td>
                    <td>
                        <?php if ($kyc['status'] === 'pending'): ?>
                        <form method="POST" action="?tab=kyc" style="display:inline-flex;gap:8px;align-items:center;">
                            <?= csrfField() ?>
                            <input type="hidden" name="kyc_id" value="<?= $kyc['id'] ?>">
                            <input type="text" name="admin_note" placeholder="Note (optional)" style="padding:6px 10px;font-size:12px;border:1px solid var(--cream-3);border-radius:6px;width:160px;">
                            <button type="submit" name="kyc_action" value="approve" class="btn btn-sm btn-primary">Approve</button>
                            <button type="submit" name="kyc_action" value="reject"  class="btn btn-sm btn-outline" style="color:var(--red);border-color:var(--red);">Reject</button>
                        </form>
                        <?php else: ?>
                        <span style="font-size:12px;color:var(--ink-faint);"><?= e($kyc['admin_note'] ?? 'Processed') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'staff'): ?>
<!-- ── STAFF TAB ── -->
<div class="card reveal">
    <div class="card-header">
        <h4>Staff Members</h4>
        <button class="btn btn-primary btn-sm" data-modal-open="addStaffModal">+ Add Staff</button>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Branch</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($staffList as $s): ?>
                <tr>
                    <td><strong><?= e($s['first_name'] . ' ' . $s['last_name']) ?></strong></td>
                    <td><?= e($s['email']) ?></td>
                    <td><span class="badge badge-info"><?= e($s['role']) ?></span></td>
                    <td><?= e($s['department'] ?? '—') ?></td>
                    <td><?= e($s['branch_id'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= $s['is_active'] ? 'success' : 'neutral' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'notifications'): ?>
<!-- ── NOTIFICATIONS TAB ── -->
<div class="card reveal" style="max-width:600px;">
    <div class="card-header"><h4>Broadcast Notification</h4></div>
    <div class="card-body">
        <p style="font-size:14px;color:var(--ink-muted);margin-bottom:20px;">Send a notification to all active customers.</p>
        <form method="POST" action="?tab=notifications">
            <?= csrfField() ?>
            <div class="form-group">
                <label class="form-label" for="notif_title">Title <span class="required">*</span></label>
                <input type="text" id="notif_title" name="notif_title" class="form-control" placeholder="e.g. System Maintenance" required>
            </div>
            <div class="form-group">
                <label class="form-label" for="notif_message">Message <span class="required">*</span></label>
                <textarea id="notif_message" name="notif_message" class="form-control" rows="4" required placeholder="Message to all customers…"></textarea>
            </div>
            <div class="form-group">
                <label class="form-label" for="notif_type">Type</label>
                <select id="notif_type" name="notif_type" class="form-control">
                    <option value="info">Info</option>
                    <option value="success">Success</option>
                    <option value="warning">Warning</option>
                    <option value="danger">Alert</option>
                </select>
            </div>
            <button type="submit" name="broadcast" value="1" class="btn btn-primary"
                    onclick="return confirm('Send this notification to ALL customers?')">
                Send to All Customers
            </button>
        </form>
    </div>
</div>

<?php elseif ($tab === 'reports'): ?>
<!-- ── REPORTS TAB ── -->
<div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;">
    <div class="card reveal">
        <div class="card-header"><h4>Monthly Transaction Volume</h4></div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Month</th><th>Count</th><th>Volume</th></tr></thead>
                <tbody>
                    <?php foreach ($reports['monthly'] as $r): ?>
                    <tr>
                        <td><?= e($r['month']) ?></td>
                        <td><?= number_format($r['count']) ?></td>
                        <td><strong><?= formatBDT($r['total']) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="card reveal">
        <div class="card-header"><h4>By Transaction Type</h4></div>
        <div class="table-wrapper">
            <table>
                <thead><tr><th>Type</th><th>Count</th><th>Total Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($reports['by_type'] as $r): ?>
                    <tr>
                        <td><span class="badge badge-info"><?= e($r['TypeName']) ?></span></td>
                        <td><?= number_format($r['count']) ?></td>
                        <td><strong><?= formatBDT($r['total']) ?></strong></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php endif; ?>
</div><!-- .page-content -->

<?php include '../includes/footer.php'; ?>
