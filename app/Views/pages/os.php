<?php use App\Core\View; ?>
<div class="page-head"><div class="container">
    <h1><?= e($os['name']) ?> Software</h1>
    <p class="muted"><?= number_format($total) ?> apps for <?= e($os['name']) ?></p>
</div></div>
<div class="container">
    <?= View::partial('partials/grid', ['items' => $items]) ?>
    <?= View::partial('partials/pagination', compact('total', 'page', 'perPage', 'baseUrl')) ?>
</div>
