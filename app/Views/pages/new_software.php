<?php use App\Core\View; ?>
<div class="page-head"><div class="container">
    <h1>New Software</h1>
    <p class="muted">Recently discovered and newly added apps.</p>
</div></div>
<div class="container">
    <table class="data-table">
        <thead><tr><th>Software</th><th>Developer</th><th>Version</th><th>Discovered</th><th>Trust</th></tr></thead>
        <tbody>
        <?php foreach ($items as $s): $b = trust_badge((int) $s['trust_score']); ?>
            <tr>
                <td><a href="<?= e(base_url('/software/' . $s['slug'])) ?>"><?= e($s['name']) ?></a></td>
                <td class="muted"><?= e($s['developer_name'] ?: '—') ?></td>
                <td><?= $s['version'] ? 'v' . e($s['version']) : '—' ?></td>
                <td class="muted small"><?= e(time_ago($s['discovered_at'] ?: $s['created_at'])) ?></td>
                <td><span class="trust <?= e($b['class']) ?>"><?= $b['dot'] ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($items)): ?><tr><td colspan="5" class="muted">Nothing discovered yet.</td></tr><?php endif; ?>
        </tbody>
    </table>
    <?= View::partial('partials/pagination', compact('total', 'page', 'perPage', 'baseUrl')) ?>
</div>
