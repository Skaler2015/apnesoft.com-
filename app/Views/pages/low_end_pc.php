<?php use App\Core\View; ?>
<div class="page-head"><div class="container">
    <h1>Best Software for Low-End PCs</h1>
    <p class="muted">Lightweight apps that run smoothly on modest hardware.</p>
    <form method="get" class="inline-filter" action="<?= e(base_url('/low-end-pc')) ?>">
        <label>Available RAM:
            <select name="max_ram" onchange="this.form.submit()">
                <?php foreach ([2048=>'2 GB',4096=>'4 GB',8192=>'8 GB'] as $k=>$v): ?>
                    <option value="<?= $k ?>" <?= (int) $maxRam === $k ? 'selected' : '' ?>><?= $v ?> or less</option>
                <?php endforeach; ?>
            </select>
        </label>
    </form>
</div></div>
<div class="container">
    <?= View::partial('partials/grid', ['items' => $items, 'emptyMessage' => 'No lightweight apps recorded for this RAM tier yet.']) ?>
    <?= View::partial('partials/pagination', compact('total', 'page', 'perPage', 'baseUrl')) ?>
</div>
