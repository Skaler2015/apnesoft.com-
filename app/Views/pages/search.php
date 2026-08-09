<?php use App\Core\View; ?>
<div class="page-head"><div class="container">
    <form class="search-page-form" action="<?= e(base_url('/search')) ?>" method="get">
        <input type="search" name="q" value="<?= e($query) ?>" placeholder="Search software, category or what you need…" autocomplete="off" data-suggest>
        <button class="btn btn-primary">Search</button>
        <div class="suggest-box" data-suggest-box hidden></div>
    </form>
    <?php if ($query !== ''): ?><p class="muted"><?= count($results) ?> result<?= count($results) === 1 ? '' : 's' ?> for “<?= e($query) ?>”</p><?php endif; ?>
</div></div>
<div class="container">
    <?php if ($query === ''): ?>
        <div class="empty-state"><p>Type what you're looking for — e.g. “free PDF editor”, “video editor for low-end PC”, “open source antivirus”.</p></div>
    <?php elseif (empty($results)): ?>
        <div class="empty-state">
            <p>No matches for “<?= e($query) ?>”.</p>
            <a class="btn btn-ghost" href="<?= e(base_url('/software-finder')) ?>">Try the Software Finder</a>
        </div>
    <?php else: ?>
        <?php foreach ($grouped as $groupName => $groupItems): ?>
            <h2 class="group-head"><?= e($groupName) ?></h2>
            <?= View::partial('partials/grid', ['items' => $groupItems]) ?>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
