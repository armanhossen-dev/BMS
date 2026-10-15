<?php
/**
 * Shared sidebar/topbar header include for all authenticated pages.
 * Variables expected:
 *   $pageTitle  — current page title
 *   $activePage — slug for active nav item (e.g. 'dashboard', 'deposit')
 *   $pdo, $_SESSION — already loaded via config/db.php
 */

// Determine role and paths
$role     = $_SESSION['role'] ?? 'client';
$username = $_SESSION['username'] ?? 'User';
$userId   = $_SESSION['user_id'] ?? 0;
$initials = strtoupper(substr($username, 0, 1));

// Root path prefix depending on depth
$rootUrl = '';
$script  = $_SERVER['SCRIPT_FILENAME'] ?? '';
$rootDir = realpath(__DIR__ . '/..');
$curDir  = realpath(dirname($script));
if ($rootDir && $curDir && strpos($curDir, $rootDir) === 0) {
    $depth   = substr_count(str_replace($rootDir, '', $curDir), DIRECTORY_SEPARATOR);
    $rootUrl = str_repeat('../', $depth);
}

// Unread notifications (clients only)
$unreadCount = 0;
if ($role === 'client') {
    try {
        $s = $pdo->prepare("SELECT COUNT(*) FROM NOTIFICATIONS WHERE customer_id = ? AND is_read = 0");
        $s->execute([$userId]);
        $unreadCount = (int)$s->fetchColumn();
    } catch (\Exception $e) {}
}

// Pending items for staff / admin
$pendingKYC = 0;
if ($role === 'staff' || $role === 'admin') {
    try {
        $pendingKYC = (int)$pdo->query("SELECT COUNT(*) FROM KYC_VERIFICATIONS WHERE status = 'pending'")->fetchColumn();
    } catch (\Exception $e) {}
}

// Toast & Login Notifications
$toast = getToast();
$isAfterLogin = !empty($_SESSION['just_logged_in']);
unset($_SESSION['just_logged_in']);

$loginNotifications = [];
if ($isAfterLogin) {
    if ($toast) {
        $loginNotifications[] = [
            'title'   => 'Login Successful',
            'message' => $toast['message'],
            'type'    => $toast['type'] ?? 'success'
        ];
    }
    // For clients, also queue unread notifications (up to 3 latest unread)
    if ($role === 'client') {
        try {
            $stmtNotif = $pdo->prepare("SELECT notification_id, title, message, type FROM NOTIFICATIONS WHERE customer_id = ? AND is_read = 0 ORDER BY created_at DESC LIMIT 3");
            $stmtNotif->execute([$userId]);
            $unreads = $stmtNotif->fetchAll();
            foreach ($unreads as $un) {
                $loginNotifications[] = [
                    'id'      => $un['notification_id'] ?? null,
                    'title'   => $un['title'] ?: 'Notification',
                    'message' => $un['message'],
                    'type'    => $un['type'] ?: 'info'
                ];
            }
        } catch (\Exception $e) {}
    } elseif ($role === 'staff' || $role === 'admin') {
        if ($pendingKYC > 0) {
            $loginNotifications[] = [
                'title'   => 'KYC Compliance',
                'message' => "You have {$pendingKYC} pending KYC verification(s) awaiting review.",
                'type'    => 'warning'
            ];
        }
    }
}

