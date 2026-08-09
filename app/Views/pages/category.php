<?php use App\Core\View; ?>
<div class="page-head">
    <div class="container">
        <nav class="breadcrumb"><a href="<?= e(base_url('/categories')) ?>">Categories</a> › <span><?= e($category['name']) ?></span></nav>
        <h1><?= e($category['name']) ?> Software</h1>
        <?php if (!empty($category['description'])): ?><p class="muted"><?= e($category['description']) ?></p><?php endif; ?>
        <p class="muted small"><?= number_format($total) ?> apps</p>
    </div>
</div>
<div class="container">
    <?php if (!empty($children)): ?>
    <div class="subcat-row">
        <?php foreach ($children as $child): ?>
            <a class="pill" href="<?= e(base_url('/category/' . $child['slug'])) ?>"><?= e($child['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?= View::partial('partials/grid', ['items' => $items, 'emptyMessage' => 'No software in this category yet — our crawler may still be discovering it.']) ?>
    <?= View::partial('partials/pagination', compact('total', 'page', 'perPage', 'baseUrl')) ?>
</div>
