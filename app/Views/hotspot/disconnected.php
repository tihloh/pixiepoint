<?php
/** @var int $uptime */
/** @var int $bytesIn */
/** @var int $bytesOut */
/** @var string $loginUrl */
?>

<h1>You’re offline</h1>
<p class="muted">The Wi-Fi session has ended.</p>

<div class="context">
    <div>
        <small>Session time</small>
        <?= e(duration_nice($uptime)) ?>
    </div>
    <div>
        <small>Total transfer</small>
        <?= e(bytes_nice($bytesIn + $bytesOut)) ?>
    </div>
</div>

<a class="button full" href="<?= e($loginUrl ?: '#') ?>">Connect again</a>
