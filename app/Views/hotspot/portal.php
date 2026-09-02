<?php
/** @var array $context */
/** @var bool $routerAvailable */
/** @var bool $authenticated */
/** @var string $csrf */
?>

<h1>Connect to Wi-Fi</h1>
<p class="muted">Enter your access code to start this device’s MikroTik Hotspot session.</p>

<?php if (!$routerAvailable): ?>
    <div class="alert">
        This hotspot router is not registered or is disabled. Ask the operator to add identity
        <span class="code"><?= e($context['router_identity']) ?></span>.
    </div>
<?php endif; ?>

<div class="context">
    <div>
        <small>Device</small>
        <?= e($context['mac'] ?: 'Private/randomized identity') ?>
    </div>
    <div>
        <small>Router</small>
        <?= e($context['router_identity'] ?: 'Unknown') ?>
    </div>
</div>

<?php if ($routerAvailable): ?>
    <form method="post" action="/hotspot/authenticate">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <div class="field">
            <label for="voucher">Access code</label>
            <input
                id="voucher"
                name="voucher"
                autocomplete="one-time-code"
                autocapitalize="characters"
                required
                autofocus
            >
        </div>

        <button class="button full" type="submit">Connect this device</button>
    </form>
<?php endif; ?>

<?php if ($authenticated): ?>
    <p class="muted">
        This session can be linked to your PixiePoint account for history, points and support.
    </p>
<?php else: ?>
    <p class="muted">
        Guest access works normally. <a href="/">Log in</a> or
        <a href="/register">register</a> to protect your points and device history.
    </p>
<?php endif; ?>
