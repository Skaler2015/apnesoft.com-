<?php
use App\Core\Csrf;
/** @var bool $configured @var bool $running @var ?array $result @var int $pending @var int $enhanced @var string $model @var bool $finished */
$autoContinue = $running && $configured && !$finished;
if ($autoContinue):
?>
    <meta http-equiv="refresh" content="3;url=<?= e(base_url('/admin/ai?run=1')) ?>">
<?php endif; ?>

<div class="admin-panel">
    <h2>✨ AI content enhancer</h2>
    <p class="muted">Uses AI to rewrite each software page's description, features and pros/cons
        from its <strong>real</strong> metadata — richer, more useful pages that rank better.
        The AI only rephrases and organises facts it's given; it never invents versions,
        developers, features or security claims.</p>

    <?php if (!$configured): ?>
        <div class="flash flash-err" style="margin-top:12px">
            No Anthropic API key configured. Add your key under
            <a href="<?= e(base_url('/admin/settings')) ?>">Settings → AI enhancement</a> to enable this.
        </div>
    <?php else: ?>
        <p class="small muted">Model: <strong><?= e($model) ?></strong> ·
            <a href="<?= e(base_url('/admin/settings')) ?>">change in Settings</a></p>

        <div style="display:flex;gap:24px;margin:16px 0">
            <div><div style="font-size:1.6rem;font-weight:700"><?= number_format($enhanced) ?></div><span class="muted small">enhanced</span></div>
            <div><div style="font-size:1.6rem;font-weight:700"><?= number_format($pending) ?></div><span class="muted small">still pending</span></div>
        </div>

        <?php if ($result): ?>
            <p class="muted small">Last batch: ✨ <?= (int) $result['enhanced'] ?> enhanced,
                <?= (int) $result['failed'] ?> failed. <?= e($result['message']) ?></p>
        <?php endif; ?>

        <?php if ($finished): ?>
            <?php if ($pending === 0 && $enhanced > 0): ?>
                <div class="flash flash-ok" style="margin-top:12px">🎉 All published software enhanced!</div>
            <?php endif; ?>
            <form method="post" action="<?= e(base_url('/admin/ai/start')) ?>" style="margin-top:12px" class="inline">
                <?= Csrf::field() ?>
                <button class="btn btn-primary" <?= $pending === 0 ? 'disabled' : '' ?>>
                    <?= $pending === 0 ? 'Nothing to enhance' : 'Enhance ' . number_format($pending) . ' pending' ?>
                </button>
            </form>
        <?php elseif ($autoContinue): ?>
            <p><span class="dot dot-green"></span> Enhancing… this page updates automatically. Leave it open.</p>
            <a class="btn btn-ghost btn-sm" href="<?= e(base_url('/admin/ai')) ?>">Pause</a>
        <?php else: ?>
            <form method="post" action="<?= e(base_url('/admin/ai/start')) ?>" style="margin-top:12px" class="inline">
                <?= Csrf::field() ?>
                <button class="btn btn-primary" <?= $pending === 0 ? 'disabled' : '' ?>>
                    <?= $pending === 0 ? 'Nothing to enhance' : 'Enhance ' . number_format($pending) . ' pending items' ?>
                </button>
            </form>
        <?php endif; ?>

        <p class="muted small" style="margin-top:10px">Each item is one API call. Costs depend on your chosen model —
            Haiku is cheapest for large catalogues. You can also enhance a single item from its
            <a href="<?= e(base_url('/admin/software')) ?>">edit page</a>.</p>
    <?php endif; ?>
</div>
