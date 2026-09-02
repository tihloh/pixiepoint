<?php
/** @var string $message */
/** @var array $vouchers */
/** @var string $csrf */
?>
<div class="heading"><div><h1>Access vouchers</h1><p class="muted">Create Wi-Fi access codes with limits for time, data, devices, uses and expiry.</p></div></div>
<?= $message ?>
<section class="panel">
    <h2>Create voucher</h2>
    <p class="muted">Define how long the voucher works and how many devices or logins can use it.</p>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="form-grid">
            <div class="field"><label>Code</label><input name="code" placeholder="Leave blank for automatic"><small class="text-body-secondary">The code customers enter to connect. Leave blank to let PixiePoint generate one.</small></div>
            <div class="field"><label>Label</label><input name="label" placeholder="Optional note or package name"><small class="text-body-secondary">A human-readable description for operators; it does not change the voucher code.</small></div>
            <div class="field"><label>Duration in minutes</label><input name="duration_minutes" type="number" min="1" value="60"><small class="text-body-secondary">How much Wi-Fi time this voucher grants.</small></div>
            <div class="field"><label>Data limit in MB</label><input name="data_limit_mb" type="number" min="1" placeholder="Optional"><small class="text-body-secondary">Maximum transfer allowed. Leave blank when only time should limit access.</small></div>
            <div class="field"><label>Maximum devices</label><input name="max_devices" type="number" min="1" value="1"><small class="text-body-secondary">How many different devices may use this voucher.</small></div>
            <div class="field"><label>Maximum uses</label><input name="max_uses" type="number" min="1" value="1"><small class="text-body-secondary">How many times the voucher may be consumed before it stops accepting new use.</small></div>
            <div class="field"><label>Expires at</label><input name="expires_at" type="datetime-local"><small class="text-body-secondary">Optional calendar expiry. Leave blank for no fixed expiry date.</small></div>
        </div>
        <button class="button">Create voucher</button>
        <small class="d-block text-body-secondary mt-2">Saves the access code with the limits above.</small>
    </form>
</section>
<section class="panel">
    <h2>Created vouchers</h2>
    <p class="muted">Shows the codes already issued, how much time they grant, usage count, expiry and current status.</p>
    <table><thead><tr><th>Code</th><th>Label</th><th>Duration</th><th>Uses</th><th>Expires</th><th>Status</th></tr></thead>
    <tbody>
    <?php if (!$vouchers): ?><tr><td colspan="6" class="empty">No vouchers created.</td></tr><?php endif; ?>
    <?php foreach ($vouchers as $v): ?><tr>
        <td class="code"><?= e($v['code']) ?></td><td><?= e($v['label'] ?: '—') ?></td><td><?= e($v['duration_minutes']) ?> min</td>
        <td><?= e($v['uses'] . ' / ' . $v['max_uses']) ?></td><td><?= e($v['expires_at'] ?: 'Never') ?></td>
        <td><span class="badge <?= $v['enabled'] ? '' : 'off' ?>"><?= $v['enabled'] ? 'Enabled' : 'Disabled' ?></span></td>
    </tr><?php endforeach; ?>
    </tbody></table>
</section>
