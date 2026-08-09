<?php $siteName = setting('site_name', 'SoftwareHub'); ?>
<?php if ($ad = setting('ad_footer')): ?><div class="ad ad-footer container"><?= $ad ?></div><?php endif; ?>
<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-col">
            <div class="brand"><span class="brand-mark" aria-hidden="true">◆</span> <strong><?= e($siteName) ?></strong></div>
            <p class="muted"><?= e(setting('footer_text')) ?></p>
            <p class="muted small">We link to official developer &amp; authorized sources. We do not host cracked or modified software, and we make no absolute security guarantees.</p>
        </div>
        <div class="footer-col">
            <h4>Discover</h4>
            <a href="<?= e(base_url('/software')) ?>">All Software</a>
            <a href="<?= e(base_url('/new-software')) ?>">New Software</a>
            <a href="<?= e(base_url('/software-updates')) ?>">Software Updates</a>
            <a href="<?= e(base_url('/low-end-pc')) ?>">Best for Low-End PCs</a>
        </div>
        <div class="footer-col">
            <h4>Tools</h4>
            <a href="<?= e(base_url('/software-finder')) ?>">Software Finder</a>
            <a href="<?= e(base_url('/compare')) ?>">Compare Software</a>
            <a href="<?= e(base_url('/categories')) ?>">Categories</a>
        </div>
        <div class="footer-col">
            <h4>Platforms</h4>
            <a href="<?= e(base_url('/os/windows')) ?>">Windows</a>
            <a href="<?= e(base_url('/os/macos')) ?>">macOS</a>
            <a href="<?= e(base_url('/os/linux')) ?>">Linux</a>
        </div>
    </div>
    <div class="container footer-bottom">
        <span class="muted small">&copy; <?= gmdate('Y') ?> <?= e($siteName) ?>. All product names are trademarks of their respective owners.</span>
        <span class="footer-social">
            <?php if ($t = setting('social_twitter')): ?><a href="<?= e($t) ?>" rel="nofollow noopener">Twitter</a><?php endif; ?>
            <?php if ($g = setting('social_github')): ?><a href="<?= e($g) ?>" rel="nofollow noopener">GitHub</a><?php endif; ?>
        </span>
    </div>
</footer>
