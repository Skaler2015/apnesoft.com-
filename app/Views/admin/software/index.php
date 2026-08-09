<?php use App\Core\Csrf; use App\Core\View; ?>
<form method="get" class="admin-toolbar" action="<?= e(base_url('/admin/software')) ?>">
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
                <?php if ($s['status'] !== 'published'): ?>
                    <?= View::partial('admin/partials/action', ['id' => $s['id'], 'action' => 'approve', 'label' => 'Approve']) ?>
                <?php endif; ?>
                <?= View::partial('admin/partials/action', ['id' => $s['id'], 'action' => 'recheck', 'label' => 'Recheck']) ?>
                <?= View::partial('admin/partials/action', ['id' => $s['id'], 'action' => 'reject', 'label' => 'Reject']) ?>
            </td>
        </tr>
    <?php endforeach; ?>
    <?php if (empty($items)): ?><tr><td colspan="6" class="muted">No software found.</td></tr><?php endif; ?>
    </tbody>
</table>
<?= View::partial('partials/pagination', ['total' => $total, 'page' => $page, 'perPage' => $perPage, 'baseUrl' => '/admin/software']) ?>
