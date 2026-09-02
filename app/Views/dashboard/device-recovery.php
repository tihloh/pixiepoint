<?php
/** @var string $message */
/** @var array|null $current */
/** @var int $userId */
/** @var int $guestPoints */
/** @var array $known */
/** @var string $csrf */
?>
<?= $message ?>

<?php if (!$current): return; endif; ?>

<?php if ($current['user_id'] !== null && (int)$current['user_id'] !== $userId): ?>
<section class="panel">
    <h2>Device identity conflict</h2>
    <p class="muted">This browser currently points to a device already owned by another account.</p>
    <div class="alert">PixiePoint will not merge it automatically. Use another device identity or resolve ownership before claiming it.</div>
</section>
<?php return; endif; ?>

<?php if ($current['user_id'] !== null && (int)$current['user_id'] === $userId): return; endif; ?>

<section class="panel">
    <h2>Confirm this device</h2>
    <p class="muted">Tell PixiePoint whether this browser belongs to one of your saved devices or should become a new device.</p>

    <?php if ($guestPoints > 0): ?>
        <div class="notice"><strong><?= e($guestPoints) ?> guest points</strong> are waiting on this device. Confirming the device lets PixiePoint move those points into your account.</div>
    <?php endif; ?>

    <?php if ($known): ?>
        <p class="muted">Choose an existing device below to restore its identity and history to this browser.</p>
    <?php else: ?>
        <p class="muted">No matching saved device was found. Save this browser as a new device to protect its identity and guest wallet with your account.</p>
    <?php endif; ?>

    <div class="actions">
        <?php foreach ($known as $device): ?>
            <?php if ((int)$device['id'] === (int)$current['id']) continue; ?>
            <?php $label = $device['mac'] ?: ('Device ' . substr((string)$device['uuid'], 0, 8)); ?>
            <form method="post" action="/devices/claim">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="device_id" value="<?= e($current['id']) ?>">
                <input type="hidden" name="target_device_id" value="<?= e($device['id']) ?>">
                <button class="button secondary full" type="submit">Use <?= e($label) ?></button>
                <small class="d-block text-body-secondary mt-2">Links this browser to the selected saved device.</small>
            </form>
        <?php endforeach; ?>

        <form method="post" action="/devices/claim">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="device_id" value="<?= e($current['id']) ?>">
            <input type="hidden" name="target_device_id" value="0">
            <button class="button full" type="submit">Save as new device</button>
            <small class="d-block text-body-secondary mt-2">Creates a separate saved device for this browser.</small>
        </form>
    </div>
</section>
