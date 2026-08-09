<?php use App\Core\Csrf;
$runnable = ['discover'=>'Discovery','github_sync'=>'GitHub Sync','winget_sync'=>'Winget Sync','rss_sync'=>'RSS Sync','link_check'=>'Link Checker','seo_update'=>'SEO Generator','sitemap'=>'Sitemap Generator'];
$jobsByKey = [];
foreach ($jobs as $j) { $jobsByKey[$j['key']] = $j; }
?>
<div class="admin-panel">
    <h2>Jobs</h2>
    <table class="admin-table">
        <thead><tr><th>Job</th><th>Status</th><th>Last run</th><th>Duration</th><th>Processed</th><th>Errors</th><th>Run</th></tr></thead>
        <tbody>
        <?php foreach ($runnable as $key => $label): $j = $jobsByKey[$key] ?? null; ?>
            <tr>
                <td><strong><?= e($label) ?></strong><br><span class="muted small"><?= e($j['schedule'] ?? '') ?></span></td>
                <td><span class="dot dot-<?= ($j['status'] ?? '') === 'error' ? 'red' : (($j['enabled'] ?? 1) ? 'green' : 'gray') ?>"></span><?= e($j['status'] ?? 'idle') ?></td>
                <td class="muted small"><?= e(!empty($j['last_run_at']) ? time_ago($j['last_run_at']) : 'never') ?></td>
                <td class="muted small"><?= isset($j['last_duration']) ? (int) $j['last_duration'] . 's' : '—' ?></td>
                <td><?= (int) ($j['last_processed'] ?? 0) ?></td>
                <td><?= (int) ($j['last_errors'] ?? 0) ?></td>
                <td class="row-actions">
                    <form method="post" action="<?= e(base_url('/admin/automation/run')) ?>" class="inline">
                        <?= Csrf::field() ?><input type="hidden" name="key" value="<?= $key ?>"><button class="btn btn-xs btn-primary">Run now</button>
                    </form>
                    <?php if ($j): ?>
                    <form method="post" action="<?= e(base_url('/admin/automation/toggle')) ?>" class="inline">
                        <?= Csrf::field() ?><input type="hidden" name="key" value="<?= $key ?>"><button class="btn btn-xs btn-ghost"><?= ($j['enabled'] ?? 1) ? 'Pause' : 'Enable' ?></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="admin-panel">
    <h2>Recent run logs</h2>
    <table class="admin-table compact">
        <thead><tr><th>Job</th><th>Status</th><th>Processed</th><th>Created</th><th>Updated</th><th>Failed</th><th>When</th></tr></thead>
        <tbody>
        <?php foreach ($logs as $l): ?>
            <tr>
                <td><?= e($l['job']) ?></td>
                <td><span class="dot dot-<?= $l['status'] === 'error' ? 'red' : 'green' ?>"></span><?= e($l['status']) ?></td>
                <td><?= (int) $l['processed'] ?></td><td><?= (int) $l['created'] ?></td><td><?= (int) $l['updated'] ?></td><td><?= (int) $l['failed'] ?></td>
                <td class="muted small"><?= e(time_ago($l['created_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($logs)): ?><tr><td colspan="7" class="muted">No runs yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
