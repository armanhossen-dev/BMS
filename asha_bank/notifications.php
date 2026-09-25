<?php
require_once 'config/db.php';
requireAuth('client');

$userId = $_SESSION['user_id'];

// Mark all as read
if (isset($_GET['mark_all'])) {
    $pdo->prepare("UPDATE NOTIFICATIONS SET is_read=1 WHERE customer_id=?")->execute([$userId]);
    setToast('All notifications marked as read.', 'success');
    redirect('notifications.php');
}

// Mark single as read
if (isset($_GET['read'])) {
    $pdo->prepare("UPDATE NOTIFICATIONS SET is_read=1 WHERE id=? AND customer_id=?")->execute([(int)$_GET['read'], $userId]);
    redirect('notifications.php');
}

// Get all notifications
$notifs = $pdo->prepare("SELECT * FROM NOTIFICATIONS WHERE customer_id=? ORDER BY created_at DESC LIMIT 100");
$notifs->execute([$userId]);
$notifications = $notifs->fetchAll();
$unreadCount   = count(array_filter($notifications, fn($n) => !$n['is_read']));

$pageTitle  = 'Notifications';
$activePage = 'notifications';
include 'includes/header.php';
?>

<div class="page-content">
    <div class="page-header" style="display:flex;align-items:center;justify-content:space-between;gap:16px;">
        <div>
            <h1>Notifications</h1>
            <p><?= $unreadCount ?> unread <?= $unreadCount === 1 ? 'notification' : 'notifications' ?></p>
        </div>
        <?php if ($unreadCount > 0): ?>
        <a href="notifications.php?mark_all=1" class="btn btn-outline btn-sm">Mark all as read</a>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
    <div class="card">
        <div class="empty-state" style="padding:80px 0;">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/></svg>
            <h3>No notifications yet</h3>
            <p>You're all caught up! Notifications will appear here.</p>
        </div>
    </div>
    <?php else: ?>
    <div class="card reveal">
        <?php foreach ($notifications as $n):
            $colors  = ['success'=>'var(--green)','warning'=>'var(--amber)','danger'=>'var(--red)','info'=>'var(--blue)'];
            $bgColors= ['success'=>'var(--green-light)','warning'=>'var(--amber-light)','danger'=>'var(--red-light)','info'=>'var(--blue-light)'];
            $color   = $colors[$n['type'] ?? 'info']   ?? 'var(--blue)';
            $bg      = $bgColors[$n['type'] ?? 'info'] ?? 'var(--blue-light)';
            $isUnread= !$n['is_read'];
        ?>
        <div style="display:flex;align-items:flex-start;gap:16px;padding:18px 24px;border-bottom:1px solid var(--cream-3);<?= $isUnread ? 'background:var(--surface-2);' : '' ?> transition:background .2s;">
            <div style="width:36px;height:36px;border-radius:10px;background:<?= $bg ?>;display:flex;align-items:center;justify-content:center;flex-shrink:0;margin-top:2px;">
                <?php
                $icons = [
                    'success' => '<polyline points="20 6 9 17 4 12"/>',
                    'warning' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
                    'danger'  => '<circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>',
                    'info'    => '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>',
                ];
                $path = $icons[$n['type'] ?? 'info'] ?? $icons['info'];
                ?>
                <svg viewBox="0 0 24 24" fill="none" stroke="<?= $color ?>" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="17" height="17"><?= $path ?></svg>
            </div>
            <div style="flex:1;min-width:0;">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:4px;">
                    <span style="font-size:14px;font-weight:600;color:var(--ink);"><?= e($n['title']) ?></span>
                    <?php if ($isUnread): ?>
                    <span style="width:7px;height:7px;border-radius:50%;background:var(--red);flex-shrink:0;"></span>
                    <?php endif; ?>
                </div>
                <p style="font-size:13px;color:var(--ink-muted);margin-bottom:6px;"><?= e($n['message']) ?></p>
                <span style="font-size:11px;color:var(--ink-faint);"><?= timeAgo($n['created_at']) ?> &bull; <?= formatDate($n['created_at'], 'd M Y, g:i A') ?></span>
            </div>
            <?php if ($isUnread): ?>
            <a href="notifications.php?read=<?= $n['id'] ?>" class="btn btn-ghost btn-sm" aria-label="Mark as read">✓</a>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>
