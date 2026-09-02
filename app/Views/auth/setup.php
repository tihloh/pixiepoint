<?php
/** @var string $error */
/** @var string $csrf */
?>
<h1>Create platform owner</h1>
<p class="muted">Set up the first account that will own and administer this PixiePoint installation.</p>

<?= $error ?>

<form method="post" class="auth-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="field"><label for="setup-name">Name</label><input id="setup-name" name="name" required><small class="text-body-secondary">Name shown for the platform owner account.</small></div>
    <div class="field"><label for="setup-email">Email address</label><input id="setup-email" name="email" type="email" required><small class="text-body-secondary">Used to sign in to the owner account.</small></div>
    <div class="field"><label for="setup-password">Password</label><input id="setup-password" name="password" type="password" minlength="12" required><small class="text-body-secondary">Use at least 12 characters because this account has full platform access.</small></div>
    <button class="button full">Create platform owner</button>
    <small class="d-block text-body-secondary mt-2">Creates the first privileged account and completes initial setup.</small>
</form>
