<?php use App\Core\Csrf; /** @var array $cats @var ?string $panelLabel */
$osLabels = ['windows' => '🪟 Windows', 'macos' => '🍎 macOS', 'ios' => '📱 iOS', 'android' => '🤖 Android'];
?>
<div class="admin-edit-head">
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/software')) ?>">← Back</a>
    <h2 style="margin:0 0 0 4px">🗂 Manage categories</h2>
</div>

<div class="flash flash-ok" style="margin:0 0 14px"><span>📌</span><div>नई category <strong><?= e($panelLabel ?: 'current') ?></strong> panel में जुड़ेगी और सिर्फ़ उसी platform में दिखेगी. दूसरे platform के लिए वहाँ का panel खोलकर जोड़ें.</div></div>

<form method="post" action="<?= e(base_url('/admin/categories')) ?>" class="admin-toolbar" style="margin-bottom:18px">
    <?= Csrf::field() ?>
    <input name="name" placeholder="New <?= e($panelLabel ?: '') ?> category name…" maxlength="120" required style="flex:1;min-width:200px">
    <button class="btn btn-primary" type="submit">➕ Add category</button>
</form>

<div class="admin-table-wrap">
<table class="admin-table">
    <thead><tr><th>Category</th><th>Platform</th><th>Slug</th><th style="text-align:center">Software</th><th style="text-align:right">Actions</th></tr></thead>
    <tbody>
    <?php if (empty($cats)): ?>
        <tr><td colspan="5" class="muted">No categories yet — add one above.</td></tr>
    <?php endif; ?>
    <?php foreach ($cats as $c): ?>
        <tr>
            <td>
                <form method="post" action="<?= e(base_url('/admin/categories/' . $c['id'] . '/rename')) ?>" style="display:flex;gap:6px;align-items:center">
                    <?= Csrf::field() ?>
                    <input name="name" value="<?= e($c['name']) ?>" maxlength="120" style="min-width:160px">
                    <button class="btn btn-xs btn-ghost" type="submit" title="Save name">💾</button>
                </form>
            </td>
            <td><span class="chip"><?= e($osLabels[$c['os_slug'] ?? ''] ?? ($c['os_slug'] ?: '— all')) ?></span></td>
            <td><code><?= e($c['slug']) ?></code></td>
            <td style="text-align:center"><span class="chip"><?= (int) $c['cnt'] ?></span></td>
            <td style="text-align:right">
                <div class="row-actions" style="justify-content:flex-end">
                    <a class="btn btn-xs btn-ghost" href="<?= e(base_url('/category/' . $c['slug'])) ?>" target="_blank" rel="noopener">View ↗</a>
                    <form method="post" action="<?= e(base_url('/admin/categories/' . $c['id'] . '/delete')) ?>" class="inline"
                          onsubmit="return confirm('Delete “<?= e($c['name']) ?>”? Its <?= (int) $c['cnt'] ?> software will become uncategorised.')">
                        <?= Csrf::field() ?>
                        <button class="btn btn-xs btn-ghost" type="submit" style="color:var(--red)">Delete</button>
                    </form>
                </div>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>
<p class="muted small" style="margin-top:12px">Categories are sorted by name. Deleting a category does not delete its software — those become uncategorised.</p>
