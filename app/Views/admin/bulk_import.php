<?php
use App\Core\Csrf;
/** @var bool $running @var ?array $result @var array $progress @var int $target */
$done = (int) $progress['done'];
$target = (int) $target;
$pct = $target > 0 ? min(100, (int) round($done / $target * 100)) : 0;
$finished = $result['finished'] ?? ($done >= $target && $done > 0);
// Auto-continue while running and not finished.
$autoContinue = $running && !$finished;
if ($autoContinue):
    // Refresh this same page to run the next batch. ~8s keeps us under GitHub's
    // unauthenticated search rate limit; faster with a token.
?>
    <meta http-equiv="refresh" content="8;url=<?= e(base_url('/admin/bulk-import?run=1')) ?>">
<?php endif; ?>

<div class="admin-panel">
    <h2>Bulk import real software from GitHub</h2>
    <p class="muted">Imports popular open-source projects with real names, descriptions,
        stars, licenses and official links — published automatically. Genuine content only,
        never fabricated entries.</p>

    <div style="margin:18px 0">
        <div style="display:flex;justify-content:space-between;margin-bottom:6px">
            <strong><?= number_format($done) ?> / <?= number_format($target) ?> imported</strong>
            <span class="muted"><?= $pct ?>%</span>
        </div>
        <div style="height:16px;background:var(--surface-2);border-radius:10px;overflow:hidden;border:1px solid var(--border)">
            <div style="height:100%;width:<?= $pct ?>%;background:linear-gradient(90deg,var(--brand),var(--brand-2));transition:width .4s"></div>
        </div>
    </div>

    <?php if ($result): ?>
        <p class="muted small">Last batch: +<?= (int) $result['created'] ?> new,
            <?= (int) $result['skipped'] ?> already present.
            <?= e($result['message']) ?></p>
    <?php endif; ?>

    <?php if ($finished && $done > 0): ?>
        <div class="flash flash-ok" style="margin-top:12px">🎉 Import complete — <?= number_format($done) ?> software published!</div>
        <a class="btn btn-primary" href="<?= e(base_url('/admin/software')) ?>">View software</a>
        <a class="btn btn-ghost" href="<?= e(base_url('/')) ?>" target="_blank">Open site ↗</a>

    <?php elseif ($autoContinue): ?>
        <p><span class="dot dot-green"></span> Importing… this page updates automatically every few seconds. You can leave it open.</p>
        <a class="btn btn-ghost btn-sm" href="<?= e(base_url('/admin/bulk-import')) ?>">Pause</a>

    <?php else: ?>
        <form method="post" action="<?= e(base_url('/admin/bulk-import/start')) ?>" style="margin-top:12px;display:flex;gap:10px;align-items:flex-end">
            <?= Csrf::field() ?>
            <label style="display:flex;flex-direction:column;gap:4px;font-size:.85rem;font-weight:600">How many to import?
                <input type="number" name="target" value="<?= $target ?: 2000 ?>" min="10" max="10000" style="padding:.5em;border:1px solid var(--border);border-radius:8px;background:var(--surface-2);color:var(--text);width:140px">
            </label>
            <button class="btn btn-primary" type="submit"><?= $done > 0 ? 'Restart import' : 'Start import' ?></button>
            <?php if ($done > 0 && !$finished): ?>
                <a class="btn btn-ghost" href="<?= e(base_url('/admin/bulk-import?run=1')) ?>">Resume</a>
            <?php endif; ?>
        </form>
        <p class="muted small" style="margin-top:10px">Tip: for faster, more reliable imports, add a free GitHub token to
            <code>GITHUB_TOKEN</code> in your <code>.env</code> (raises the API limit from 10 to 30 searches/min).</p>
    <?php endif; ?>
</div>

<div class="admin-panel">
    <h2>Import by platform (official app catalogs)</h2>
    <p class="muted">Pull real apps from official multi-platform catalogs. Each click imports a batch
        (~150). The hourly cron also rotates through these automatically.</p>
    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:12px">
        <?php
        $catButtons = [
            'popular'    => '⭐ Popular apps',
            'chocolatey' => '🪟 Windows (Chocolatey)',
            'fdroid'     => '🤖 Android (F-Droid)',
            'homebrew'   => '🍎 macOS (Homebrew)',
            'flathub'    => '🐧 Linux (Flathub)',
        ];
        foreach ($catButtons as $src => $label): ?>
            <form method="post" action="<?= e(base_url('/admin/bulk-import/catalog')) ?>" class="inline">
                <?= Csrf::field() ?>
                <input type="hidden" name="source" value="<?= e($src) ?>">
                <button class="btn btn-sm <?= $src === 'popular' ? 'btn-primary' : 'btn-ghost' ?>"><?= e($label) ?></button>
            </form>
        <?php endforeach; ?>
    </div>
    <p class="muted small" style="margin-top:8px">Popular Play Store apps (WhatsApp, Instagram…) can be added via
        <a href="<?= e(base_url('/admin/software/new')) ?>">+ Add Software</a> with their official Play Store link —
        the Play Store itself has no public API to import from.</p>
</div>

<div class="admin-panel">
    <h2>Automatic hourly discovery</h2>
    <p class="muted">When on, the hourly cron keeps importing ~250 fresh real open-source
        apps from GitHub every hour — the catalog grows by itself, hands-off.</p>
    <p style="margin:10px 0"><strong><?= number_format((int) ($total ?? 0)) ?></strong> software currently published.</p>
    <form method="post" action="<?= e(base_url('/admin/bulk-import/toggle')) ?>" class="inline">
        <?= Csrf::field() ?>
        <input type="hidden" name="enable" value="<?= !empty($autoDiscovery) ? '0' : '1' ?>">
        <?php if (!empty($autoDiscovery)): ?>
            <span class="status status-published">● ON</span>
            <button class="btn btn-sm btn-ghost">Turn off</button>
        <?php else: ?>
            <span class="status status-disabled">● OFF</span>
            <button class="btn btn-sm btn-primary">Turn on</button>
        <?php endif; ?>
    </form>
</div>
