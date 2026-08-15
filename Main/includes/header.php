<?php
/**
 * Customer portal header. Expects $pageTitle, $activeNav to be set by the including page.
 * Requires: config/db.php, config/language.php, includes/functions.php already loaded,
 * and require_customer() already called.
 */
$customer = get_customer($_SESSION['customer_id']);
$unread = unread_notification_count($_SESSION['customer_id']);
$stmtNotif = $pdo->prepare("SELECT * FROM NOTIFICATIONS WHERE customer_id = ? ORDER BY created_at DESC LIMIT 8");
$stmtNotif->execute([$_SESSION['customer_id']]);
$notifications = $stmtNotif->fetchAll();
$initials = strtoupper(substr($customer['FirstName'],0,1) . substr($customer['LastName'],0,1));
?>
<!DOCTYPE html>
<html lang="<?= $GLOBALS['CURRENT_LANG'] ?? 'en' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= clean($pageTitle ?? 'Dashboard') ?> · Asha Bank</title>
<link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
<script>const BASE_URL = "<?= BASE_URL ?>"; window.CSRF_TOKEN = "<?= csrf_token() ?>";</script>
</head>
<body>
<div class="app-shell">
  <div class="sidebar-backdrop" id="sidebarBackdrop"></div>
  <aside class="sidebar" id="sidebar">
    <div class="brand">
      <div class="brand-mark">A</div>
      <div class="brand-name">Asha<span>Bank</span></div>
    </div>

    <div class="nav-search">
      <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
      Search
      <kbd>/</kbd>
    </div>

    <div class="nav-group">
      <a class="nav-item <?= $activeNav==='dashboard'?'active':'' ?>" href="<?= BASE_URL ?>/dashboard.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg>
        <?= t('dashboard') ?>
      </a>
      <a class="nav-item <?= $activeNav==='transfer'?'active':'' ?>" href="<?= BASE_URL ?>/transfer.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        <?= t('send_money') ?>
      </a>
      <a class="nav-item <?= $activeNav==='deposit'?'active':'' ?>" href="<?= BASE_URL ?>/deposit.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>
        <?= t('deposit') ?>
      </a>
      <a class="nav-item <?= $activeNav==='withdraw'?'active':'' ?>" href="<?= BASE_URL ?>/withdraw.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><polyline points="19 12 12 19 5 12"/></svg>
        <?= t('withdraw') ?>
      </a>
      <a class="nav-item <?= $activeNav==='cards'?'active':'' ?>" href="<?= BASE_URL ?>/cards.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="1" y="4" width="22" height="16" rx="2"/><line x1="1" y1="10" x2="23" y2="10"/></svg>
        <?= t('cards') ?>
      </a>
    </div>

    <div class="nav-group">
      <div class="nav-label">Services</div>
      <a class="nav-item <?= $activeNav==='loans'?'active':'' ?>" href="<?= BASE_URL ?>/modules/loans/apply.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="21" x2="21" y2="21"/><line x1="5" y1="21" x2="5" y2="10"/><line x1="19" y1="21" x2="19" y2="10"/><polygon points="12 3 21 9 3 9"/></svg>
        Loans
      </a>
      <a class="nav-item <?= $activeNav==='bills'?'active':'' ?>" href="<?= BASE_URL ?>/modules/bills/pay.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="8" y1="13" x2="16" y2="13"/><line x1="8" y1="17" x2="16" y2="17"/></svg>
        Bill Pay
      </a>
      <a class="nav-item <?= $activeNav==='cheques'?'active':'' ?>" href="<?= BASE_URL ?>/modules/cheques/request.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="2" y="5" width="20" height="14" rx="2"/><path d="M2 10h20"/></svg>
        Cheques
      </a>
      <a class="nav-item <?= $activeNav==='feedback'?'active':'' ?>" href="<?= BASE_URL ?>/feedback.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"/></svg>
        <?= t('feedback') ?>
      </a>
    </div>

    <div class="nav-group" style="margin-bottom:0;">
      <div class="nav-label">Account</div>
      <a class="nav-item <?= $activeNav==='profile'?'active':'' ?>" href="<?= BASE_URL ?>/profile.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
        <?= t('profile') ?>
      </a>
      <a class="nav-item" href="<?= BASE_URL ?>/logout.php">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
        <?= t('logout') ?>
      </a>
    </div>

    <div class="upgrade-card">
      <h4>Go Premium</h4>
      <p>Higher limits, priority support, and zero transfer fees.</p>
      <a href="<?= BASE_URL ?>/modules/loans/apply.php" class="btn btn-primary btn-sm btn-block">Explore Plans</a>
    </div>
  </aside>

  <div class="main-col">
    <header class="topbar">
      <button class="icon-btn" id="menuToggle" aria-label="Menu">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/></svg>
      </button>
      <div class="topbar-crumb">
        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
        <?= clean($pageTitle ?? 'Dashboard') ?>
      </div>
      <div class="topbar-spacer"></div>

      <a href="?lang=<?= ($GLOBALS['CURRENT_LANG']??'en')==='en'?'bn':'en' ?>" class="icon-btn text-xs" style="width:auto;padding:0 10px;font-weight:700;" title="Switch language">
        <?= ($GLOBALS['CURRENT_LANG']??'en')==='en' ? 'বাং' : 'EN' ?>
      </a>

      <div style="position:relative;">
        <button class="icon-btn" data-dropdown-toggle="notifPanel" aria-label="Notifications">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
          <?php if ($unread > 0): ?><span class="dot-badge"></span><?php endif; ?>
        </button>
        <div class="dropdown-panel" id="notifPanel">
          <div class="dropdown-head">
            <h4>Notifications</h4>
            <?php if ($unread > 0): ?>
              <button class="btn btn-ghost btn-sm" onclick="markAllNotificationsRead()">Mark all read</button>
            <?php endif; ?>
          </div>
          <?php if (empty($notifications)): ?>
            <div class="empty-state">You're all caught up 🎉</div>
          <?php else: foreach ($notifications as $n): ?>
            <div class="notif-item <?= !$n['is_read'] ? 'unread' : '' ?>" onclick="markNotificationRead(<?= $n['notification_id'] ?>, this)">
              <span class="notif-dot <?= $n['type'] ?>"></span>
              <div class="notif-body">
                <h5><?= clean($n['title']) ?></h5>
                <p><?= clean($n['message']) ?></p>
                <div class="notif-time"><?= time_ago($n['created_at']) ?></div>
              </div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>

      <div class="user-chip">
        <div class="avatar"><?= clean($initials) ?></div>
        <span class="text-sm" style="font-weight:600;"><?= clean($customer['FirstName']) ?></span>
      </div>
    </header>

    <div class="content">
