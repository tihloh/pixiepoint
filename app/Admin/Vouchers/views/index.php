<?php
/** @var string $message */
/** @var array $vouchers */
/** @var string $csrf */
?>
<div class="heading">
    <div><h1>Access vouchers</h1><p class="muted">Create Wi-Fi access codes with limits for time, data, devices, uses and expiry.</p></div>
    <button class="button" type="button" data-bs-toggle="modal" data-bs-target="#voucherModal" data-mode="create">Create voucher</button>
</div>
<?= $message ?>

<section class="panel">
    <h2>Created vouchers</h2>
    <p class="muted">Each row shows the code customers use, the access limits, how many times it has been used, and whether it is still enabled.</p>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Code</th><th>Label</th><th>Duration</th><th>Uses</th><th>Expires</th><th>Status</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            <?php if (!$vouchers): ?><tr><td colspan="7" class="empty">No vouchers created. Use Create voucher to issue your first access code.</td></tr><?php endif; ?>
            <?php foreach ($vouchers as $v): ?><tr>
                <td class="code"><strong><?= e($v['code']) ?></strong></td>
                <td><?= e($v['label'] ?: 'No label') ?></td>
                <td><?= e($v['duration_minutes']) ?> min<?php if ($v['data_limit_mb']): ?><div class="small text-body-secondary"><?= e($v['data_limit_mb']) ?> MB data limit</div><?php endif; ?></td>
                <td><?= e($v['uses'] . ' / ' . $v['max_uses']) ?><div class="small text-body-secondary">Up to <?= e($v['max_devices']) ?> device(s)</div></td>
                <td><?= e($v['expires_at'] ?: 'Never') ?></td>
                <td><span class="badge <?= $v['enabled'] ? '' : 'off' ?>"><?= $v['enabled'] ? 'Enabled' : 'Disabled' ?></span></td>
                <td class="text-end"><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#voucherModal" data-mode="edit" data-id="<?= e($v['id']) ?>" data-code="<?= e($v['code']) ?>" data-label="<?= e($v['label'] ?? '') ?>" data-duration="<?= e($v['duration_minutes']) ?>" data-data-limit="<?= e($v['data_limit_mb'] ?? '') ?>" data-max-devices="<?= e($v['max_devices']) ?>" data-max-uses="<?= e($v['max_uses']) ?>" data-expires="<?= e($v['expires_at'] ? str_replace(' ', 'T', substr((string)$v['expires_at'], 0, 16)) : '') ?>" data-enabled="<?= $v['enabled'] ? '1' : '0' ?>">Edit</button></td>
            </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<div class="modal fade" id="voucherModal" tabindex="-1" aria-labelledby="voucherModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <div><h2 class="modal-title fs-5 mb-1" id="voucherModalTitle">Create voucher</h2><p class="small text-body-secondary mb-0">Define what the code grants and how it may be used.</p></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="id" value="0">
                    <div class="form-grid">
                        <div class="field"><label for="voucher-code">Code</label><input id="voucher-code" name="code" placeholder="Leave blank for automatic"><small class="text-body-secondary">What customers enter to connect. Leave blank only when creating to let PixiePoint generate it.</small></div>
                        <div class="field"><label for="voucher-label">Label</label><input id="voucher-label" name="label" placeholder="Optional note or package name"><small class="text-body-secondary">Operator-facing description such as 1 Hour Promo.</small></div>
                        <div class="field"><label for="voucher-duration">Duration in minutes</label><input id="voucher-duration" name="duration_minutes" type="number" min="1" value="60" required><small class="text-body-secondary">Amount of Wi-Fi time granted by this voucher.</small></div>
                        <div class="field"><label for="voucher-data">Data limit in MB</label><input id="voucher-data" name="data_limit_mb" type="number" min="1" placeholder="Optional"><small class="text-body-secondary">Leave blank when access should be limited only by time.</small></div>
                        <div class="field"><label for="voucher-devices">Maximum devices</label><input id="voucher-devices" name="max_devices" type="number" min="1" value="1" required><small class="text-body-secondary">Number of different devices allowed to use this code.</small></div>
                        <div class="field"><label for="voucher-uses">Maximum uses</label><input id="voucher-uses" name="max_uses" type="number" min="1" value="1" required><small class="text-body-secondary">Total number of times this voucher may be consumed.</small></div>
                        <div class="field"><label for="voucher-expires">Expires at</label><input id="voucher-expires" name="expires_at" type="datetime-local"><small class="text-body-secondary">Optional calendar cutoff. Leave blank for no fixed expiry date.</small></div>
                    </div>
                    <div class="form-check mt-2" id="voucher-enabled-wrap" hidden><input class="form-check-input" type="checkbox" name="enabled" value="1" id="voucher-enabled"><label class="form-check-label" for="voucher-enabled">Enabled</label><div class="small text-body-secondary">Disabled vouchers remain saved but cannot be used for new access.</div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="button" type="submit" id="voucher-submit">Create voucher</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('voucherModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const edit = button && button.dataset.mode === 'edit';
    const form = this.querySelector('form');
    form.reset();
    form.querySelector('[name="action"]').value = edit ? 'update' : 'create';
    form.querySelector('[name="id"]').value = edit ? button.dataset.id : '0';
    form.querySelector('[name="code"]').value = edit ? button.dataset.code : '';
    form.querySelector('[name="code"]').required = edit;
    form.querySelector('[name="label"]').value = edit ? button.dataset.label : '';
    form.querySelector('[name="duration_minutes"]').value = edit ? button.dataset.duration : '60';
    form.querySelector('[name="data_limit_mb"]').value = edit ? button.dataset.dataLimit : '';
    form.querySelector('[name="max_devices"]').value = edit ? button.dataset.maxDevices : '1';
    form.querySelector('[name="max_uses"]').value = edit ? button.dataset.maxUses : '1';
    form.querySelector('[name="expires_at"]').value = edit ? button.dataset.expires : '';
    form.querySelector('[name="enabled"]').checked = edit ? button.dataset.enabled === '1' : true;
    document.getElementById('voucher-enabled-wrap').hidden = !edit;
    document.getElementById('voucherModalTitle').textContent = edit ? 'Edit voucher' : 'Create voucher';
    document.getElementById('voucher-submit').textContent = edit ? 'Save changes' : 'Create voucher';
});
</script>
