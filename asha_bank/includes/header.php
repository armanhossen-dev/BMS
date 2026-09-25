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

// Toast
$toast = getToast();

// Build nav items based on role
$navGroups = [];
if ($role === 'client') {
    $navGroups = [
        'Main' => [
            ['dashboard',     'Dashboard',     'home',        $rootUrl . 'dashboard.php'],
            ['deposit',       'Deposit',       'arrow-down',   $rootUrl . 'deposit.php'],
            ['withdraw',      'Withdraw',      'arrow-up',    $rootUrl . 'withdraw.php'],
            ['transfer',      'Transfer',      'repeat',      $rootUrl . 'transfer.php'],
        ],
        'Account' => [
            ['cards',         'My Cards',      'credit-card', $rootUrl . 'cards.php'],
            ['profile',       'Profile',       'user',        $rootUrl . 'profile.php'],
            ['notifications', 'Notifications', 'bell',        $rootUrl . 'notifications.php', $unreadCount],
            ['feedback',      'Support',       'message-square', $rootUrl . 'feedback.php'],
        ],
    ];
} elseif ($role === 'staff') {
    $navGroups = [
        'Main' => [
            ['dashboard',    'Dashboard',    'home',       $rootUrl . 'staff/dashboard.php'],
            ['kyc',          'KYC Verify',   'check-circle', $rootUrl . 'staff/dashboard.php?tab=kyc', $pendingKYC],
            ['feedback',     'Feedback',     'message-square', $rootUrl . 'staff/dashboard.php?tab=feedback'],
            ['transactions', 'Transactions', 'activity',   $rootUrl . 'staff/dashboard.php?tab=transactions'],
        ],
        'Account' => [
            ['messages',     'Messages',     'mail',       $rootUrl . 'staff/dashboard.php?tab=messages'],
            ['profile',      'Profile',      'user',       $rootUrl . 'staff/profile.php'],
        ],
    ];
} elseif ($role === 'admin') {
    $navGroups = [
        'Main' => [
            ['dashboard',    'Dashboard',    'home',       $rootUrl . 'admin/index.php'],
            ['customers',    'Customers',    'users',      $rootUrl . 'admin/index.php?tab=customers'],
            ['transactions', 'Transactions', 'activity',   $rootUrl . 'admin/index.php?tab=transactions'],
            ['accounts',     'Accounts',     'credit-card', $rootUrl . 'admin/index.php?tab=accounts'],
        ],
        'Management' => [
            ['staff',        'Staff',        'briefcase',  $rootUrl . 'admin/index.php?tab=staff'],
            ['branches',     'Branches',     'map-pin',    $rootUrl . 'admin/index.php?tab=branches'],
            ['notifications','Notify All',   'bell',       $rootUrl . 'admin/index.php?tab=notifications'],
            ['feedback',     'Feedback',     'message-square', $rootUrl . 'admin/index.php?tab=feedback'],
            ['kyc',          'KYC Requests', 'shield',     $rootUrl . 'admin/index.php?tab=kyc', $pendingKYC],
            ['reports',      'Reports',      'bar-chart-2',$rootUrl . 'admin/index.php?tab=reports'],
        ],
    ];
}

