<?php
use App\Core\View;
?>
<section class="hero">
    <div class="container hero-inner">
        <h1>Find the Right Software for Your PC</h1>
        <p class="hero-sub">Discover, compare and download trusted software from official sources.</p>
        <form class="hero-search" action="<?= e(base_url('/search')) ?>" method="get" role="search">
            <input type="search" name="q" placeholder="Search software, category or what you need…" aria-label="Search" autocomplete="off" data-suggest>
            <button type="submit" class="btn btn-primary">Search Software</button>
            <div class="suggest-box" data-suggest-box hidden></div>
        </form>
        <div class="hero-examples">
            <span class="muted small">Try:</span>
            <?php foreach (['PDF editor', 'Video editor', 'Screen recorder', 'Antivirus', 'Windows cleaner', 'Free photo editor'] as $ex): ?>
                <a class="pill" href="<?= e(base_url('/search?q=' . urlencode($ex))) ?>"><?= e($ex) ?></a>
            <?php endforeach; ?>
        </div>
        <div class="hero-cta">
            <a class="btn btn-ghost" href="<?= e(base_url('/software-finder')) ?>">✨ Find My Software</a>
        </div>
    </div>
</section>

<?php if (!empty($trending)): ?>
<section class="home-section trending">
    <div class="container">
        <div class="section-head"><h2>Trending Categories</h2></div>
        <div class="chip-row">
            <?php foreach ($trending as $c): ?>
                <a class="cat-pill" href="<?= e(base_url('/category/' . $c['slug'])) ?>">
                    <?= e($c['name']) ?> <span class="muted small"><?= (int) ($c['software_count'] ?? 0) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<?= View::partial('partials/section', ['heading' => 'Popular Software', 'items' => $popular, 'moreUrl' => '/software?sort=popular']) ?>
<?= View::partial('partials/section', ['heading' => 'Recently Updated', 'items' => $recentlyUpdated, 'moreUrl' => '/software?sort=updated']) ?>
<?= View::partial('partials/section', ['heading' => 'New Software', 'items' => $newest, 'moreUrl' => '/new-software']) ?>

<?php if (!empty($categorySections)): ?>
<div class="container"><div class="section-head"><h2>Popular Categories</h2><a class="see-all" href="<?= e(base_url('/categories')) ?>">Browse all →</a></div></div>
<?php foreach ($categorySections as $sec): ?>
    <?= View::partial('partials/section', [
        'heading' => $sec['category']['name'],
        'items'   => $sec['items'],
        'moreUrl' => '/category/' . $sec['category']['slug'],
    ]) ?>
<?php endforeach; ?>
<?php endif; ?>

<?php if ($ad = setting('ad_incontent')): ?><div class="ad ad-incontent container"><?= $ad ?></div><?php endif; ?>

<?= View::partial('partials/section', ['heading' => 'Free Software', 'items' => $free, 'moreUrl' => '/software?price=free']) ?>
<?= View::partial('partials/section', ['heading' => 'Open Source', 'items' => $openSource, 'moreUrl' => '/software?open_source=1']) ?>
<?= View::partial('partials/section', ['heading' => 'Windows Software', 'items' => $windows, 'moreUrl' => '/os/windows']) ?>
<?= View::partial('partials/section', ['heading' => 'macOS Software', 'items' => $macos, 'moreUrl' => '/os/macos']) ?>
<?= View::partial('partials/section', ['heading' => 'Linux Software', 'items' => $linux, 'moreUrl' => '/os/linux']) ?>
<?= View::partial('partials/section', ['heading' => 'Best Software for Low-End PCs', 'items' => $lowEnd, 'moreUrl' => '/low-end-pc', 'subtitle' => 'Lightweight apps that run smoothly on modest hardware']) ?>

<?php if (!empty($updates)): ?>
<section class="home-section">
    <div class="container">
        <div class="section-head"><div><h2>Latest Software Updates</h2></div><a class="see-all" href="<?= e(base_url('/software-updates')) ?>">See all →</a></div>
        <div class="update-list">
            <?php foreach (array_slice($updates, 0, 8) as $u): ?>
                <a class="update-row" href="<?= e(base_url('/software/' . $u['slug'])) ?>">
                    <span class="update-name"><?= e($u['name']) ?></span>
                    <span class="update-ver">
                        <?php if (!empty($u['old_version'])): ?><span class="muted"><?= e($u['old_version']) ?></span> → <?php endif; ?>
                        <strong><?= e($u['new_version']) ?></strong>
                    </span>
                    <span class="muted small"><?= e(time_ago($u['created_at'])) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php endif; ?>

<section class="finder-cta">
    <div class="container finder-cta-inner">
        <div>
            <h2>Not sure what you need?</h2>
            <p>Answer a few quick questions and we'll recommend the best software for your PC, budget and skill level.</p>
        </div>
        <a class="btn btn-primary btn-lg" href="<?= e(base_url('/software-finder')) ?>">Launch Software Finder</a>
    </div>
</section>
