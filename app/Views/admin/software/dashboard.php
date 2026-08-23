<?php
/** @var int $total @var int $today @var int $week @var array $perOs @var array $spark @var array $queue */
$vals = array_values($spark);
$max = $vals ? max($vals) : 1; $max = $max ?: 1;
$labels = ['windows' => '🪟 Windows', 'macos' => '🖥️ Mac', 'ios' => '📱 iOS', 'android' => '🤖 Android'];
$osMax = $perOs ? max(array_map('intval', $perOs)) : 1; $osMax = $osMax ?: 1;
?>
<div class="admin-edit-head">
    <a class="btn btn-sm btn-ghost" href="<?= e(base_url('/admin/software/bulk')) ?>">← Bulk publish</a>
    <h2 style="margin:0 0 0 4px">📊 Publishing dashboard</h2>
</div>

<div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin:14px 0">
    <div class="admin-panel" style="text-align:center"><div style="font-size:2rem;font-weight:800"><?= number_format($today) ?></div><span class="muted small">Published today</span></div>
    <div class="admin-panel" style="text-align:center"><div style="font-size:2rem;font-weight:800"><?= number_format($total) ?></div><span class="muted small">Total software</span></div>
    <div class="admin-panel" style="text-align:center"><div style="font-size:2rem;font-weight:800"><?= number_format($week) ?></div><span class="muted small">This week</span></div>
</div>

<div class="admin-panel">
    <h3 style="margin:0 0 12px">Last 14 days</h3>
    <?php if ($vals): ?>
        <div style="display:flex;align-items:flex-end;gap:5px;height:120px">
            <?php foreach ($spark as $d => $c): $h = (int) round($c / $max * 110) + 2; ?>
                <div title="<?= e($d) ?>: <?= (int) $c ?>" style="flex:1;height:<?= $h ?>px;background:linear-gradient(180deg,var(--brand),var(--brand-2));border-radius:4px 4px 0 0"></div>
            <?php endforeach; ?>
        </div>
    <?php else: ?><p class="muted">No software added in the last 14 days yet.</p><?php endif; ?>
</div>

<div class="admin-panel">
    <h3 style="margin:0 0 12px">Per platform</h3>
    <?php foreach ($labels as $slug => $label): $c = (int) ($perOs[$slug] ?? 0); $w = (int) round($c / $osMax * 100); ?>
        <div style="display:flex;align-items:center;gap:12px;margin-bottom:10px">
            <span style="width:120px;font-weight:600"><?= $label ?></span>
            <div style="flex:1;height:14px;background:var(--surface-2);border-radius:8px;overflow:hidden">
                <div style="height:100%;width:<?= $w ?>%;background:linear-gradient(90deg,var(--brand),var(--brand-2))"></div>
            </div>
            <span class="muted small" style="width:60px;text-align:right"><?= number_format($c) ?></span>
        </div>
    <?php endforeach; ?>
</div>

<?php if (($queue['pending'] ?? 0) > 0 || ($queue['done'] ?? 0) > 0): ?>
<div class="admin-panel">
    <h3 style="margin:0 0 8px">⚡ Background queue</h3>
    <p style="margin:0"><strong><?= number_format($queue['pending'] ?? 0) ?></strong> waiting ·
        <?= number_format($queue['done'] ?? 0) ?> done · <?= number_format($queue['duplicate'] ?? 0) ?> duplicates ·
        <?= number_format($queue['failed'] ?? 0) ?> failed</p>
    <p class="muted small" style="margin:6px 0 0">The hourly cron publishes ~30 queued items per run.</p>
</div>
<?php endif; ?>
