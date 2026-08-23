<?php use App\Core\Csrf; use App\Core\View; $os = $os ?? ''; $counts = $counts ?? []; ?>
<div class="admin-toolbar">
    <a class="btn btn-sm btn-primary" href="<?= e(base_url('/admin/software/new' . ($os ? '?os=' . $os : ''))) ?>">+ Add Software</a>
</div>

<div class="plat-tabs" style="display:flex;gap:6px;flex-wrap:wrap;margin:4px 0 14px">
    <?php
    $tabs = ['all' => 'All', 'windows' => '🪟 Windows', 'macos' => '🖥️ Mac', 'ios' => '📱 iOS', 'android' => '🤖 Android'];
    foreach ($tabs as $slug => $label):
        $active = ($slug === 'all') ? ($os === '') : ($os === $slug);
        $count = $slug === 'all' ? ($counts['all'] ?? null) : ($counts[$slug] ?? null);
        $url = base_url('/admin/software?os=' . $slug);
    ?>
        <a href="<?= e($url) ?>" class="btn btn-sm <?= $active ? 'btn-primary' : 'btn-ghost' ?>">
            <?= e($label) ?><?php if ($count !== null): ?> <span class="muted">(<?= number_format($count) ?>)</span><?php endif; ?>
        </a>
    <?php endforeach; ?>
</div>
<form method="get" class="admin-toolbar" action="<?= e(base_url('/admin/software')) ?>">
    <input type="hidden" name="os" value="<?= e($os) ?>">
    <input type="search" name="q" value="<?= e($q) ?>" placeholder="Search name / developer…">
    <select name="status" onchange="this.form.submit()">
        <?php foreach (['' => 'All statuses', 'published' => 'Published', 'review' => 'Review', 'draft' => 'Draft', 'rejected' => 'Rejected', 'disabled' => 'Disabled'] as $k => $v): ?>
            <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-sm btn-primary">Filter</button>
</form>

<table class="admin-table">
    <thead><tr><th>Name</th><th>Version</th><th>Trust</th><th>Status</th><th>Updated</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($items as $s): $b = trust_badge((int) $s['trust_score']); ?>
        <tr>
            <td><a href="<?= e(base_url('/admin/software/' . $s['id'] . '/edit')) ?>"><?= e($s['name']) ?></a><br><span class="muted small"><?= e($s['developer_name'] ?: '—') ?></span></td>
            <td><?= $s['version'] ? 'v' . e($s['version']) : '—' ?></td>
            <td><?= $b['dot'] ?> <?= (int) $s['trust_score'] ?></td>
            <td><span class="status status-<?= e($s['status']) ?>"><?= e($s['status']) ?></span></td>
            <td class="muted small"><?= e(time_ago($s['updated_at'])) ?></td>
            <td class="row-actions">
                <a class="btn btn-xs btn-ghost" href="<?= e(base_url('/admin/software/' . $s['id'] . '/edit')) ?>">Edit</a>
                <?php if ($s['status'] !== 'published'): ?>
                    <?= View::partial('admin/partials/action', ['id' => $s['id'], 'action' => 'approve', 'label' => 'Approve']) ?>
                <?php else: ?>
                    <?= View::partial('admin/partials/action', ['id' => $s['id'], 'action' => 'disable', 'label' => 'Disable']) ?>
                <?php endif; ?>
                <?= View::partial('admin/partials/action', ['id' => $s['id'], 'action' => 'delete', 'label' => 'Delete']) ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($items)): ?><tr><td colspan="6" class="muted">No software found.</td></tr><?php endif; ?>
    </tbody>
</table>
<?= View::partial('partials/pagination', ['total' => $total, 'page' => $page, 'perPage' => $perPage, 'baseUrl' => '/admin/software']) ?>
