<?php
/** @var string $mac */
/** @var string $ip */
/** @var int $bytesIn */
/** @var int $bytesOut */
/** @var int $uptime */
/** @var string $logoutUrl */
/** @var bool $authenticated */
?>
<h1>You’re connected</h1>
<p class="muted">Live Wi-Fi session for <?= e($mac ?: 'this device') ?>.</p>

<div class="context">
    <div><small>Connected</small><?= e(duration_nice($uptime)) ?></div>
    <div><small>IP address</small><?= e($ip) ?></div>
    <div><small>Downloaded</small><?= e(bytes_nice($bytesOut)) ?></div>
    <div><small>Uploaded</small><?= e(bytes_nice($bytesIn)) ?></div>
</div>

<form method="post" action="<?= e($logoutUrl ?: '#') ?>">
    <button class="button full" type="submit">Disconnect</button>
</form>

<?php if ($authenticated): ?>
<a class="button full" href="/dashboard">My PixiePoint account</a>
<?php else: ?>
<p class="muted">Create a PixiePoint account later to protect your points and device history.</p>
<?php endif; ?>
