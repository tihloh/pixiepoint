<?php
/** @var string $error */
/** @var bool $googleEnabled */
/** @var string $csrf */
?>
<div class="online-auth-header">
    <span class="badge rounded-pill text-bg-success mb-3">Free member account</span>
    <h1>Create your PixiePoint account</h1>
    <p class="muted">Register once and use the same account anywhere the PixiePoint service is available.</p>
</div>

<div class="online-auth-info mb-4">
    <div><strong>Keep your points</strong><small>Your rewards can stay associated with your account instead of only one browser or device.</small></div>
    <div><strong>Save your devices</strong><small>Link your phones and computers so PixiePoint can recognize your Wi-Fi activity more reliably.</small></div>
    <div><strong>See your history</strong><small>Account-linked sessions and activity can appear in your personal dashboard.</small></div>
</div>

<div class="alert alert-info border-0 small" role="note">
    <strong>Your default access is Member.</strong> Registration does not create an administrator or operator account. Management features only appear if additional roles or permissions are assigned later.
</div>

<?= $error ?>
<?php if ($googleEnabled): require dirname(__DIR__) . '/partials/google-button.php'; endif; ?>

<form method="post" class="auth-form mt-3">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="field">
        <label for="register-name">Your name</label>
        <input id="register-name" name="name" autocomplete="name" placeholder="Full name" required>
    </div>
    <div class="field">
        <label for="register-email">Email address</label>
        <input id="register-email" name="email" type="email" autocomplete="email" placeholder="you@example.com" required>
        <small class="text-body-secondary">This becomes your PixiePoint login name.</small>
    </div>
    <div class="field">
        <label for="register-password">Password</label>
        <input id="register-password" name="password" type="password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" required>
        <small class="text-body-secondary">Use a password you do not reuse on other websites.</small>
    </div>
    <button class="button full">Create my member account</button>
</form>

<div class="online-auth-footer">
    <strong>Already have an account?</strong>
    <p class="muted mb-2">Members, staff, operators and administrators all use the same login page.</p>
    <a class="btn btn-outline-secondary w-100" href="/">Back to sign in</a>
</div>
