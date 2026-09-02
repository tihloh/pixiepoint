<?php
/** @var string $error */
/** @var bool $googleEnabled */
/** @var string $csrf */
?>
<h1>Create your account</h1>
<p class="muted">Register as a PixiePoint member.</p>

<?= $error ?>
<?php if ($googleEnabled): require dirname(__DIR__) . '/partials/google-button.php'; endif; ?>

<form method="post" class="auth-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="field">
        <label for="register-name">Full name</label>
        <input id="register-name" name="name" autocomplete="name" placeholder="Your name" required>
    </div>
    <div class="field">
        <label for="register-email">Email address</label>
        <input id="register-email" name="email" type="email" autocomplete="email" placeholder="you@example.com" required>
    </div>
    <div class="field">
        <label for="register-password">Password</label>
        <input id="register-password" name="password" type="password" minlength="8" autocomplete="new-password" placeholder="At least 8 characters" required>
    </div>
    <button class="button full">Create member account</button>
</form>

<p class="muted auth-footer">Already registered? <a href="/">Sign in</a></p>
