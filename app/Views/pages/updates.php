<div class="page-head"><div class="container">
    <h1>Software Updates</h1>
    <p class="muted">Latest version updates detected from official sources.</p>
</div></div>
<div class="container">
    <?php
    $labels = ['today' => 'Updated today', 'yesterday' => 'Updated yesterday', 'this_week' => 'Earlier this week', 'this_month' => 'This month'];
    $any = false;
    foreach ($labels as $key => $label):
        $rows = $grouped[$key] ?? [];
        if (empty($rows)) continue;
        $any = true;
    ?>
        <h2 class="group-head"><?= e($label) ?></h2>
        <table class="data-table">
            <thead><tr><th>Software</th><th>Old</th><th>New</th><th>Released</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $u): ?>
                <tr>
                    <td><a href="<?= e(base_url('/software/' . $u['slug'])) ?>"><?= e($u['name']) ?></a></td>
                    <td class="muted"><?= e($u['old_version'] ?: '—') ?></td>
                    <td><strong><?= e($u['new_version'] ?: '—') ?></strong></td>
                    <td class="muted small"><?= e($u['release_date'] ?: '—') ?></td>
                    <td class="muted small"><?= e(time_ago($u['created_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endforeach; ?>
    <?php if (!$any): ?><div class="empty-state"><p>No updates recorded yet. The version checker runs on a schedule.</p></div><?php endif; ?>
</div>
