<?php
/** @var string $error */
/** @var bool $googleEnabled */
/** @var string $csrf */
?>
<h1>Welcome to PixiePoint</h1>
<p class="muted">Sign in to your PixiePoint account.</p>

<?= $error ?>
<?php if ($googleEnabled): require dirname(__DIR__) . '/partials/google-button.php'; endif; ?>

<form method="post" action="/login" class="auth-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="field">
        <label for="login-email">Email address</label>
        <input id="login-email" name="email" type="email" autocomplete="username" placeholder="you@example.com" required autofocus>
    </div>
    <div class="field">
        <label for="login-password">Password</label>
        <input id="login-password" name="password" type="password" autocomplete="current-password" placeholder="Password" required>
    </div>
    <button class="button full">Sign in</button>
</form>

<p class="muted auth-footer">No account yet? <a href="/register">Create an account</a></p>
