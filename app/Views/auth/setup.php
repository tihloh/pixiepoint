<?php
/** @var string $error */
/** @var string $csrf */
?>
<h1>Create platform owner</h1>
<p class="muted">This one-time account owns the centralized PixiePoint service. All other users register through the normal account flow.</p>

<?= $error ?>

<form method="post" class="auth-form">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
    <div class="field"><label>Name</label><input name="name" required></div>
    <div class="field"><label>Email</label><input name="email" type="email" required></div>
    <div class="field"><label>Password</label><input name="password" type="password" minlength="12" required></div>
    <button class="button full">Create platform owner</button>
</form>