// Build nav items based on role — [slug, label, boxicon-class, href, badge?]
$navGroups = [];
if ($role === 'client') {
    $navGroups = [
        'Main' => [
            ['dashboard',     'Dashboard',     'bx bxs-dashboard',      $rootUrl . 'dashboard.php'],
            ['deposit',       'Deposit',       'bx bx-log-in-circle',   $rootUrl . 'deposit.php'],
            ['withdraw',      'Withdraw',      'bx bx-log-out-circle',  $rootUrl . 'withdraw.php'],
            ['transfer',      'Transfer',      'bx bx-transfer',        $rootUrl . 'transfer.php'],
        ],
        'Account' => [
            ['cards',         'My Cards',      'bx bx-credit-card',     $rootUrl . 'cards.php'],
            ['profile',       'Profile',       'bx bx-user',            $rootUrl . 'profile.php'],
            ['notifications', 'Notifications', 'bx bx-bell',            $rootUrl . 'notifications.php', $unreadCount],
            ['feedback',      'Support',       'bx bx-support',         $rootUrl . 'feedback.php'],
        ],
    ];
} elseif ($role === 'staff') {
    $navGroups = [
        'Main' => [
            ['dashboard',    'Dashboard',    'bx bxs-dashboard',       $rootUrl . 'staff/dashboard.php'],
            ['kyc',          'KYC Verify',   'bx bx-shield-quarter',   $rootUrl . 'staff/dashboard.php?tab=kyc', $pendingKYC],
            ['feedback',     'Feedback',     'bx bx-message-dots',     $rootUrl . 'staff/dashboard.php?tab=feedback'],
            ['transactions', 'Transactions', 'bx bx-trending-up',      $rootUrl . 'staff/dashboard.php?tab=transactions'],
        ],
        'Account' => [
            ['messages',     'Messages',     'bx bx-envelope',         $rootUrl . 'staff/dashboard.php?tab=messages'],
            ['profile',      'Profile',      'bx bx-user',             $rootUrl . 'staff/dashboard.php?tab=profile'],
        ],
    ];
} elseif ($role === 'admin') {
    $navGroups = [
        'Main' => [
            ['dashboard',    'Dashboard',    'bx bxs-dashboard',       $rootUrl . 'admin/index.php'],
            ['customers',    'Customers',    'bx bx-group',            $rootUrl . 'admin/index.php?tab=customers'],
            ['transactions', 'Transactions', 'bx bx-trending-up',      $rootUrl . 'admin/index.php?tab=transactions'],
            ['accounts',     'Accounts',     'bx bx-credit-card',      $rootUrl . 'admin/index.php?tab=accounts'],
        ],
        'Management' => [
            ['staff',        'Staff',        'bx bx-briefcase',        $rootUrl . 'admin/index.php?tab=staff'],
            ['branches',     'Branches',     'bx bx-map-pin',          $rootUrl . 'admin/index.php?tab=branches'],
            ['notifications','Notify All',   'bx bx-bell',             $rootUrl . 'admin/index.php?tab=notifications'],
            ['feedback',     'Feedback',     'bx bx-message-dots',     $rootUrl . 'admin/index.php?tab=feedback'],
            ['kyc',          'KYC Requests', 'bx bx-shield-quarter',   $rootUrl . 'admin/index.php?tab=kyc', $pendingKYC],
            ['reports',      'Reports',      'bx bx-bar-chart-alt-2',  $rootUrl . 'admin/index.php?tab=reports'],
        ],
    ];
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — Asha Bank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css" rel="stylesheet">
    <link rel="icon" type="image/svg+xml" href="<?= $rootUrl ?>assets/images/favicon.svg">
    <link rel="alternate icon" href="<?= $rootUrl ?>favicon.svg">
    <link rel="stylesheet" href="<?= $rootUrl ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= $rootUrl ?>assets/css/components.css">
</head>
<body>

<!-- Toast container (Bottom-Right) -->
<div id="toastContainer" class="toast-container"></div>

<?php if ($isAfterLogin && !empty($loginNotifications)): ?>
<script>
window.__LOGIN_NOTIFICATIONS__ = <?= json_encode($loginNotifications) ?>;
</script>
<?php elseif ($toast): ?>
<script>
window.__FLASH_TOAST__ = <?= json_encode($toast) ?>;
</script>
<?php endif; ?>

<!-- Desktop Only Mode Gate for Mobile/Responsive -->
<div class="desktop-only-gate" aria-live="polite">
    <div class="desktop-gate-card">
        <div class="desktop-gate-badge">
            <i class='bx bx-desktop'></i>
            <span>Desktop Mode Required</span>
        </div>
        <div class="desktop-gate-icon-wrap">
            <div class="gate-icon-circle">
                <i class='bx bx-laptop'></i>
            </div>
            <div class="gate-icon-mini-tag">
                <i class='bx bx-shield-quarter'></i>
            </div>
        </div>
        <h2 class="desktop-gate-title">Desktop Only Portal</h2>
        <p class="desktop-gate-desc">
            Asha Bank's banking portals, interactive dashboards, and account management are currently designed exclusively for desktop displays for enterprise security and multi-column workspace clarity.
        </p>
        <div class="desktop-gate-features">
            <div class="feature-item">
                <div class="feature-bullet"><i class='bx bx-check'></i></div>
                <div>
                    <strong>Enterprise Multi-Column View</strong>
                    <span>Full financial ledgers, statement exports & real-time analytics</span>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-bullet"><i class='bx bx-check'></i></div>
                <div>
                    <strong>Workstation Security Session</strong>
                    <span>Hardware-backed 256-bit encryption & two-factor authentication</span>
                </div>
            </div>
        </div>
        <a href="<?= $rootUrl ?>index.php" class="gate-btn-home">
            <i class='bx bx-left-arrow-alt'></i> Return to Homepage
        </a>
        <p class="desktop-gate-hint">
            💡 <em>Tip: On a mobile browser, tap menu and select <b>"Request Desktop Site"</b> to preview.</em>
        </p>
    </div>
</div>

<!-- Sidebar overlay (mobile) -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- App layout -->
<div class="app-layout">

<!-- ── Sidebar ──────────────────────────────────────────────── -->
<aside id="sidebar" class="sidebar" aria-label="Main navigation">
    <!-- Logo -->
    <a href="<?= $rootUrl ?>index.php" class="sidebar-logo">
        <div class="sidebar-logo-mark">
            <svg viewBox="0 0 24 24" fill="none" stroke="#0B3D2E" stroke-width="2.2" stroke-linecap="round"><path d="M4 16a8 8 0 0 1 16 0"/><path d="M12 4v2M4.9 7.9l1.4 1.4M19.1 7.9l-1.4 1.4M2 20h20"/></svg>
        </div>
        <div>
            <div class="sidebar-logo-text">Asha Bank</div>
            <span class="sidebar-logo-sub"><?= ucfirst($role) ?> Portal</span>
        </div>
    </a>

    <!-- Navigation -->
    <nav class="sidebar-nav" aria-label="Navigation">
        <?php foreach ($navGroups as $group => $items): ?>
        <div class="sidebar-section-label"><?= e($group) ?></div>
        <?php foreach ($items as $item):
            [$slug, $label, $icon, $href] = $item;
            $badge = $item[4] ?? 0;
            $isActive = ($activePage ?? '') === $slug;
        ?>
        <a href="<?= e($href) ?>"
           class="<?= $isActive ? 'active' : '' ?>"
           <?= $isActive ? 'aria-current="page"' : '' ?>>
            <i class="<?= $icon ?>"></i>
            <?= e($label) ?>
            <?php if ($badge > 0): ?>
            <span class="nav-badge" aria-label="<?= $badge ?> pending"><?= $badge ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <!-- Footer -->
    <div class="sidebar-footer">
        <div class="sidebar-user">
            <div class="sidebar-avatar dark"><?= $initials ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= e($username) ?></div>
                <div class="sidebar-user-role"><?= e($role) ?></div>
            </div>
        </div>
        <a href="<?= $rootUrl ?>logout.php" class="btn btn-ghost w-full sidebar-signout" style="justify-content:flex-start;gap:10px;font-size:13px;padding:9px 12px;">
            <i class='bx bx-log-out' style="font-size:18px;"></i>
            Sign Out
        </a>
    </div>
</aside>

<!-- ── Main content ──────────────────────────────────────────── -->
<div class="main-content">

<!-- Topbar -->
<header class="topbar">
    <div class="topbar-left">
        <button id="menuToggle" class="menu-toggle" aria-label="Toggle navigation" aria-controls="sidebar">
            <i class='bx bx-menu'></i>
        </button>
        <div class="topbar-breadcrumb">
            <a href="<?= $rootUrl ?><?= $role === 'staff' ? 'staff/dashboard.php' : ($role === 'admin' ? 'admin/index.php' : 'dashboard.php') ?>">Home</a>
            <?php if (($pageTitle ?? '') !== 'Dashboard'): ?>
            <span class="sep">/</span>
            <span><?= e($pageTitle ?? '') ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="topbar-right">
        <!-- Theme toggle -->
        <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme" title="Toggle dark mode">
            <i class='bx bx-moon' id="themeIcon"></i>
        </button>
        <?php if ($role === 'client'): ?>
        <a href="<?= $rootUrl ?>notifications.php" class="notif-btn" aria-label="<?= $unreadCount ?> notifications">
            <i class='bx bx-bell'></i>
            <?php if ($unreadCount > 0): ?>
            <span class="notif-count"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <a href="<?= $rootUrl ?><?= $role === 'admin' ? 'admin/index.php?tab=profile' : ($role === 'staff' ? 'staff/dashboard.php?tab=profile' : 'profile.php') ?>"
           class="btn btn-ghost btn-sm" aria-label="Profile" style="gap:8px;">
            <div class="avatar avatar-sm" style="background:var(--bg-muted);color:var(--text-primary);font-size:11px;"><?= $initials ?></div>
            <span style="font-size:13px;font-weight:500;color:var(--text-secondary);"><?= e($username) ?></span>
        </a>
    </div>
</header>
<!-- end topbar -->
