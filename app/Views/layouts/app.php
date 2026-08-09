<?php
/** @var string $content */
$siteName = setting('site_name', 'SoftwareHub');
$primary = setting('primary_color', '#4f46e5');
$secondary = setting('secondary_color', '#0ea5e9');
$noindex = $noindex ?? false;
$structured = $structured ?? null;
$canonical = $canonical ?? null;
$metaDescription = $metaDescription ?? setting('tagline');
?><!doctype html>
<html lang="en" data-theme="">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title ?? $siteName) ?></title>
    <meta name="description" content="<?= e($metaDescription) ?>">
    <?php if ($noindex): ?><meta name="robots" content="noindex,follow"><?php endif; ?>
    <?php if ($canonical): ?><link rel="canonical" href="<?= e($canonical) ?>"><?php endif; ?>
    <meta property="og:title" content="<?= e($ogTitle ?? $title ?? $siteName) ?>">
    <meta property="og:description" content="<?= e($metaDescription) ?>">
    <meta property="og:type" content="website">
    <?php if (!empty($ogImage)): ?><meta property="og:image" content="<?= e($ogImage) ?>"><?php endif; ?>
    <meta name="twitter:card" content="summary_large_image">
    <link rel="icon" href="<?= e(setting('favicon') ?: asset('img/favicon.svg')) ?>">
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
    <style>:root{--brand:<?= e($primary) ?>;--brand-2:<?= e($secondary) ?>;}</style>
    <?php if ($structured): ?>
        <?php foreach ((array) $structured as $schema): ?>
            <script type="application/ld+json"><?= json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?></script>
        <?php endforeach; ?>
    <?php endif; ?>
    <script>(function(){try{var t=localStorage.getItem('theme');if(t)document.documentElement.setAttribute('data-theme',t);}catch(e){}})();</script>
</head>
<body>
    <?php if ($ad = setting('ad_header')): ?><div class="ad ad-header container"><?= $ad /* admin-controlled */ ?></div><?php endif; ?>
    <?= \App\Core\View::partial('partials/header') ?>
    <main id="main">
        <?= $content ?>
    </main>
    <?= \App\Core\View::partial('partials/footer') ?>
    <script src="<?= e(asset('js/app.js')) ?>" defer></script>
</body>
</html>
