<?php
/** @var string $content */
$admin = $_admin ?? \App\Core\Auth::user();
$unread = $_unread ?? 0;
$ok = \App\Core\Session::flash('ok');
$err = \App\Core\Session::flash('err');
$path = $_SERVER['REQUEST_URI'] ?? '';
// Minimal menu — only software management for now. The other tools (Dashboard,
// Bulk Import, AI Enhancer, Review, Sources, Automation, Settings) still exist
// and are reachable by URL; they were removed from the menu on request and can
// be restored here later.
$nav = [
    '/admin/platform'     => ['Platforms', '▚'],
    '/admin/software/new' => ['Add Software', '➕'],
    '/admin/software/discover' => ['Discover', '🧭'],
    '/admin/software/bulk' => ['Bulk publish', '🚀'],
    '/admin/software'     => ['All Software', '▤'],
];
// Highlight only the most specific (longest) matching nav item.
$activeHref = '';
foreach ($nav as $href => $_x) {
    if ($href === '/admin') {
        if ($path === '/admin' || $path === '/admin/') { $activeHref = $href; }
    } elseif (str_starts_with($path, $href) && strlen($href) > strlen($activeHref)) {
        $activeHref = $href;
    }
}
?><!doctype html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title><?= e($title ?? 'Admin') ?> — Admin</title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/admin.css')) ?>">
</head>
<body class="admin-body">
<div class="admin-shell">
    <aside class="admin-sidebar">
        <a class="admin-brand" href="<?= e(base_url('/admin')) ?>">◆ <?= e(setting('site_name')) ?></a>
        <nav class="admin-nav">
            <?php foreach ($nav as $href => [$label, $icon]): ?>
                <a href="<?= e(base_url($href)) ?>" class="<?= $href === $activeHref ? 'is-active' : '' ?>"><span class="ico"><?= $icon ?></span> <?= e($label) ?></a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-nav-foot">
            <a href="<?= e(base_url('/admin/notifications')) ?>">🔔 Notifications <?php if ($unread): ?><span class="badge"><?= (int) $unread ?></span><?php endif; ?></a>
            <a href="<?= e(base_url('/')) ?>" target="_blank">↗ View site</a>
        </div>
    </aside>
    <div class="admin-main">
        <header class="admin-top">
            <h1><?= e($title ?? '') ?></h1>
            <div class="admin-user">
                <?php $curPlat = \App\Controllers\Admin\PlatformController::current(); ?>
                <?php if ($curPlat): ?>
                    <a href="<?= e(base_url('/admin/platform')) ?>" class="plat-pill" title="Switch platform panel">
                        <strong><?= e($curPlat['label']) ?></strong> panel · ⇄ switch
                    </a>
                <?php else: ?>
                    <a href="<?= e(base_url('/admin/platform')) ?>" class="plat-pill">⇄ Choose platform</a>
                <?php endif; ?>
                <span><?= e($admin['name'] ?? 'Admin') ?> · <span class="muted"><?= e($admin['role'] ?? '') ?></span></span>
                <form method="post" action="<?= e(base_url('/admin/logout')) ?>" class="inline">
                    <?= \App\Core\Csrf::field() ?>
                    <button class="btn btn-sm btn-ghost">Log out</button>
                </form>
            </div>
        </header>
        <?php if ($ok): ?><div class="flash flash-ok"><?= e($ok) ?></div><?php endif; ?>
        <?php if ($err): ?><div class="flash flash-err"><?= e($err) ?></div><?php endif; ?>
        <div class="admin-content"><?= $content ?></div>
    </div>
</div>
</body>
</html>
