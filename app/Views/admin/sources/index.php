<?php use App\Core\Csrf; ?>
<div class="admin-toolbar">
    <a class="btn btn-sm btn-primary" href="<?= e(base_url('/admin/sources/new')) ?>">+ Add source</a>
</div>
<table class="admin-table">
    <thead><tr><th>Name</th><th>Type</th><th>Status</th><th>Trust</th><th>Last sync</th><th>Errors</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($sources as $src): ?>
        <tr>
            <td><a href="<?= e(base_url('/admin/sources/' . $src['id'] . '/edit')) ?>"><?= e($src['name']) ?></a></td>
            <td><span class="chip chip-ghost"><?= e($src['source_type']) ?></span></td>
            <td><span class="status status-<?= $src['status'] === 'active' ? 'published' : 'disabled' ?>"><?= e($src['status']) ?></span></td>
            <td><?= (int) $src['trust_score'] ?></td>
            <td class="muted small"><?= e($src['last_sync'] ? time_ago($src['last_sync']) : 'never') ?></td>
            <td><?= (int) $src['error_count'] ? '<span class="dot dot-red"></span>' . (int) $src['error_count'] : '0' ?></td>
            <td class="row-actions">
                <form method="post" action="<?= e(base_url('/admin/sources/' . $src['id'] . '/run')) ?>" class="inline">
                    <?= Csrf::field() ?><button class="btn btn-xs btn-ghost">Run now</button>
                </form>
                <form method="post" action="<?= e(base_url('/admin/sources/' . $src['id'] . '/delete')) ?>" class="inline" onsubmit="return confirm('Delete source?')">
                    <?= Csrf::field() ?><button class="btn btn-xs btn-ghost">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($sources)): ?><tr><td colspan="7" class="muted">No sources yet. Add a GitHub, RSS, Winget or website source to begin discovery.</td></tr><?php endif; ?>
    </tbody>
</table>
