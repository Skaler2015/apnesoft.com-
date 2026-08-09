<?php
/** @var int $total @var int $page @var int $perPage @var string $baseUrl */
$pages = (int) ceil(($total ?: 0) / max(1, $perPage));
if ($pages <= 1) return;
$query = $_GET;
$build = function (int $p) use ($baseUrl, $query) {
    $query['page'] = $p;
    return base_url($baseUrl) . '?' . http_build_query($query);
};
$start = max(1, $page - 2);
$end = min($pages, $page + 2);
?>
<nav class="pagination" aria-label="Pagination">
    <?php if ($page > 1): ?><a href="<?= e($build($page - 1)) ?>" class="page-btn">← Prev</a><?php endif; ?>
    <?php if ($start > 1): ?><a href="<?= e($build(1)) ?>" class="page-btn">1</a><span class="page-gap">…</span><?php endif; ?>
    <?php for ($i = $start; $i <= $end; $i++): ?>
        <a href="<?= e($build($i)) ?>" class="page-btn <?= $i === $page ? 'is-active' : '' ?>"><?= $i ?></a>
    <?php endfor; ?>
    <?php if ($end < $pages): ?><span class="page-gap">…</span><a href="<?= e($build($pages)) ?>" class="page-btn"><?= $pages ?></a><?php endif; ?>
    <?php if ($page < $pages): ?><a href="<?= e($build($page + 1)) ?>" class="page-btn">Next →</a><?php endif; ?>
</nav>
