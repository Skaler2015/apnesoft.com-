<div class="page-head"><div class="container"><h1><?= e($title) ?></h1><p class="muted">Browse software by what it does.</p></div></div>
<div class="container">
    <div class="cat-grid">
        <?php foreach ($categories as $c): ?>
            <a class="cat-card" href="<?= e(base_url('/category/' . $c['slug'])) ?>">
                <span class="cat-icon" aria-hidden="true"><?= e($c['icon'] ?: '▤') ?></span>
                <span class="cat-name"><?= e($c['name']) ?></span>
                <span class="muted small"><?= (int) ($c['software_count'] ?? 0) ?> apps</span>
            </a>
        <?php endforeach; ?>
        <?php if (empty($categories)): ?><p class="muted">No categories yet.</p><?php endif; ?>
    </div>
</div>
