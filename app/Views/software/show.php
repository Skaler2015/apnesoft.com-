<?php
/** @var array $software */
use App\Core\View;
$s = $software;
$lb = license_badge($s);
$downloadLabel = download_label($s);
$hasDownload = !empty($s['official_download_url']) || !empty($s['official_website']);
?>
<div class="container detail-breadcrumb">
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= e(base_url('/')) ?>">Home</a> ›
        <a href="<?= e(base_url('/software')) ?>">Software</a> ›
        <?php if ($category): ?><a href="<?= e(base_url('/category/' . $category['slug'])) ?>"><?= e($category['name']) ?></a> ›<?php endif; ?>
        <span><?= e($s['name']) ?></span>
    </nav>
</div>

<section class="detail-hero">
    <div class="container detail-hero-inner">
        <div class="detail-logo" aria-hidden="true">
            <?php if (!empty($s['logo'])): ?><img src="<?= e($s['logo']) ?>" alt="" width="72" height="72">
            <?php else: ?><span><?= e(strtoupper(mb_substr($s['name'], 0, 1))) ?></span><?php endif; ?>
        </div>
        <div class="detail-headline">
            <h1><?= e($s['name']) ?></h1>
            <p class="detail-short"><?= e($s['short_description'] ?: str_excerpt($s['long_description'], 160)) ?></p>
            <div class="detail-badges">
                <?php if ($lb['label']): ?><span class="license-badge <?= e($lb['class']) ?>"><?= e($lb['label']) ?></span><?php endif; ?>
                <?php if (!empty($s['version'])): ?><span class="chip">v<?= e($s['version']) ?></span><?php endif; ?>
                <?php if (!empty($s['developer_name'])): ?><span class="chip chip-ghost">By <?= e($s['developer_name']) ?></span><?php endif; ?>
                <?php if (!empty($s['last_updated'])): ?><span class="muted small">Updated <?= e(time_ago($s['last_updated'])) ?></span><?php endif; ?>
            </div>
        </div>
        <div class="detail-actions">
            <?php if ($hasDownload): ?>
                <a class="btn btn-primary btn-lg" rel="nofollow noopener" href="<?= e(base_url('/download/' . $s['slug'])) ?>"><?= e($downloadLabel) ?></a>
            <?php endif; ?>
            <?php if (!empty($s['developer_website']) || !empty($s['official_website'])): ?>
                <a class="btn btn-ghost" rel="nofollow noopener" target="_blank" href="<?= e($s['developer_website'] ?: $s['official_website']) ?>">Visit Developer</a>
            <?php endif; ?>
            <div class="detail-sub-actions">
                <a href="<?= e(base_url('/compare?ids=' . $s['id'])) ?>">Compare</a> ·
                <a href="<?= e(base_url('/alternatives/' . $s['slug'])) ?>">Alternatives</a>
            </div>
            <p class="muted small">This links to the official / authorized source. We don't host the installer.</p>
        </div>
    </div>
</section>

