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
    <div class="alert">This browser is linked to a device owned by another account. PixiePoint will not merge it automatically.</div>
</section>
<?php return; endif; ?>

<?php if ($current['user_id'] !== null && (int)$current['user_id'] === $userId): return; endif; ?>

<section class="panel">
    <h2>Confirm this device</h2>

    <?php if ($guestPoints > 0): ?>
        <div class="notice"><strong><?= e($guestPoints) ?> guest points</strong> are waiting on this device. Confirm it to claim them into your account.</div>
    <?php endif; ?>

    <?php if ($known): ?>
        <p class="muted">PixiePoint could not confidently match this browser/MAC combination. If this is one of your existing devices, confirm it below to restore that device identity, guest wallet and history together.</p>
    <?php else: ?>
        <p class="muted">PixiePoint detected an anonymous device from before you signed in. Save it to your account so its guest wallet and identity become protected by your account.</p>
    <?php endif; ?>

    <div class="actions">
        <?php foreach ($known as $device): ?>
            <?php if ((int)$device['id'] === (int)$current['id']) continue; ?>
            <?php $label = $device['mac'] ?: ('Device ' . substr((string)$device['uuid'], 0, 8)); ?>
            <form method="post" action="/devices/claim">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="device_id" value="<?= e($current['id']) ?>">
                <input type="hidden" name="target_device_id" value="<?= e($device['id']) ?>">
                <button class="button secondary full" type="submit">This is <?= e($label) ?></button>
            </form>
        <?php endforeach; ?>

        <form method="post" action="/devices/claim">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="device_id" value="<?= e($current['id']) ?>">
            <input type="hidden" name="target_device_id" value="0">
            <button class="button full" type="submit">Save as a new device</button>
        </form>
    </div>
</section>
