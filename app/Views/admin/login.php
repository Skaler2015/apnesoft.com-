<?php use App\Core\Csrf; ?>
<div class="login-card">
    <h1>◆ <?= e(setting('site_name')) ?></h1>
    <p class="muted">Admin sign in</p>
    <?php if (!empty($error)): ?><div class="flash flash-err"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="<?= e(base_url('/admin/login')) ?>">
        <?= Csrf::field() ?>
        <label>Email<input type="email" name="email" required autofocus autocomplete="username"></label>
        <label>Password<input type="password" name="password" required autocomplete="current-password"></label>
        <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>
</div>