// Icon SVG map
function navIcon(string $name): string {
    $icons = [
        'home'           => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'arrow-down'     => '<line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/>',
        'arrow-up'       => '<line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/>',
        'repeat'         => '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
        'credit-card'    => '<rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/>',
        'user'           => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'bell'           => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'message-square' => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'check-circle'   => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'activity'       => '<polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>',
        'users'          => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'briefcase'      => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/><line x1="9" y1="13" x2="15" y2="13"/>',
        'map-pin'        => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'bar-chart-2'    => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'shield'         => '<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>',
        'mail'           => '<path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>',
    ];
    $d = $icons[$name] ?? '<circle cx="12" cy="12" r="10"/>';
    return "<svg viewBox=\"0 0 24 24\" fill=\"none\" stroke=\"currentColor\" stroke-width=\"1.8\" stroke-linecap=\"round\" stroke-linejoin=\"round\">$d</svg>";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?= e($pageTitle ?? 'Dashboard') ?> — Asha Bank</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=DM+Serif+Display:ital@0;1&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $rootUrl ?>assets/css/style.css">
    <link rel="stylesheet" href="<?= $rootUrl ?>assets/css/components.css">
</head>
<body>

<!-- Toast container -->
<div id="toastContainer" class="toast-container"></div>

<?php if ($toast): ?>
<script>
document.addEventListener('DOMContentLoaded', () => {
    Toast.show(<?= json_encode($toast['message']) ?>, <?= json_encode($toast['type']) ?>);
});
</script>
<?php endif; ?>

<!-- Sidebar overlay (mobile) -->
<div id="sidebarOverlay" class="sidebar-overlay"></div>

<!-- App layout -->
<div class="app-layout">

<!-- ── Sidebar ────────────────────────────────────────── -->
<aside id="sidebar" class="sidebar" aria-label="Main navigation">
    <a href="<?= $rootUrl ?>index.php" class="sidebar-logo">
        <div class="sidebar-logo-mark">
            <svg viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18">
                <rect x="2" y="9" width="20" height="13" rx="2"/>
                <path d="M6 9V7a6 6 0 0 1 12 0v2"/>
                <circle cx="12" cy="15" r="2" fill="white" stroke="none"/>
            </svg>
        </div>
        <div>
            <div class="sidebar-logo-text">Asha Bank</div>
            <span class="sidebar-logo-sub"><?= ucfirst($role) ?> Portal</span>
        </div>
    </a>

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
            <?= navIcon($icon) ?>
            <?= e($label) ?>
            <?php if ($badge > 0): ?>
            <span class="nav-badge" aria-label="<?= $badge ?> pending"><?= $badge ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
        <?php endforeach; ?>
    </nav>

    <div class="sidebar-footer">
        <a href="<?= $rootUrl ?>logout.php" class="sidebar-user" style="margin-bottom:8px;">
            <div class="sidebar-avatar"><?= $initials ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= e($username) ?></div>
                <div class="sidebar-user-role"><?= e($role) ?></div>
            </div>
        </a>
        <a href="<?= $rootUrl ?>logout.php" class="btn btn-outline w-full" style="color:rgba(255,255,255,0.6);border-color:rgba(255,255,255,0.15);font-size:13px;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Sign Out
        </a>
    </div>
</aside>

<!-- ── Main content ───────────────────────────────────── -->
<div class="main-content">

<!-- Topbar -->
<header class="topbar">
    <div class="topbar-left">
        <button id="menuToggle" class="menu-toggle" aria-label="Toggle navigation" aria-controls="sidebar">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
        </button>
        <div class="topbar-breadcrumb">
            <a href="<?= $rootUrl ?>dashboard.php">Home</a>
            <?php if (($pageTitle ?? '') !== 'Dashboard'): ?>
            <span class="sep">/</span>
            <span><?= e($pageTitle ?? '') ?></span>
            <?php endif; ?>
        </div>
    </div>
    <div class="topbar-right">
        <?php if ($role === 'client'): ?>
        <a href="<?= $rootUrl ?>notifications.php" class="notif-btn" aria-label="<?= $unreadCount ?> notifications">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <?php if ($unreadCount > 0): ?>
            <span class="notif-count"><?= $unreadCount > 9 ? '9+' : $unreadCount ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>
        <a href="<?= $rootUrl ?><?= $role === 'admin' ? 'admin/' : ($role === 'staff' ? 'staff/' : '') ?>profile.php"
           class="btn btn-ghost btn-sm" aria-label="Profile">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="16" height="16"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
            <?= e($username) ?>
        </a>
    </div>
</header>
<!-- end topbar -->
