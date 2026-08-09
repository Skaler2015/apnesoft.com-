<?php
/** @var string $heading @var array $items @var ?string $moreUrl @var ?string $subtitle */
if (empty($items)) return;
?>
<section class="home-section">
    <div class="container">
        <div class="section-head">
            <div>
                <h2><?= e($heading) ?></h2>
                <?php if (!empty($subtitle)): ?><p class="muted"><?= e($subtitle) ?></p><?php endif; ?>
            </div>
            <?php if (!empty($moreUrl)): ?>
                <a class="see-all" href="<?= e(base_url($moreUrl)) ?>">See all →</a>
            <?php endif; ?>
        </div>
        <div class="card-grid">
            <?php foreach (array_slice($items, 0, 8) as $item): ?>
                <?= \App\Core\View::partial('partials/card', ['item' => $item]) ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>
