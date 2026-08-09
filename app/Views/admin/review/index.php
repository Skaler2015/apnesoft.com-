<?php use App\Core\Csrf; use App\Core\View; ?>
<div class="admin-panel">
    <h2>Software pending review <span class="muted">(<?= count($pending) ?>)</span></h2>
    <table class="admin-table">
        <thead><tr><th>Name</th><th>Developer</th><th>Trust</th><th>Version</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($pending as $s): $b = trust_badge((int) $s['trust_score']); ?>
            <tr>
                <td><a href="<?= e(base_url('/admin/software/' . $s['id'] . '/edit')) ?>"><?= e($s['name']) ?></a></td>
                <td class="muted"><?= e($s['developer_name'] ?: '—') ?></td>
                <td><?= $b['dot'] ?> <?= (int) $s['trust_score'] ?></td>
                <td><?= $s['version'] ? 'v' . e($s['version']) : '—' ?></td>
                <td class="row-actions">
                    <?= View::partial('admin/partials/action', ['id' => $s['id'], 'action' => 'approve', 'label' => 'Approve']) ?>
                    <?= View::partial('admin/partials/action', ['id' => $s['id'], 'action' => 'reject', 'label' => 'Reject']) ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($pending)): ?><tr><td colspan="5" class="muted">Nothing awaiting review. 🎉</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>

<div class="admin-panel">
    <h2>Possible duplicates <span class="muted">(<?= count($duplicates) ?>)</span></h2>
    <table class="admin-table">
        <thead><tr><th>Candidate</th><th>Existing match</th><th>Confidence</th><th>Reason</th><th>Decision</th></tr></thead>
        <tbody>
        <?php foreach ($duplicates as $d): ?>
            <tr>
                <td><?= e($d['software_name']) ?></td>
                <td><?= e($d['match_name']) ?></td>
                <td><?= (int) $d['confidence'] ?>%</td>
                <td class="muted small"><?= e($d['reason']) ?></td>
                <td class="row-actions">
                    <?php foreach (['merge' => 'Merge', 'ignore' => 'Ignore'] as $dec => $lbl): ?>
                    <form method="post" action="<?= e(base_url('/admin/review/duplicate/' . $d['id'])) ?>" class="inline">
                        <?= Csrf::field() ?><input type="hidden" name="decision" value="<?= $dec ?>"><button class="btn btn-xs btn-ghost"><?= $lbl ?></button>
                    </form>
                    <?php endforeach; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($duplicates)): ?><tr><td colspan="5" class="muted">No pending duplicates.</td></tr><?php endif; ?>
        </tbody>
    </table>
</div>
