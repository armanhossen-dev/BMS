<?php requireLogin(); ?>
<div id="sidebarOverlay" class="sidebar-overlay"></div>
<div class="layout">
<?php include 'includes/sidebar.php'; ?>
<div class="main-wrapper">
    <!-- TOPBAR -->
    <div class="topbar">
        <div class="topbar-left">
            <button class="sidebar-toggle" id="sidebarToggle">☰</button>
            <div class="page-breadcrumb">
                <span><?php echo htmlspecialchars($pageTitle ?? 'Dashboard'); ?></span>
            </div>
        </div>
        <div class="topbar-right">
            <div class="topbar-time">
                <?php echo date('D, d M Y'); ?> &nbsp;|&nbsp; <strong id="topbarClock"></strong>
            </div>
            <div class="notification-btn" title="Notifications">
                🔔
                <span class="notif-badge">3</span>
            </div>
            <a href="logout.php" class="btn btn-outline btn-sm" style="padding:6px 14px;font-size:0.75rem;">⏻ Logout</a>
        </div>
    </div>
    <div class="main-content">