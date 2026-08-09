<?php
/** @var array $items */
/** @var string $emptyMessage */
$emptyMessage = $emptyMessage ?? 'No software found.';
?>
<?php if (empty($items)): ?>
    <div class="empty-state">
        <p><?= e($emptyMessage) ?></p>
    </div>
<?php else: ?>
    <div class="card-grid">
        <?php foreach ($items as $item): ?>
            <?= \App\Core\View::partial('partials/card', ['item' => $item]) ?>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
