<?php
/** @var string $message */
/** @var array|null $current */
/** @var int $userId */
/** @var int $guestPoints */
/** @var array $known */
/** @var string $csrf */
?>

<?= $message ?>

<?php // Nothing to confirm when the request cannot be tied to a current device.
if (!$current) {
  return;
} ?>

<?php if ($current['user_id'] !== null && (int) $current['user_id'] !== $userId): ?>
    <section class="panel">
        <h2>Device identity conflict</h2>
        <div class="alert">This device is already linked to another account.</div>
    </section>
    <?php return; ?>
<?php endif; ?>

<?php // The device is already owned by the signed-in account, so no action is needed.

if ($current['user_id'] !== null && (int) $current['user_id'] === $userId) {
  return;
} ?>

<section class="panel">
    <h2>Confirm this device</h2>
    <p class="muted">Choose a saved device or save this one as new.</p>

    <?php if ($guestPoints > 0): ?>
        <div class="notice">
            <strong><?= e($guestPoints) ?> guest points</strong>
            will be added to your account when confirmed.
        </div>
    <?php endif; ?>

    <div class="actions">
        <?php foreach ($known as $device): ?>
            <?php
            if ((int) $device['id'] === (int) $current['id']) {
              continue;
            }
            $label = $device['mac'] ?: 'Device ' . substr((string) $device['uuid'], 0, 8);
            ?>

            <form method="post" action="/devices/claim">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="device_id" value="<?= e($current['id']) ?>">
                <input type="hidden" name="target_device_id" value="<?= e($device['id']) ?>">
                <button class="button secondary full" type="submit">
                    Use <?= e($label) ?>
                </button>
            </form>
        <?php endforeach; ?>

        <form method="post" action="/devices/claim">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="device_id" value="<?= e($current['id']) ?>">
            <input type="hidden" name="target_device_id" value="0">
            <button class="button full" type="submit">Save as new device</button>
        </form>
    </div>
</section>
