<?php
/** @var string $error */
/** @var bool $googleEnabled */
/** @var string $csrf */
?>
<h1>Welcome to PixiePoint</h1>
<p class="muted">Sign in to manage your PixiePoint account, points, saved devices and activity across participating PixiePoint Wi-Fi hotspots.</p>

<?= $error ?>
<?php if ($googleEnabled): require dirname(__DIR__) . '/partials/google-button.php'; endif; ?>

<form method="post" action="/login" class="auth-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="field"><label>Email</label><input name="email" type="email" autocomplete="username" required autofocus></div>
    <div class="field"><label>Password</label><input name="password" type="password" autocomplete="current-password" required></div>
    <button class="button full">Log in</button>
</form>

<p class="muted auth-footer">No account? <a href="/register">Create a free account</a></p>
