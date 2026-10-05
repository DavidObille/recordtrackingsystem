<?php
require_once __DIR__ . '/NotificationService.php';

$notifications = getNotifications($con, (int)$_SESSION['user_id']);
$unreadNotificationCount = getUnreadNotificationCount($con, (int)$_SESSION['user_id']);
?>
<details class="notification-dropdown">
    <summary class="notification-bell" aria-label="Notifications, <?= $unreadNotificationCount ?> unread">
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"></path>
        </svg>
        <?php if ($unreadNotificationCount > 0): ?>
            <span class="notification-badge"><?= $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount ?></span>
        <?php endif; ?>
    </summary>

    <div class="notification-menu">
        <div class="notification-heading">
            <h2>Notifications</h2>
            <?php if ($unreadNotificationCount > 0): ?>
                <form method="post" action="notification_action.php">
                    <input type="hidden" name="action" value="read_all">
                    <button type="submit" class="notification-action">Mark all read</button>
                </form>
            <?php endif; ?>
        </div>

        <?php if ($notifications): ?>
            <ul class="notification-list">
                <?php foreach ($notifications as $notification): ?>
                    <li class="notification-item<?= (int)$notification['is_read'] === 0 ? ' is-unread' : '' ?>">
                        <p><?= htmlspecialchars($notification['message']) ?></p>
                        <time datetime="<?= htmlspecialchars($notification['created_at']) ?>">
                            <?= htmlspecialchars(date('M j, Y g:i A', strtotime($notification['created_at']))) ?>
                        </time>
                        <?php if ((int)$notification['is_read'] === 0): ?>
                            <form method="post" action="notification_action.php">
                                <input type="hidden" name="action" value="read_one">
                                <input type="hidden" name="notification_id" value="<?= (int)$notification['id'] ?>">
                                <button type="submit" class="notification-action">Mark read</button>
                            </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p class="notification-empty">You don't have any notifications yet.</p>
        <?php endif; ?>
    </div>
</details>
