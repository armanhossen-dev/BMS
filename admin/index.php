<?php
require_once __DIR__ . '/../config/db.php';
requireAuth('admin');

if (!function_exists('navIcon')) {
    function navIcon(string $icon): string {
        $map = [
            'home'          => 'bx bxs-dashboard',
            'dashboard'     => 'bx bxs-dashboard',
            'users'         => 'bx bx-user',
            'customers'     => 'bx bx-group',
            'activity'      => 'bx bx-trending-up',
            'transactions'  => 'bx bx-transfer',
            'credit-card'   => 'bx bx-credit-card',
            'accounts'      => 'bx bx-credit-card',
            'briefcase'     => 'bx bx-briefcase',
            'staff'         => 'bx bx-briefcase',
            'shield'        => 'bx bx-shield-quarter',
            'kyc'           => 'bx bx-shield-quarter',
            'bell'          => 'bx bx-bell',
            'notifications' => 'bx bx-bell',
            'bar-chart-2'   => 'bx bx-bar-chart-alt-2',
            'reports'       => 'bx bx-bar-chart-alt-2',
            'map-pin'       => 'bx bx-map-pin',
            'branches'      => 'bx bx-map-pin',
            'message-dots'  => 'bx bx-message-dots',
            'feedback'      => 'bx bx-message-dots',
            'trending-up'   => 'bx bx-trending-up',
            'settings'      => 'bx bx-cog',
            'search'        => 'bx bx-search',
        ];
        if (str_starts_with($icon, 'bx ') || str_starts_with($icon, 'bxs-') || str_starts_with($icon, 'bx-')) {
            $cls = str_starts_with($icon, 'bx ') ? $icon : ('bx ' . $icon);
        } else {
            $cls = $map[$icon] ?? ('bx bx-' . $icon);
        }
        return '<i class="' . htmlspecialchars($cls, ENT_QUOTES, 'UTF-8') . '"></i>';
    }
}

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
    $params = $search ? ["%$search%","%$search%","%$search%","%$search%"] : [];
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

