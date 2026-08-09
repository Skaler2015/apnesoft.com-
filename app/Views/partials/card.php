<?php
/** @var array $item */
$badge = trust_badge((int) ($item['trust_score'] ?? 0));
$price = $item['price_type'] ?? '';
$priceLabel = match ($price) {
    'free' => 'Free',
    'open_source' => 'Open Source',
    'freemium' => 'Freemium',
    'paid' => 'Paid',
    'trial' => 'Trial',
    default => '',
};
?>
<article class="card">
    <a class="card-link" href="<?= e(base_url('/software/' . $item['slug'])) ?>">
        <div class="card-head">
            <div class="card-logo" aria-hidden="true">
                <?php if (!empty($item['logo'])): ?>
                    <img src="<?= e($item['logo']) ?>" alt="" loading="lazy" width="40" height="40">
                <?php else: ?>
                    <span><?= e(strtoupper(mb_substr($item['name'], 0, 1))) ?></span>
                <?php endif; ?>
            </div>
            <div class="card-title">
                <h3><?= e($item['name']) ?></h3>
                <?php if (!empty($item['developer_name'])): ?>
                    <span class="muted small"><?= e($item['developer_name']) ?></span>
                <?php endif; ?>
            </div>
        </div>
        <p class="card-desc"><?= e(str_excerpt($item['short_description'] ?: $item['long_description'], 96)) ?></p>
        <div class="card-meta">
            <?php if (!empty($item['version'])): ?><span class="chip">v<?= e($item['version']) ?></span><?php endif; ?>
            <?php if ($priceLabel): ?><span class="chip chip-soft"><?= e($priceLabel) ?></span><?php endif; ?>
            <?php if (!empty($item['operating_system'])): ?><span class="chip chip-ghost"><?= e(str_excerpt($item['operating_system'], 22)) ?></span><?php endif; ?>
        </div>
        <div class="card-foot">
            <span class="trust <?= e($badge['class']) ?>" title="<?= e($badge['label']) ?>"><?= $badge['dot'] ?> <?= e($badge['label']) ?></span>
            <?php if (!empty($item['last_updated'])): ?>
                <span class="muted small">Updated <?= e(time_ago($item['last_updated'])) ?></span>
            <?php endif; ?>
        </div>
    </a>
</article>
