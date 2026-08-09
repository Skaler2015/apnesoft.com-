<?php $siteName = setting('site_name', 'SoftwareHub'); ?>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(base_url('/')) ?>">
            <?php if ($logo = setting('logo')): ?>
                <img src="<?= e($logo) ?>" alt="<?= e($siteName) ?>" height="28">
            <?php else: ?>
                <span class="brand-mark" aria-hidden="true">◆</span>
            <?php endif; ?>
            <span class="brand-name"><?= e($siteName) ?></span>
        </a>

        <button class="nav-toggle" aria-label="Toggle menu" aria-expanded="false" data-nav-toggle>
            <span></span><span></span><span></span>
        </button>

        <nav class="site-nav" data-nav>
            <a href="<?= e(base_url('/software')) ?>">Software</a>
            <a href="<?= e(base_url('/categories')) ?>">Categories</a>
            <a href="<?= e(base_url('/new-software')) ?>">New</a>
            <a href="<?= e(base_url('/software-updates')) ?>">Updates</a>
            <a href="<?= e(base_url('/compare')) ?>">Compare</a>
            <a href="<?= e(base_url('/low-end-pc')) ?>">Low-End PC</a>
            <a href="<?= e(base_url('/software-finder')) ?>" class="nav-cta">Software Finder</a>
        </nav>

        <div class="header-actions">
            <form class="header-search" action="<?= e(base_url('/search')) ?>" method="get" role="search">
                <input type="search" name="q" placeholder="Search…" aria-label="Search software" autocomplete="off" data-suggest>
                <div class="suggest-box" data-suggest-box hidden></div>
            </form>
            <button class="theme-toggle" data-theme-toggle aria-label="Toggle dark mode" title="Toggle theme">◐</button>
        </div>
    </div>
</header>
