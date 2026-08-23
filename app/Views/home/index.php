<?php
/** @var array $latest @var array $popular @var array $categorySections @var string $platform */
$icon = static function (array $it): string {
    if (!empty($it['logo'])) {
        return '<img src="' . e($it['logo']) . '" alt="" loading="lazy" width="22" height="22">';
    }
    return '<span>' . e(strtoupper(mb_substr($it['name'], 0, 1))) . '</span>';
};
?>
<section class="fh-band">
    <div class="container">
        <h1>Download &amp; Discover the Best <?= e($platform ?: '') ?> Software, Apps &amp; Games</h1>
    </div>
</section>

<div class="container fh-wrap">
    <?php if ($ad = setting('ad_header')): ?><div class="ad ad-header"><?= $ad ?></div><?php endif; ?>

    <div class="fh-top">
        <section class="fh-box">
            <h2 class="fh-h">Latest Software Releases</h2>
            <ul class="fh-latest">
                <?php foreach ($latest as $s): $d = $s['last_updated'] ?? $s['discovered_at'] ?? null; ?>
                    <li>
                        <span class="fh-when"><?= $d ? e(date('d M', strtotime((string) $d))) : '' ?></span>
                        <span class="fh-ico"><?= $icon($s) ?></span>
                        <a href="<?= e(base_url('/software/' . $s['slug'])) ?>"><?= e($s['name']) ?><?= !empty($s['version']) ? ' ' . e($s['version']) : '' ?></a>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($latest)): ?><li class="muted small">No software yet.</li><?php endif; ?>
            </ul>
            <a class="fh-more" href="<?= e(base_url('/new-software')) ?>">More Latest Software »</a>
        </section>

        <section class="fh-box">
            <h2 class="fh-h">Most Popular Downloads</h2>
            <ol class="fh-popular">
                <?php foreach ($popular as $s): ?>
                    <li>
                        <span class="fh-ico"><?= $icon($s) ?></span>
                        <a href="<?= e(base_url('/software/' . $s['slug'])) ?>"><?= e($s['name']) ?><?= !empty($s['version']) ? ' ' . e($s['version']) : '' ?></a>
                    </li>
                <?php endforeach; ?>
                <?php if (empty($popular)): ?><li class="muted small">No software yet.</li><?php endif; ?>
            </ol>
            <a class="fh-more" href="<?= e(base_url('/software?sort=popular')) ?>">More Popular Software »</a>
        </section>
    </div>

    <?php if (!empty($categorySections)): ?>
    <div class="fh-cats">
        <?php foreach ($categorySections as $sec): $c = $sec['category']; ?>
            <section class="fh-cat">
                <h3 class="fh-cat-h"><a href="<?= e(base_url('/category/' . $c['slug'])) ?>"><?= e($c['name']) ?></a></h3>
                <ul>
                    <?php foreach ($sec['items'] as $it): ?>
                        <li>
                            <span class="fh-ico"><?= $icon($it) ?></span>
                            <a href="<?= e(base_url('/software/' . $it['slug'])) ?>">
                                <span class="fh-name"><?= e($it['name']) ?></span>
                                <span class="fh-sub"><?= e(str_excerpt($it['short_description'] ?: ($it['developer_name'] ?: ''), 42)) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <a class="fh-more" href="<?= e(base_url('/category/' . $c['slug'])) ?>">View More »</a>
            </section>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <p class="muted" style="text-align:center;padding:40px 0">No <?= e($platform ?: '') ?> software published yet. The catalogue fills up automatically every hour.</p>
    <?php endif; ?>

    <?php if ($ad = setting('ad_footer')): ?><div class="ad ad-footer"><?= $ad ?></div><?php endif; ?>
</div>
