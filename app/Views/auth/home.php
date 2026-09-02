<?php
/** @var string $error */
/** @var bool $googleEnabled */
/** @var string $csrf */
?>
<div class="online-auth-header">
    <span class="badge rounded-pill text-bg-primary mb-3">Central PixiePoint account</span>
    <h1>Welcome to PixiePoint</h1>
    <p class="muted mb-3">One account gives you the same PixiePoint website and login. What you can see and manage after signing in depends on your assigned role and permissions.</p>
</div>

<div class="online-auth-info mb-4">
    <div>
        <strong>Members</strong>
        <small>Keep points, saved devices and Wi-Fi activity linked to one account.</small>
    </div>
    <div>
        <strong>Operators &amp; staff</strong>
        <small>See only the hotspot, vendo, voucher, session and reporting tools you are allowed to use.</small>
    </div>
    <div>
        <strong>Platform administrators</strong>
        <small>Manage the wider PixiePoint service according to their administrative permissions.</small>
    </div>
</div>

<div class="alert alert-info border-0 small" role="note">
    <strong>Same login for everyone.</strong> You do not need a separate admin or operator login page. Sign in below and PixiePoint will show the correct features for your account.
</div>

<?= $error ?>
<?php if ($googleEnabled): require dirname(__DIR__) . '/partials/google-button.php'; endif; ?>

<form method="post" action="/login" class="auth-form mt-3">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="field">
        <label for="login-email">Email address</label>
        <input id="login-email" name="email" type="email" autocomplete="username" placeholder="you@example.com" required autofocus>
        <small class="text-body-secondary">Use the email address registered to your PixiePoint account.</small>
    </div>
    <div class="field">
        <label for="login-password">Password</label>
        <input id="login-password" name="password" type="password" autocomplete="current-password" placeholder="Your password" required>
    </div>
    <button class="button full">Sign in to PixiePoint</button>
</form>

<div class="online-auth-footer">
    <strong>New customer?</strong>
    <p class="muted mb-2">Registration creates a normal member account. Extra management access is granted separately through roles and permissions.</p>
    <a class="btn btn-outline-secondary w-100" href="/register">Create a free member account</a>
</div>
