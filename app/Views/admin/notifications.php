<div class="admin-panel">
    <ul class="notif-list full">
        <?php foreach ($notifications as $n): ?>
            <li class="notif notif-<?= e($n['level']) ?>">
                <div><strong><?= e($n['title']) ?></strong><?php if (!empty($n['body'])): ?><br><span class="muted small"><?= e($n['body']) ?></span><?php endif; ?></div>
                <span class="muted small"><?= e(time_ago($n['created_at'])) ?></span>
            </li>
        <?php endforeach; ?>
        <?php if (empty($notifications)): ?><li class="muted">No notifications.</li><?php endif; ?>
    </ul>
</div>
