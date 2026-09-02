<?php
/** @var string $error */
/** @var bool $googleEnabled */
/** @var string $csrf */
?>

<h1>Create your account</h1>
<p class="muted">
    Register a member account for your points, devices and account-linked Wi-Fi activity.
</p>

<?= $error ?>

<?php if ($googleEnabled): ?>
    <?php require dirname(__DIR__) . '/partials/google-button.php'; ?>
<?php endif; ?>

<form method="post" class="auth-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <div class="field">
        <label for="register-name">Name</label>
        <input
            id="register-name"
            name="name"
            autocomplete="name"
            placeholder="Your name"
            required
        >
        <small class="text-body-secondary">The name shown on your PixiePoint account.</small>
    </div>

    <div class="field">
        <label for="register-email">Email address</label>
        <input
            id="register-email"
            name="email"
            type="email"
            autocomplete="email"
            placeholder="you@example.com"
            required
        >
        <small class="text-body-secondary">Used to sign in and identify your account.</small>
    </div>

    <div class="field">
        <label for="register-password">Password</label>
        <input
            id="register-password"
            name="password"
            type="password"
            minlength="8"
            autocomplete="new-password"
            placeholder="At least 8 characters"
            required
        >
        <small class="text-body-secondary">
            Choose at least 8 characters to protect your account.
        </small>
    </div>

    <button class="button full">Create account</button>
    <small class="d-block text-body-secondary mt-2">
        Creates a member account and signs you in.
    </small>
</form>

<p class="muted auth-footer">
    Already registered? <a href="/">Sign in</a>
</p>
