<?php
/** @var string $message */
/** @var array $vouchers */
/** @var string $csrf */
?>
<div class="heading"><div><h1>Access vouchers</h1><p class="muted">Issue time- and usage-limited Wi-Fi credentials.</p></div></div>
<?= $message ?>
<section class="panel">
    <h2>Create voucher</h2>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="form-grid">
            <div class="field"><label>Code (blank for automatic)</label><input name="code"></div>
            <div class="field"><label>Label</label><input name="label"></div>
            <div class="field"><label>Duration in minutes</label><input name="duration_minutes" type="number" min="1" value="60"></div>
            <div class="field"><label>Data limit in MB (optional)</label><input name="data_limit_mb" type="number" min="1"></div>
            <div class="field"><label>Maximum devices</label><input name="max_devices" type="number" min="1" value="1"></div>
            <div class="field"><label>Maximum uses</label><input name="max_uses" type="number" min="1" value="1"></div>
            <div class="field"><label>Expires at (optional)</label><input name="expires_at" type="datetime-local"></div>
        </div>
        <button class="button">Create voucher</button>
    </form>
</section>
<section class="panel">
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
