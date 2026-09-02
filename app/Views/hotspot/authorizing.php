<?php
/** @var string $action */
/** @var string $destination */
/** @var string $username */
/** @var string $password */
?>
<h1>Authorizing…</h1>
<p class="muted">Your access code was accepted. Connecting this device now.</p>

<form id="router-login" action="<?= e($action) ?>" method="post">
    <input type="hidden" name="username" value="<?= e($username) ?>">
    <input type="hidden" name="password" value="<?= e($password) ?>">
    <input type="hidden" name="dst" value="<?= e($destination) ?>">
    <input type="hidden" name="popup" value="true">
</form>

<script>document.getElementById('router-login').submit();</script>
<noscript><button class="button full" type="submit" form="router-login">Continue</button></noscript>