<div class="container detail-body">
    <div class="detail-main">
        <!-- Info cards -->
        <div class="info-cards">
            <?php
            $info = [
                'Latest Version' => $s['version'] ?: 'Not specified',
                'Developer'      => $s['developer_name'] ?: 'Not specified',
                'License'        => $s['license_type'] ?: 'Not specified',
                'Operating System' => $s['operating_system'] ?: 'Not specified',
                'File Size'      => $s['file_size'] ?: 'Not specified',
                'Architecture'   => $s['architecture'] ?: 'Not specified',
                'Release Date'   => $s['release_date'] ?: 'Not specified',
                'Last Checked'   => $s['last_checked_at'] ? time_ago($s['last_checked_at']) : 'Not yet',
            ];
            foreach ($info as $label => $value): ?>
                <div class="info-card"><span class="info-label"><?= e($label) ?></span><span class="info-value"><?= e($value) ?></span></div>
            <?php endforeach; ?>
        </div>

        <?php if (!empty($s['long_description'])): ?>
        <section class="detail-section"><h2>About <?= e($s['name']) ?></h2>
            <div class="prose"><?= nl2br(e($s['long_description'])) ?></div>
        </section>
        <?php endif; ?>

        <?php if (!empty($features)): ?>
        <section class="detail-section"><h2>Features</h2>
            <ul class="feature-list">
                <?php foreach ($features as $f): ?><li><?= e($f['label']) ?></li><?php endforeach; ?>
            </ul>
        </section>
        <?php endif; ?>

        <?php if (!empty($pros) || !empty($cons)): ?>
        <section class="detail-section"><h2>Pros &amp; Cons</h2>
            <div class="proscons">
                <div class="pros"><h3>Pros</h3><ul><?php foreach ($pros as $p): ?><li>✔ <?= e($p['label']) ?></li><?php endforeach; ?></ul></div>
                <div class="cons"><h3>Cons</h3><ul><?php foreach ($cons as $c): ?><li>✘ <?= e($c['label']) ?></li><?php endforeach; ?></ul></div>
            </div>
        </section>
        <?php endif; ?>

        <?php if (!empty($s['changelog'])): ?>
        <section class="detail-section"><h2>What's New<?= $s['version'] ? ' in v' . e($s['version']) : '' ?></h2>
            <div class="prose changelog"><?= nl2br(e(str_excerpt($s['changelog'], 1200))) ?></div>
        </section>
        <?php endif; ?>

        <?php if (!empty($s['minimum_requirements'])): ?>
        <section class="detail-section"><h2>System Requirements</h2>
            <div class="prose"><?= nl2br(e($s['minimum_requirements'])) ?></div>
        </section>
        <?php endif; ?>

        <?php if (!empty($screenshots)): ?>
        <section class="detail-section"><h2>Screenshots</h2>
            <div class="screenshot-grid">
                <?php foreach ($screenshots as $sc): ?>
                    <img src="<?= e($sc['url']) ?>" alt="<?= e($sc['caption'] ?: $s['name'] . ' screenshot') ?>" loading="lazy">
                <?php endforeach; ?>
            </div>
        </section>
        <?php endif; ?>

        <section class="detail-section"><h2>Download Information</h2>
            <div class="download-info">
                <p>Download <?= e($s['name']) ?><?= $s['version'] ? ' v' . e($s['version']) : '' ?> from the
                    <?= str_contains((string) $s['source_type'], 'github') ? 'official GitHub releases' : 'official developer website' ?>.</p>
                <?php if ($hasDownload): ?>
                    <a class="btn btn-primary" rel="nofollow noopener" href="<?= e(base_url('/download/' . $s['slug'])) ?>"><?= e($downloadLabel) ?></a>
                <?php else: ?>
                    <p class="muted">Official download link not available yet.</p>
                <?php endif; ?>
                <p class="muted small">Last checked: <?= e($s['last_checked_at'] ?: 'not yet') ?> (UTC)</p>
            </div>
        </section>

        <?php if (count($versions) > 1): ?>
        <section class="detail-section"><h2>Older Versions</h2>
            <table class="version-table">
                <thead><tr><th>Version</th><th>Released</th><th>Status</th></tr></thead>
                <tbody>
                <?php foreach ($versions as $v): ?>
                    <tr>
                        <td>v<?= e($v['version']) ?></td>
                        <td><?= e($v['release_date'] ?: '—') ?></td>
                        <td><?= $v['is_current'] ? '<span class="chip chip-soft">Current</span>' : '<span class="muted">Previous</span>' ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="muted small">We only link to legitimate, authorized archives or official sources.</p>
        </section>
        <?php endif; ?>

        <?php if ($ad = setting('ad_software_page')): ?><div class="ad ad-software"><?= $ad ?></div><?php endif; ?>
    </div>

    <aside class="detail-aside">
        <?php if (!empty($alternatives)): ?>
        <div class="aside-box">
            <h3>Best Alternatives</h3>
            <?php foreach ($alternatives as $alt): ?>
                <a class="mini-row" href="<?= e(base_url('/software/' . $alt['slug'])) ?>">
                    <span><?= e($alt['name']) ?></span>
                    <?php if (!empty($alt['similarity'])): ?><span class="muted small"><?= (int) $alt['similarity'] ?>% match</span><?php endif; ?>
                </a>
            <?php endforeach; ?>
            <a class="see-all small" href="<?= e(base_url('/alternatives/' . $s['slug'])) ?>">All alternatives →</a>
        </div>
        <?php endif; ?>

        <?php if (!empty($similar)): ?>
        <div class="aside-box">
            <h3>Similar Software</h3>
            <?php foreach ($similar as $sim): ?>
                <a class="mini-row" href="<?= e(base_url('/software/' . $sim['slug'])) ?>"><span><?= e($sim['name']) ?></span></a>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ($ad = setting('ad_sidebar')): ?><div class="ad ad-sidebar"><?= $ad ?></div><?php endif; ?>
    </aside>
</div>
