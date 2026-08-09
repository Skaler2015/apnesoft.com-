<?php
/** @var int $id @var string $action @var string $label */
$confirm = in_array($action, ['delete', 'reject'], true) ? ' onsubmit="return confirm(\'Are you sure?\')"' : '';
?>
<form method="post" action="<?= e(base_url('/admin/software/' . $id . '/action')) ?>" class="inline"<?= $confirm ?>>
    <?= \App\Core\Csrf::field() ?>
    <input type="hidden" name="action" value="<?= e($action) ?>">
    <button class="btn btn-xs btn-ghost"><?= e($label) ?></button>
</form>