// ── Accounts tab ────────────────────────────────────────────
$accounts = [];
$totalAccounts = 0;
if ($tab === 'accounts') {
    $where = $search ? "WHERE (a.AccountNumber LIKE ? OR c.FirstName LIKE ? OR c.LastName LIKE ?)" : "";
    $params = $search ? ["%$search%","%$search%","%$search%"] : [];
    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM ACCOUNT a JOIN CUSTOMER c ON a.CustomerID=c.CustomerID $where");
    $countStmt->execute($params);
    $totalAccounts = (int)$countStmt->fetchColumn();
    $totalPages = (int)ceil($totalAccounts / $perPage);
    $offset = ($page - 1) * $perPage;
    $stmt = $pdo->prepare(
        "SELECT a.*, c.FirstName, c.LastName, c.Email, ap.ProductName, b.BranchName
         FROM ACCOUNT a
         JOIN CUSTOMER c ON a.CustomerID = c.CustomerID
         JOIN ACCOUNTPRODUCT ap ON a.ProductID = ap.ProductID
         JOIN BRANCH b ON a.BranchID = b.BranchID
         $where ORDER BY a.OpeningDate DESC LIMIT $perPage OFFSET $offset"
    );
    $stmt->execute($params);
    $accounts = $stmt->fetchAll();
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

// ── Branches tab ────────────────────────────────────────────
$branches = [];
if ($tab === 'branches') {
    $branches = $pdo->query("SELECT * FROM BRANCH ORDER BY BranchID ASC")->fetchAll();
}

// ── Feedback tab ────────────────────────────────────────────
$feedbackList = [];
if ($tab === 'feedback') {
    $feedbackList = $pdo->query(
        "SELECT f.*, CONCAT(c.FirstName, ' ', c.LastName) AS customer_name, c.Email
         FROM FEEDBACK f
         JOIN CUSTOMER c ON f.customer_id = c.CustomerID
         ORDER BY f.created_at DESC LIMIT 50"
    )->fetchAll();
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
    $kycId  = (int)($_POST['kyc_id'] ?? 0);
    $action = $_POST['kyc_action'] === 'approve' ? 'verified' : 'rejected';
    $note   = sanitize($_POST['admin_note'] ?? '');
    $pdo->prepare("UPDATE KYC_VERIFICATIONS SET status=?, rejection_reason=?, verified_at=NOW(), verified_by=? WHERE kyc_id=?")
        ->execute([$action, $note, $_SESSION['user_id'] ?? null, $kycId]);
    // Notify customer
    $stmt = $pdo->prepare("SELECT customer_id FROM KYC_VERIFICATIONS WHERE kyc_id=?");
    $stmt->execute([$kycId]);
    $row = $stmt->fetch();
    if ($row) {
        sendNotification($pdo, (int)$row['customer_id'], 'KYC ' . ucfirst($action),
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

// ── Handle Add Staff ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_staff'])) {
    verifyCsrf();
    $fname    = sanitize($_POST['first_name'] ?? '');
    $lname    = sanitize($_POST['last_name'] ?? '');
    $email    = sanitize($_POST['email'] ?? '');
    $phone    = sanitize($_POST['phone'] ?? '');
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $role     = sanitize($_POST['role'] ?? 'officer');
    $dept     = sanitize($_POST['department'] ?? 'Operations');

    if ($fname && $lname && $email && $username && $password) {
        try {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO STAFF (first_name, last_name, email, phone, username, password_hash, role, department, join_date, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 1, NOW())");
            $stmt->execute([$fname, $lname, $email, $phone, $username, $hash, $role, $dept]);
            setToast("Staff member $fname $lname added successfully.", 'success');
        } catch (\PDOException $e) {
            setToast("Failed to add staff: " . $e->getMessage(), 'danger');
        }
    } else {
        setToast("Please fill all required staff fields.", 'danger');
    }
    redirect('index.php?tab=staff');
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
include __DIR__ . '/../includes/header.php';
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
        'accounts'      => ['Accounts',      'credit-card'],
        'transactions'  => ['Transactions',  'activity'],
        'staff'         => ['Staff',         'briefcase'],
        'branches'      => ['Branches',      'map-pin'],
        'kyc'           => ['KYC Requests',  'shield'],
        'feedback'      => ['Feedback',      'message-dots'],
        'notifications' => ['Notifications', 'bell'],
        'reports'       => ['Reports',       'bar-chart-2'],
    ];
    $kycBadgeCount = $stats['pending_kyc'] ?? $pendingKYC ?? 0;
    foreach ($tabs as $slug => [$label, $icon]):
        $isActive = ($tab === $slug) || ($slug === 'dashboard' && !$tab);
    ?>
    <a href="?tab=<?= $slug ?>" class="tab-link <?= $isActive ? 'active' : '' ?>">
        <?= navIcon($icon) ?>
        <?= $label ?>
        <?php if ($slug === 'kyc' && $kycBadgeCount > 0): ?>
        <span class="badge badge-danger" style="font-size:9px;padding:2px 6px;"><?= $kycBadgeCount ?></span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</div>

<?php // ── DASHBOARD TAB ── ?>
<?php if (!$tab || $tab === 'dashboard'): ?>
<div class="stats-grid reveal" style="margin-bottom:24px;">
    <?php $statCards = [
        ['Total Customers', number_format($stats['customers']), 'Active accounts', 'bx-group', '#2563EB', 'rgba(37,99,235,0.08)'],
        ['Total Deposits',  formatBDT($stats['total_balance']), 'Across all accounts', 'bx-trending-up', '#16A34A', 'rgba(22,163,74,0.08)'],
        ['Txns Today',      number_format($stats['txn_today']), formatBDT($stats['txn_vol_today']) . ' volume', 'bx-transfer-alt', '#D97706', 'rgba(217,119,6,0.08)'],
        ['Pending KYC',     number_format($stats['pending_kyc']), 'Awaiting review', 'bx-shield-quarter', '#DC2626', 'rgba(220,38,38,0.08)'],
        ['Active Staff',    number_format($stats['staff']), 'Employees', 'bx-briefcase', '#71717A', 'rgba(113,113,122,0.1)'],
        ['Branches',        number_format($stats['branches']), 'Network locations', 'bx-map-pin', '#0284C7', 'rgba(2,132,199,0.08)'],
    ];
    foreach ($statCards as [$label, $value, $sub, $icon, $color, $bg]): ?>
    <div class="stat-card">
        <div class="stat-card-icon" style="background:<?= $bg ?>;">
            <i class='bx <?= $icon ?>' style="font-size:20px;color:<?= $color ?>;"></i>
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
            <i class='bx bx-search'></i>
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
                                    data-confirm="<?= $c['IsActive'] ? 'Deactivate this customer? They will lose access immediately.' : 'Activate this customer account?' ?>"
                                    data-confirm-title="<?= $c['IsActive'] ? 'Deactivate Customer' : 'Activate Customer' ?>"
                                    data-confirm-btn="<?= $c['IsActive'] ? 'Deactivate' : 'Activate' ?>"
                                    data-confirm-class="<?= $c['IsActive'] ? 'btn-danger' : 'btn-primary' ?>"
                                    data-confirm-icon="<?= $c['IsActive'] ? 'bx-user-x' : 'bx-user-check' ?>">
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

<?php elseif ($tab === 'accounts'): ?>
<!-- ── ACCOUNTS TAB ── -->
<div class="card reveal">
    <div class="card-header">
        <h4>All Accounts (<?= number_format($totalAccounts) ?>)</h4>
        <div class="search-bar" style="width:280px;">
            <i class='bx bx-search'></i>
            <input type="text" placeholder="Search account or customer…" value="<?= e($search) ?>" onkeydown="if(event.key==='Enter')window.location='?tab=accounts&q='+encodeURIComponent(this.value)">
        </div>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Account No</th><th>Customer</th><th>Product</th><th>Branch</th><th>Balance</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php if (empty($accounts)): ?>
                <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--ink-muted);">No accounts found.</td></tr>
                <?php endif; ?>
                <?php foreach ($accounts as $a): ?>
                <tr>
                    <td><code style="font-size:13px;font-weight:600;"><?= e($a['AccountNumber']) ?></code></td>
                    <td><strong><?= e($a['FirstName'] . ' ' . $a['LastName']) ?></strong><br><span style="font-size:12px;color:var(--ink-faint);"><?= e($a['Email']) ?></span></td>
                    <td><?= e($a['ProductName']) ?></td>
                    <td><?= e($a['BranchName']) ?></td>
                    <td><strong><?= formatBDT($a['AvailableBalance']) ?></strong></td>
                    <td>
                        <span class="badge badge-<?= $a['AccountStatus'] === 'Active' ? 'success' : ($a['AccountStatus'] === 'Frozen' ? 'warning' : 'danger') ?>">
                            <?= e($a['AccountStatus']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if (($totalPages ?? 1) > 1): ?>
    <div class="card-footer">
        <div class="pagination">
            <?php for ($i = 1; $i <= ($totalPages ?? 1); $i++): ?>
            <a href="?tab=accounts&page=<?= $i ?>&q=<?= urlencode($search) ?>"
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
            <i class='bx bx-search'></i>
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

<?php elseif ($tab === 'staff'): ?>
<!-- ── STAFF TAB ── -->
<div class="card reveal">
    <div class="card-header" style="display:flex;justify-content:space-between;align-items:center;">
        <h4>Staff Members (<?= count($staffList) ?>)</h4>
        <button type="button" class="btn btn-primary btn-sm" data-modal-open="addStaffModal">+ Add Staff</button>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Name</th><th>Email</th><th>Role</th><th>Department</th><th>Status</th></tr>
            </thead>
            <tbody>
                <?php foreach ($staffList as $s): ?>
                <tr>
                    <td><strong><?= e($s['first_name'] . ' ' . $s['last_name']) ?></strong></td>
                    <td><?= e($s['email']) ?></td>
                    <td><span class="badge badge-info"><?= ucfirst(e($s['role'])) ?></span></td>
                    <td><?= e($s['department'] ?? '—') ?></td>
                    <td><span class="badge badge-<?= $s['is_active'] ? 'success' : 'neutral' ?>"><?= $s['is_active'] ? 'Active' : 'Inactive' ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'branches'): ?>
<!-- ── BRANCHES TAB ── -->
<div class="card reveal">
    <div class="card-header">
        <h4>Branches (<?= count($branches) ?>)</h4>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>ID</th><th>Branch Name</th><th>IFSC / Routing</th><th>Address</th><th>City</th><th>Email</th><th>Phone</th></tr>
            </thead>
            <tbody>
                <?php foreach ($branches as $b): ?>
                <tr>
                    <td>#<?= $b['BranchID'] ?></td>
                    <td><strong><?= e($b['BranchName']) ?></strong></td>
                    <td><code><?= e($b['IFSCCode']) ?></code></td>
                    <td><?= e($b['Address']) ?></td>
                    <td><?= e($b['City']) ?></td>
                    <td><?= e($b['Email'] ?? '—') ?></td>
                    <td><?= e($b['Phone'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
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
                    <td><?= e($kyc['id_type'] ?? 'National ID') ?></td>
                    <td><?= formatDate($kyc['submitted_at'], 'd M Y') ?></td>
                    <td><span class="badge badge-<?= $kyc['status']==='verified'?'success':($kyc['status']==='rejected'?'danger':'warning') ?>"><?= ucfirst($kyc['status']) ?></span></td>
                    <td>
                        <?php if ($kyc['status'] === 'pending'): ?>
                        <form method="POST" action="?tab=kyc" style="display:inline-flex;gap:8px;align-items:center;">
                            <?= csrfField() ?>
                            <input type="hidden" name="kyc_id" value="<?= $kyc['kyc_id'] ?? $kyc['id'] ?? 0 ?>">
                            <input type="text" name="admin_note" placeholder="Note (optional)" style="padding:6px 10px;font-size:12px;border:1px solid var(--cream-3);border-radius:6px;width:160px;">
                            <button type="submit" name="kyc_action" value="approve" class="btn btn-sm btn-primary">Approve</button>
                            <button type="submit" name="kyc_action" value="reject"  class="btn btn-sm btn-outline" style="color:var(--red);border-color:var(--red);">Reject</button>
                        </form>
                        <?php else: ?>
                        <span style="font-size:12px;color:var(--ink-faint);"><?= e($kyc['rejection_reason'] ?? 'Processed') ?></span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?php elseif ($tab === 'feedback'): ?>
<!-- ── FEEDBACK TAB ── -->
<div class="card reveal">
    <div class="card-header">
        <h4>Customer Feedback & Support (<?= count($feedbackList) ?>)</h4>
    </div>
    <div class="table-wrapper">
        <table>
            <thead>
                <tr><th>Customer</th><th>Subject</th><th>Type</th><th>Date</th><th>Status</th><th>Message</th></tr>
            </thead>
            <tbody>
                <?php if (empty($feedbackList)): ?>
                <tr><td colspan="6" style="text-align:center;padding:24px;color:var(--ink-muted);">No feedback messages found.</td></tr>
                <?php endif; ?>
                <?php foreach ($feedbackList as $fb): ?>
                <tr>
                    <td><strong><?= e($fb['customer_name']) ?></strong><br><span style="font-size:12px;color:var(--ink-faint);"><?= e($fb['Email']) ?></span></td>
                    <td><strong><?= e($fb['subject']) ?></strong></td>
                    <td><span class="badge badge-info"><?= ucfirst($fb['type']) ?></span></td>
                    <td><?= formatDate($fb['created_at'], 'd M Y') ?></td>
                    <td><span class="badge badge-<?= $fb['status']==='resolved'?'success':($fb['status']==='in_progress'?'warning':'neutral') ?>"><?= ucfirst($fb['status']) ?></span></td>
                    <td style="max-width:320px;font-size:13px;color:var(--ink-muted);"><?= nl2br(e($fb['message'])) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?php elseif ($tab === 'notifications'): ?>
<!-- ── NOTIFICATIONS TAB ── -->
<div class="card reveal" style="max-width:640px;">
    <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <div>
            <h4>Broadcast Notification</h4>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px;">Publish an announcement directly to customer accounts</div>
        </div>
        <span class="badge badge-info" style="display:inline-flex;align-items:center;gap:5px;font-size:11px;">
            <i class='bx bx-broadcast'></i> <?= number_format($stats['customers'] ?? 0) ?> Recipients
        </span>
    </div>
    <div class="card-body">
        <p style="font-size:14px;color:var(--text-secondary);margin-bottom:20px;line-height:1.5;">
            Broadcasts appear immediately in the notification feed and bell dropdown of every active customer.
        </p>
        <form id="broadcastForm" method="POST" action="?tab=notifications">
            <?= csrfField() ?>
            <input type="hidden" name="broadcast" value="1">
            <div class="form-group">
                <label class="form-label" for="notif_title">Notification Title <span class="required">*</span></label>
                <input type="text" id="notif_title" name="notif_title" class="form-control" placeholder="e.g. Scheduled System Upgrade" required maxlength="120">
            </div>
            <div class="form-group">
                <label class="form-label" for="notif_type">Notification Category</label>
                <select id="notif_type" name="notif_type" class="form-control">
                    <option value="info">Info Notice (Blue)</option>
                    <option value="success">Success Notice (Green)</option>
                    <option value="warning">Important Warning (Amber)</option>
                    <option value="danger">Urgent Alert (Red)</option>
                </select>
            </div>
            <div class="form-group">
                <label class="form-label" for="notif_message">Message Content <span class="required">*</span></label>
                <textarea id="notif_message" name="notif_message" class="form-control" rows="4" required placeholder="Write clear, helpful message content for your customers…"></textarea>
                <div style="font-size:12px;color:var(--text-muted);margin-top:6px;display:flex;justify-content:space-between;">
                    <span>Visible in customer dashboard</span>
                    <span id="notifCharCount">0 characters</span>
                </div>
            </div>
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:12px;margin-top:22px;padding-top:16px;border-top:1px solid var(--border);">
                <button type="button" id="reviewBroadcastBtn" class="btn btn-primary" style="display:inline-flex;align-items:center;gap:8px;">
                    <i class='bx bx-send'></i> Review & Send Broadcast
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Broadcast Confirmation & Live Preview Modal ── -->
<div id="broadcastConfirmModal" class="modal-overlay" style="display:none;">
    <div class="modal" style="max-width:520px;" role="dialog" aria-modal="true" aria-labelledby="broadcastModalTitle">
        <div class="modal-header">
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="width:38px;height:38px;border-radius:10px;background:rgba(37,99,235,0.1);display:flex;align-items:center;justify-content:center;color:var(--info);font-size:22px;flex-shrink:0;">
                    <i class='bx bx-broadcast'></i>
                </div>
                <div>
                    <h3 id="broadcastModalTitle" style="margin:0;font-size:16px;font-weight:600;">Confirm Broadcast</h3>
                    <div style="font-size:12px;color:var(--text-muted);">Preview customer presentation before dispatching</div>
                </div>
            </div>
            <button class="modal-close" type="button" data-modal-close="broadcastConfirmModal" aria-label="Close">&times;</button>
        </div>
        <div class="modal-body" style="padding:var(--sp-5) var(--sp-6);">
            <!-- Target Summary -->
            <div style="display:flex;align-items:center;justify-content:space-between;padding:10px 14px;background:var(--bg-muted);border-radius:var(--r);margin-bottom:14px;font-size:13px;">
                <span style="color:var(--text-secondary);"><i class='bx bx-group' style="margin-right:6px;vertical-align:-2px;"></i> Delivery Audience</span>
                <strong style="color:var(--text-primary);">All Active Customers (<?= number_format($stats['customers'] ?? 0) ?>)</strong>
            </div>

            <!-- Live Customer View Card -->
            <div style="font-size:11px;font-weight:600;letter-spacing:0.06em;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px;">
                Live Customer Notification Card
            </div>
            <div id="broadcastPreviewBox" class="confirm-preview-box type-info">
                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                    <span id="broadcastPreviewBadge" class="badge badge-info" style="font-size:10px;text-transform:uppercase;">Info</span>
                    <span style="font-size:11px;color:var(--text-muted);"><i class='bx bx-time-five' style="vertical-align:-1px;"></i> Just now</span>
                </div>
                <h4 id="broadcastPreviewTitle" style="font-size:15px;font-weight:600;margin:0 0 6px;color:var(--text-primary);"></h4>
                <p id="broadcastPreviewMsg" style="font-size:13px;color:var(--text-secondary);line-height:1.5;margin:0;white-space:pre-wrap;word-break:break-word;"></p>
            </div>

            <!-- Warning Notice -->
            <div class="confirm-alert-note">
                <i class='bx bx-error-circle'></i>
                <div>
                    <strong>Immediate Action:</strong> This announcement will immediately push to all <?= number_format($stats['customers'] ?? 0) ?> active customer inboxes. Broadcasts cannot be recalled once sent.
                </div>
            </div>
        </div>
        <div class="modal-footer" style="padding:var(--sp-4) var(--sp-6);">
            <button type="button" class="btn btn-outline btn-sm" data-modal-close="broadcastConfirmModal">Keep Editing</button>
            <button type="button" id="confirmBroadcastSendBtn" class="btn btn-primary btn-sm" style="display:inline-flex;align-items:center;gap:6px;">
                <i class='bx bx-send'></i> Send Broadcast Now
            </button>
        </div>
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

<!-- ── Add Staff Modal ──────────────────────────────────────── -->
<div id="addStaffModal" class="modal-overlay" style="display:none;">
    <div class="modal-box" style="max-width:500px;width:100%;margin:auto;background:var(--surface);border-radius:12px;padding:24px;border:1px solid var(--cream-3);box-shadow:var(--shadow-xl);">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="margin:0;font-size:18px;">Add New Staff Member</h3>
            <button type="button" class="btn btn-ghost btn-sm" data-modal-close="addStaffModal" style="padding:4px 8px;font-size:16px;">&times;</button>
        </div>
        <form method="POST" action="?tab=staff">
            <?= csrfField() ?>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">First Name <span class="required">*</span></label>
                    <input type="text" name="first_name" class="form-control" required placeholder="John">
                </div>
                <div class="form-group">
                    <label class="form-label">Last Name <span class="required">*</span></label>
                    <input type="text" name="last_name" class="form-control" required placeholder="Doe">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address <span class="required">*</span></label>
                <input type="email" name="email" class="form-control" required placeholder="john.doe@ashabank.com">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">Username <span class="required">*</span></label>
                    <input type="text" name="username" class="form-control" required placeholder="johndoe">
                </div>
                <div class="form-group">
                    <label class="form-label">Phone</label>
                    <input type="text" name="phone" class="form-control" placeholder="+8801700000000">
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Password <span class="required">*</span></label>
                <input type="password" name="password" class="form-control" required placeholder="Minimum 6 characters">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                <div class="form-group">
                    <label class="form-label">Role</label>
                    <select name="role" class="form-control">
                        <option value="officer">Officer</option>
                        <option value="teller">Teller</option>
                        <option value="manager">Manager</option>
                        <option value="support">Support</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Department</label>
                    <input type="text" name="department" class="form-control" placeholder="Operations" value="Operations">
                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:20px;">
                <button type="button" class="btn btn-outline" data-modal-close="addStaffModal">Cancel</button>
                <button type="submit" name="add_staff" value="1" class="btn btn-primary">Create Staff</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const titleInput = document.getElementById('notif_title');
    const msgInput   = document.getElementById('notif_message');
    const typeSelect = document.getElementById('notif_type');
    const charCount  = document.getElementById('notifCharCount');
    const reviewBtn  = document.getElementById('reviewBroadcastBtn');
    const form       = document.getElementById('broadcastForm');

    if (msgInput && charCount) {
        const updateCount = () => {
            charCount.textContent = msgInput.value.length + ' character' + (msgInput.value.length === 1 ? '' : 's');
        };
        msgInput.addEventListener('input', updateCount);
        updateCount();
    }

    if (reviewBtn && form) {
        reviewBtn.addEventListener('click', (e) => {
            e.preventDefault();
            if (!form.reportValidity()) return;

            const title = titleInput.value.trim();
            const msg   = msgInput.value.trim();
            const type  = typeSelect ? typeSelect.value : 'info';

            const previewBox   = document.getElementById('broadcastPreviewBox');
            const previewBadge = document.getElementById('broadcastPreviewBadge');
            const previewTitle = document.getElementById('broadcastPreviewTitle');
            const previewMsg   = document.getElementById('broadcastPreviewMsg');

            if (previewTitle) previewTitle.textContent = title;
            if (previewMsg)   previewMsg.textContent   = msg;

            if (previewBox) {
                previewBox.className = 'confirm-preview-box type-' + type;
            }
            if (previewBadge) {
                previewBadge.className = 'badge badge-' + (type === 'danger' ? 'danger' : (type === 'warning' ? 'warning' : (type === 'success' ? 'success' : 'info')));
                previewBadge.textContent = type;
            }

            if (window.Modal) {
                Modal.open('broadcastConfirmModal');
            } else {
                const m = document.getElementById('broadcastConfirmModal');
                if (m) {
                    m.classList.add('active');
                    m.style.display = 'flex';
                }
            }
        });

        const sendBtn = document.getElementById('confirmBroadcastSendBtn');
        if (sendBtn) {
            sendBtn.addEventListener('click', () => {
                sendBtn.disabled = true;
                sendBtn.innerHTML = "<i class='bx bx-loader-alt bx-spin'></i> Broadcasting...";
                form.submit();
            });
        }
    }
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
