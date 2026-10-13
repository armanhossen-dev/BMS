<?php
require_once 'config/db.php';
requireAuth('client');

$userId = $_SESSION['user_id'];

// Mark all as read
if (isset($_GET['mark_all'])) {
    $pdo->prepare("UPDATE NOTIFICATIONS SET is_read = 1 WHERE customer_id = ?")->execute([$userId]);
    setToast('All notifications marked as read.', 'success');
    redirect('notifications.php');
}

// Mark single as read
if (isset($_GET['read'])) {
    $notificationId = (int)$_GET['read'];
    $pdo->prepare("UPDATE NOTIFICATIONS SET is_read = 1 WHERE notification_id = ? AND customer_id = ?")
        ->execute([$notificationId, $userId]);
    redirect('notifications.php');
}

// Get all notifications
$notifs = $pdo->prepare("SELECT * FROM NOTIFICATIONS WHERE customer_id = ? ORDER BY created_at DESC LIMIT 100");
$notifs->execute([$userId]);
$notifications = $notifs->fetchAll();
$unreadCount   = count(array_filter($notifications, fn($n) => !$n['is_read']));

$pageTitle  = 'Notifications';
$activePage = 'notifications';
include 'includes/header.php';
?>

<div class="page-content">
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:16px;margin-bottom:24px;">
        <div>
            <h1 style="margin:0 0 4px;font-size:24px;font-weight:700;">Notifications</h1>
            <p style="margin:0;font-size:14px;color:var(--text-muted);"><?= $unreadCount ?> unread <?= $unreadCount === 1 ? 'notification' : 'notifications' ?></p>
        </div>
        <?php if ($unreadCount > 0): ?>
        <a href="notifications.php?mark_all=1" class="btn btn-outline btn-sm" style="display:inline-flex;align-items:center;gap:6px;">
            <i class='bx bx-check-double'></i> Mark all as read
        </a>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
    <div class="card reveal" style="border-radius:14px;overflow:hidden;">
        <div class="empty-state" style="padding:60px 20px;text-align:center;">
            <div style="width:58px;height:58px;border-radius:50%;background:rgba(24,95,165,0.08);color:var(--primary);display:flex;align-items:center;justify-content:center;margin:0 auto 16px;font-size:28px;">
                <i class='bx bx-bell-off'></i>
            </div>
            <h3 style="margin:0 0 6px;font-size:16px;font-weight:600;">No notifications yet</h3>
            <p style="margin:0;font-size:13.5px;color:var(--text-muted);max-width:360px;margin-inline:auto;">You're all caught up! New account alerts, transaction updates, and security notices will appear here.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="card reveal" style="border-radius:14px;overflow:hidden;box-shadow:var(--shadow-sm);border:1px solid var(--border-subtle);">
        <?php foreach ($notifications as $n):
            $type = $n['type'] ?? 'info';

            $typeIcons = [
                'success' => 'bx-check-circle',
                'warning' => 'bx-error-alt',
                'danger'  => 'bx-error-circle',
                'info'    => 'bx-bell'
            ];
            $typeColors = [
                'success' => '#16A34A',
                'warning' => '#F59E0B',
                'danger'  => '#EF4444',
                'info'    => '#3B82F6'
            ];
            $typeBg = [
                'success' => 'rgba(22, 163, 74, 0.1)',
                'warning' => 'rgba(245, 158, 11, 0.1)',
                'danger'  => 'rgba(239, 68, 68, 0.1)',
                'info'    => 'rgba(59, 130, 246, 0.1)'
            ];

            $icon     = $typeIcons[$type]  ?? 'bx-bell';
            $color    = $typeColors[$type] ?? '#3B82F6';
            $bg       = $typeBg[$type]     ?? 'rgba(59, 130, 246, 0.1)';
            $isUnread = empty($n['is_read']);
            $notifId  = (int)($n['notification_id'] ?? $n['id'] ?? 0);
        ?>
        <div style="display:flex;align-items:flex-start;gap:16px;padding:18px 24px;border-bottom:1px solid var(--border-subtle);<?= $isUnread ? 'background:rgba(24,95,165,0.04);' : 'background:var(--bg-card);' ?> transition:background .2s;">
            <!-- Category Icon -->
            <div style="width:38px;height:38px;border-radius:10px;background:<?= $bg ?>;color:<?= $color ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;font-size:20px;margin-top:2px;">
                <i class='bx <?= $icon ?>'></i>
            </div>

            <!-- Content Area -->
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:8px;margin-bottom:4px;">
                    <span style="font-size:14px;font-weight:600;color:var(--text-primary);"><?= e($n['title']) ?></span>
                    <?php if ($isUnread): ?>
                    <span style="width:7px;height:7px;border-radius:50%;background:#EF4444;display:inline-block;flex-shrink:0;" title="Unread"></span>
                    <?php endif; ?>
                </div>
                <p style="font-size:13.5px;line-height:1.5;color:var(--text-secondary);margin:0 0 6px;word-break:break-word;"><?= e($n['message']) ?></p>
                <div style="font-size:11.5px;color:var(--text-muted);display:flex;align-items:center;gap:6px;">
                    <i class='bx bx-time-five' style="font-size:12px;"></i>
                    <span><?= timeAgo($n['created_at']) ?></span>
                    <span>&bull;</span>
                    <span><?= formatDate($n['created_at'], 'd M Y, g:i A') ?></span>
                </div>
            </div>

            <!-- Read Action -->
            <div style="margin-left:auto;flex-shrink:0;">
                <?php if ($isUnread): ?>
                <a href="notifications.php?read=<?= $notifId ?>" class="btn btn-outline btn-sm" style="display:inline-flex;align-items:center;gap:5px;font-size:12px;padding:5px 12px;border-radius:8px;" aria-label="Mark as read" title="Mark as read">
                    <i class='bx bx-check'></i> Mark read
                </a>
                <?php else: ?>
                <span style="font-size:12px;color:var(--text-muted);display:inline-flex;align-items:center;gap:4px;padding:4px 8px;">
                    <i class='bx bx-check-double' style="color:#16A34A;font-size:16px;"></i> Read
                </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
