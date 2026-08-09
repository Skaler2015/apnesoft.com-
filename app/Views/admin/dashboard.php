<?php
/** @var array $cards @var array $growth */
$cardDefs = [
    'total_software' => 'Total Software', 'published' => 'Published', 'new_today' => 'New Today',
    'updated_today' => 'Updated Today', 'pending_review' => 'Pending Review', 'rejected' => 'Rejected',
    'broken_links' => 'Broken Links (7d)', 'failed_crawls' => 'Failed Crawls (7d)', 'active_sources' => 'Active Sources',
];
$max = max(1, ...array_map(fn($g) => (int) $g['c'], $growth ?: [['c' => 1]]));
?>
<div class="stat-grid">
    <?php foreach ($cardDefs as $key => $label): ?>
        <div class="stat-card">
            <span class="stat-value"><?= number_format((int) ($cards[$key] ?? 0)) ?></span>
            <span class="stat-label"><?= e($label) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<div class="admin-columns">
    <div class="admin-panel">
        <h2>Software added (14 days)</h2>
        <div class="bar-chart">
            <?php foreach ($growth as $g): ?>
                <div class="bar" style="height: <?= max(4, (int) round((int) $g['c'] / $max * 100)) ?>%" title="<?= e($g['d']) ?>: <?= (int) $g['c'] ?>"></div>
            <?php endforeach; ?>
            <?php if (empty($growth)): ?><p class="muted">No data yet.</p><?php endif; ?>
        </div>
    </div>
    <div class="admin-panel">
        <h2>Automation status</h2>
        <table class="admin-table compact">
            <?php foreach ($jobs as $j): ?>
                <tr>
                    <td><?= e($j['name']) ?></td>
                    <td><span class="dot dot-<?= $j['status'] === 'error' ? 'red' : ($j['enabled'] ? 'green' : 'gray') ?>"></span><?= e($j['status']) ?></td>
                    <td class="muted small"><?= e($j['last_run_at'] ? time_ago($j['last_run_at']) : 'never') ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (empty($jobs)): ?><tr><td class="muted">No jobs registered.</td></tr><?php endif; ?>
        </table>
        <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/automation')) ?>">Open Automation Center →</a>
    </div>
</div>

<div class="admin-columns">
    <div class="admin-panel">
        <h2>Most viewed</h2>
        <table class="admin-table compact">
            <?php foreach ($topViewed as $t): ?>
                <tr><td><a href="<?= e(base_url('/software/' . $t['slug'])) ?>" target="_blank"><?= e($t['name']) ?></a></td><td class="muted"><?= number_format((int) $t['views']) ?> views</td></tr>
            <?php endforeach; ?>
        </table>
    </div>
    <div class="admin-panel">
        <h2>Recent notifications</h2>
        <ul class="notif-list">
            <?php foreach ($notifications as $n): ?>
                <li class="notif notif-<?= e($n['level']) ?>"><strong><?= e($n['title']) ?></strong><span class="muted small"><?= e(time_ago($n['created_at'])) ?></span></li>
            <?php endforeach; ?>
            <?php if (empty($notifications)): ?><li class="muted">No notifications.</li><?php endif; ?>
        </ul>
    </div>
</div>
