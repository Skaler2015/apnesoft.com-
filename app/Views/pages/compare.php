<?php
/** @var array $items @var array $attributes */
$fmt = function ($key, $val) {
    if ($key === 'is_open_source') return $val ? 'Yes' : 'No';
    if ($key === 'min_ram_mb') return $val ? ($val >= 1024 ? round($val/1024, 1) . ' GB' : $val . ' MB') : '—';
    if ($key === 'last_updated') return $val ? time_ago($val) : '—';
    if ($key === 'trust_score') { $b = trust_badge((int) $val); return $b['dot'] . ' ' . $val; }
    return $val !== null && $val !== '' ? $val : '—';
};
?>
<div class="page-head"><div class="container">
    <h1><?= e(implode(' vs ', array_column($items, 'name'))) ?></h1>
    <p class="muted">Side-by-side comparison from official metadata.</p>
</div></div>
<div class="container compare-scroll">
    <table class="compare-table">
        <thead>
            <tr>
                <th>Attribute</th>
                <?php foreach ($items as $it): ?>
                    <th>
                        <a href="<?= e(base_url('/software/' . $it['slug'])) ?>"><?= e($it['name']) ?></a>
                        <div><a class="btn btn-sm btn-primary" rel="nofollow noopener" href="<?= e(base_url('/download/' . $it['slug'])) ?>">Download</a></div>
                    </th>
                <?php endforeach; ?>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($attributes as $key => $label): ?>
                <tr>
                    <th scope="row"><?= e($label) ?></th>
                    <?php foreach ($items as $it): ?><td><?= e($fmt($key, $it[$key] ?? null)) ?></td><?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
