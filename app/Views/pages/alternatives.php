<?php use App\Core\View; ?>
<div class="page-head"><div class="container">
    <nav class="breadcrumb"><a href="<?= e(base_url('/software/' . $software['slug'])) ?>"><?= e($software['name']) ?></a> › <span>Alternatives</span></nav>
    <h1>Best <?= e($software['name']) ?> Alternatives</h1>
    <p class="muted">Similar software by category, features and platform.</p>
</div></div>
<div class="container">
    <?= View::partial('partials/grid', ['items' => $alternatives, 'emptyMessage' => 'No strong alternatives found yet.']) ?>
</div>
