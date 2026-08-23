<?php
/** @var string $content */
$admin = $_admin ?? \App\Core\Auth::user();
$unread = $_unread ?? 0;
$ok = \App\Core\Session::flash('ok');
$err = \App\Core\Session::flash('err');
$path = $_SERVER['REQUEST_URI'] ?? '';
$nav = [
    '/admin/platform'          => ['Platforms', '🗂'],
    '/admin/software/new'      => ['Add Software', '➕'],
    '/admin/software/discover' => ['Discover', '🧭'],
    '/admin/software/bulk'     => ['Bulk publish', '🚀'],
    '/admin/software'          => ['All Software', '📋'],
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
$adminName = $admin['name'] ?? 'Admin';
$initial = mb_strtoupper(mb_substr($adminName, 0, 1));
$curPlat = \App\Controllers\Admin\PlatformController::current();
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
    <aside class="admin-sidebar" id="adminSidebar">
        <a class="admin-brand" href="<?= e(base_url('/admin')) ?>">
            <span class="admin-brand-mark">◆</span>
            <span class="admin-brand-name"><?= e(setting('site_name')) ?></span>
        </a>
        <?php if ($curPlat): ?>
            <a href="<?= e(base_url('/admin/platform')) ?>" class="admin-plat">
                <span class="admin-plat-dot"></span>
                <span><strong><?= e($curPlat['label']) ?></strong> panel</span>
                <span class="admin-plat-switch">⇄</span>
            </a>
        <?php else: ?>
            <a href="<?= e(base_url('/admin/platform')) ?>" class="admin-plat"><span class="admin-plat-dot off"></span> Choose platform</a>
        <?php endif; ?>
        <nav class="admin-nav">
            <?php foreach ($nav as $href => [$label, $icon]): ?>
                <a href="<?= e(base_url($href)) ?>" class="<?= $href === $activeHref ? 'is-active' : '' ?>" data-navlink>
                    <span class="ico"><?= $icon ?></span><span class="lbl"><?= e($label) ?></span>
                </a>
            <?php endforeach; ?>
        </nav>
        <div class="admin-nav-foot">
            <a href="<?= e(base_url('/admin/notifications')) ?>"><span class="ico">🔔</span> Notifications <?php if ($unread): ?><span class="badge"><?= (int) $unread ?></span><?php endif; ?></a>
            <a href="<?= e(base_url('/')) ?>" target="_blank"><span class="ico">↗</span> View site</a>
        </div>
    </aside>
    <div class="admin-backdrop" id="adminBackdrop" hidden></div>
    <div class="admin-main">
        <header class="admin-top">
            <button class="admin-burger" id="adminBurger" aria-label="Menu" aria-expanded="false"><span></span><span></span><span></span></button>
            <h1><?= e($title ?? '') ?></h1>
            <div class="admin-user">
                <?php if ($curPlat): ?>
                    <a href="<?= e(base_url('/admin/platform')) ?>" class="plat-pill" title="Switch platform panel"><strong><?= e($curPlat['label']) ?></strong> ⇄</a>
                <?php endif; ?>
                <span class="admin-avatar" title="<?= e($adminName) ?>"><?= e($initial) ?></span>
                <span class="admin-whoami"><?= e($adminName) ?><span class="muted"> · <?= e($admin['role'] ?? '') ?></span></span>
                <form method="post" action="<?= e(base_url('/admin/logout')) ?>" class="inline">
                    <?= \App\Core\Csrf::field() ?>
                    <button class="btn btn-sm btn-ghost">Log out</button>
                </form>
            </div>
        </header>
        <?php if ($ok): ?><div class="flash flash-ok"><span>✅</span><div><?= e($ok) ?></div></div><?php endif; ?>
        <?php if ($err): ?><div class="flash flash-err"><span>⚠️</span><div><?= e($err) ?></div></div><?php endif; ?>
        <div class="admin-content"><?= $content ?></div>
    </div>
</div>
<script>
(function () {
    var b = document.getElementById('adminBurger'),
        s = document.getElementById('adminSidebar'),
        bd = document.getElementById('adminBackdrop');
    function open() { document.body.classList.add('nav-open'); bd.hidden = false; b.setAttribute('aria-expanded', 'true'); }
    function close() { document.body.classList.remove('nav-open'); bd.hidden = true; b.setAttribute('aria-expanded', 'false'); }
    if (b) b.addEventListener('click', function () { document.body.classList.contains('nav-open') ? close() : open(); });
    if (bd) bd.addEventListener('click', close);
    document.querySelectorAll('[data-navlink]').forEach(function (a) { a.addEventListener('click', close); });
    window.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });
})();
</script>
</body>
</html>
