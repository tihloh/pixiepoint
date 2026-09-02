<?php
/** @var string $error */
/** @var bool $googleEnabled */
/** @var string $csrf */
?>
<h1>Create your account</h1>
<p class="muted">Create a PixiePoint account to keep your points, saved devices and activity together across participating PixiePoint Wi-Fi hotspots.</p>

<?= $error ?>
<?php if ($googleEnabled): require dirname(__DIR__) . '/partials/google-button.php'; endif; ?>

<form method="post" class="auth-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="field"><label>Name</label><input name="name" autocomplete="name" required></div>
    <div class="field"><label>Email</label><input name="email" type="email" autocomplete="email" required></div>
    <div class="field"><label>Password</label><input name="password" type="password" minlength="8" autocomplete="new-password" required></div>
    <button class="button full">Create free account</button>
</form>

<p class="muted auth-footer">Already registered? <a href="/">Log in</a></p>
