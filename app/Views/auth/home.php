<?php
/** @var string $error */
/** @var bool $googleEnabled */
/** @var string $csrf */
?>
<h1>Welcome to PixiePoint</h1>
<p class="muted">Sign in to open your PixiePoint account and the tools available to you.</p>

<?= $error ?>
<?php if ($googleEnabled): require dirname(__DIR__) . '/partials/google-button.php'; endif; ?>

<form method="post" action="/login" class="auth-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="field">
        <label for="login-email">Email address</label>
        <input id="login-email" name="email" type="email" autocomplete="username" placeholder="you@example.com" required autofocus>
        <small class="text-body-secondary">The email registered to your PixiePoint account.</small>
    </div>
    <div class="field">
        <label for="login-password">Password</label>
        <input id="login-password" name="password" type="password" autocomplete="current-password" placeholder="Enter your password" required>
        <small class="text-body-secondary">Enter your account password to continue.</small>
    </div>
    <button class="button full">Sign in</button>
    <small class="d-block text-body-secondary mt-2">Opens your dashboard after your credentials are verified.</small>
</form>

<p class="muted auth-footer">No account yet? <a href="/register">Create a member account</a></p>
