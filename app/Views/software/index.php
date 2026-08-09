<?php
use App\Core\View;
/** @var array $items @var array $filters @var array $categories */
$f = $filters;
?>
<div class="page-head">
    <div class="container">
        <h1><?= e($title) ?></h1>
        <p class="muted"><?= number_format($total) ?> result<?= $total === 1 ? '' : 's' ?></p>
    </div>
</div>

<div class="container listing-layout">
    <aside class="filters" data-filters>
        <form method="get" action="<?= e(base_url($baseUrl)) ?>" class="filter-form">
            <?php if (!empty($f['q'])): ?><input type="hidden" name="q" value="<?= e($f['q']) ?>"><?php endif; ?>
            <div class="filter-group">
                <h4>Sort</h4>
                <select name="sort" onchange="this.form.submit()">
                    <?php foreach (['popular'=>'Most popular','newest'=>'Newest','updated'=>'Recently updated','name'=>'Name A–Z','trust'=>'Trust level'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= ($f['sort'] ?? '') === $k ? 'selected' : '' ?>><?= $v ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <h4>Category</h4>
                <select name="category">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $c): ?>
                        <option value="<?= (int) $c['id'] ?>" <?= (int) ($f['category_id'] ?? 0) === (int) $c['id'] ? 'selected' : '' ?>>
                            <?= e($c['name']) ?> (<?= (int) ($c['software_count'] ?? 0) ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <h4>Operating System</h4>
                <?php foreach (['windows'=>'Windows','macos'=>'macOS','linux'=>'Linux','android'=>'Android'] as $slug=>$name): ?>
                    <label class="radio"><input type="radio" name="os" value="<?= $slug ?>" <?= ($f['os_slug'] ?? '') === $slug ? 'checked' : '' ?>> <?= $name ?></label>
                <?php endforeach; ?>
                <label class="radio"><input type="radio" name="os" value="" <?= ($f['os_slug'] ?? '') === '' ? 'checked' : '' ?>> Any</label>
            </div>
            <div class="filter-group">
                <h4>License &amp; Price</h4>
                <?php foreach (['free'=>'Free','open_source'=>'Open Source','freemium'=>'Freemium','paid'=>'Paid'] as $k=>$v): ?>
                    <label class="radio"><input type="radio" name="price" value="<?= $k ?>" <?= ($f['price_type'] ?? '') === $k ? 'checked' : '' ?>> <?= $v ?></label>
                <?php endforeach; ?>
                <label class="check"><input type="checkbox" name="open_source" value="1" <?= !empty($f['open_source']) ? 'checked' : '' ?>> Open source only</label>
            </div>
            <div class="filter-group">
                <h4>Max RAM required</h4>
                <select name="max_ram">
                    <option value="">Any</option>
                    <?php foreach ([2048=>'2 GB',4096=>'4 GB',8192=>'8 GB',16384=>'16 GB'] as $k=>$v): ?>
                        <option value="<?= $k ?>" <?= (int) ($f['max_ram'] ?? 0) === $k ? 'selected' : '' ?>><?= $v ?> or less</option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="filter-group">
                <h4>Trust level</h4>
                <select name="trust">
                    <option value="">Any</option>
                    <option value="70" <?= (int) ($f['trust_min'] ?? 0) === 70 ? 'selected' : '' ?>>🟢 Highly Verified</option>
                    <option value="40" <?= (int) ($f['trust_min'] ?? 0) === 40 ? 'selected' : '' ?>>🟡 Needs Review+</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary btn-block">Apply filters</button>
            <a href="<?= e(base_url($baseUrl)) ?>" class="btn btn-ghost btn-block">Reset</a>
        </form>
    </aside>

    <div class="listing-results">
        <button class="btn btn-ghost filter-toggle" data-filter-toggle>Filters</button>
        <?= View::partial('partials/grid', ['items' => $items]) ?>
        <?= View::partial('partials/pagination', compact('total', 'page', 'perPage', 'baseUrl')) ?>
    </div>
</div>
