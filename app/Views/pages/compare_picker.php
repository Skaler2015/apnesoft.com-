<div class="page-head"><div class="container">
    <h1>Compare Software</h1>
    <p class="muted">Pick 2–4 products to compare side by side.</p>
</div></div>
<div class="container">
    <form action="<?= e(base_url('/compare')) ?>" method="get" id="compare-form">
        <input type="hidden" name="ids" data-compare-ids>
        <p class="muted small"><span data-compare-count>0</span> selected (max 4)</p>
        <div class="card-grid compare-picker">
            <?php foreach ($popular as $item): ?>
                <label class="pick-card">
                    <input type="checkbox" value="<?= (int) $item['id'] ?>" data-compare-pick>
                    <span class="pick-name"><?= e($item['name']) ?></span>
                    <span class="muted small"><?= e(str_excerpt($item['short_description'], 60)) ?></span>
                </label>
            <?php endforeach; ?>
        </div>
        <button type="submit" class="btn btn-primary btn-lg" data-compare-submit disabled>Compare selected</button>
    </form>
</div>
