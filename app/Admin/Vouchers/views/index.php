<?php
/** @var string $message */
/** @var array $vouchers */
/** @var string $csrf */
?>

<div class="heading">
    <div>
        <h1>Access vouchers</h1>
        <p class="muted">Create and manage Wi-Fi access codes.</p>
    </div>

    <button
        class="button"
        type="button"
        data-bs-toggle="modal"
        data-bs-target="#voucherModal"
        data-mode="create"
    >
        Create voucher
    </button>
</div>

<?= $message ?>

<section class="panel">
    <h2>Created vouchers</h2>

    <div class="table-responsive">
        <table class="table align-middle">
            <thead>
                <tr>
                    <th>Code</th>
                    <th>Label</th>
                    <th>Duration</th>
                    <th>Uses</th>
                    <th>Expires</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$vouchers): ?>
                    <tr>
                        <td colspan="7" class="empty">No vouchers created.</td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($vouchers as $voucher): ?>
                    <tr>
                        <td class="code">
                            <strong><?= e($voucher['code']) ?></strong>
                        </td>
                        <td><?= e($voucher['label'] ?: 'No label') ?></td>
                        <td>
                            <?= e($voucher['duration_minutes']) ?> min
                            <?php if ($voucher['data_limit_mb']): ?>
                                <div class="small text-body-secondary">
                                    <?= e($voucher['data_limit_mb']) ?> MB limit
                                </div>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?= e($voucher['uses'] . ' / ' . $voucher['max_uses']) ?>
                            <div class="small text-body-secondary">
                                <?= e($voucher['max_devices']) ?> device(s)
                            </div>
                        </td>
                        <td><?= e($voucher['expires_at'] ?: 'Never') ?></td>
                        <td>
                            <span class="badge <?= $voucher['enabled'] ? '' : 'off' ?>">
                                <?= $voucher['enabled'] ? 'Enabled' : 'Disabled' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <!-- Values are passed to the shared create/edit modal through data attributes. -->
                            <button
                                class="btn btn-sm btn-outline-secondary"
                                type="button"
                                data-bs-toggle="modal"
                                data-bs-target="#voucherModal"
                                data-mode="edit"
                                data-id="<?= e($voucher['id']) ?>"
                                data-code="<?= e($voucher['code']) ?>"
                                data-label="<?= e($voucher['label'] ?? '') ?>"
                                data-duration="<?= e($voucher['duration_minutes']) ?>"
                                data-data-limit="<?= e($voucher['data_limit_mb'] ?? '') ?>"
                                data-max-devices="<?= e($voucher['max_devices']) ?>"
                                data-max-uses="<?= e($voucher['max_uses']) ?>"
                                data-expires="<?= e(
                                  $voucher['expires_at']
                                    ? str_replace(
                                      ' ',
                                      'T',
                                      substr((string) $voucher['expires_at'], 0, 16),
                                    )
                                    : '',
                                ) ?>"
                                data-enabled="<?= $voucher['enabled'] ? '1' : '0' ?>"
                            >
                                Edit
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<!-- One modal handles both voucher creation and editing. -->
<div
    class="modal fade"
    id="voucherModal"
    tabindex="-1"
    aria-labelledby="voucherModalTitle"
    aria-hidden="true"
>
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <h2 class="modal-title fs-5" id="voucherModalTitle">Create voucher</h2>
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>
                </div>

                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="id" value="0">

                    <div class="form-grid">
                        <div class="field">
                            <label for="voucher-code">Code</label>
                            <input id="voucher-code" name="code" placeholder="Automatic if blank">
                            <small class="text-body-secondary">Leave blank to generate a code.</small>
                        </div>

                        <div class="field">
                            <label for="voucher-label">Label</label>
                            <input id="voucher-label" name="label" placeholder="Optional">
                        </div>

                        <div class="field">
                            <label for="voucher-duration">Duration (minutes)</label>
                            <input
                                id="voucher-duration"
                                name="duration_minutes"
                                type="number"
                                min="1"
                                value="60"
                                required
                            >
                        </div>

                        <div class="field">
                            <label for="voucher-data">Data limit (MB)</label>
                            <input
                                id="voucher-data"
                                name="data_limit_mb"
                                type="number"
                                min="1"
                                placeholder="No limit"
                            >
                        </div>

                        <div class="field">
                            <label for="voucher-devices">Maximum devices</label>
                            <input
                                id="voucher-devices"
                                name="max_devices"
                                type="number"
                                min="1"
                                value="1"
                                required
                            >
                        </div>

                        <div class="field">
                            <label for="voucher-uses">Maximum uses</label>
                            <input
                                id="voucher-uses"
                                name="max_uses"
                                type="number"
                                min="1"
                                value="1"
                                required
                            >
                        </div>

                        <div class="field">
                            <label for="voucher-expires">Expires at</label>
                            <input id="voucher-expires" name="expires_at" type="datetime-local">
                            <small class="text-body-secondary">Leave blank for no expiry.</small>
                        </div>
                    </div>

                    <div class="form-check mt-2" id="voucher-enabled-wrap" hidden>
                        <input
                            class="form-check-input"
                            type="checkbox"
                            name="enabled"
                            value="1"
                            id="voucher-enabled"
                        >
                        <label class="form-check-label" for="voucher-enabled">Enabled</label>
                    </div>
                </div>

                <div class="modal-footer">
                    <button
                        type="button"
                        class="btn btn-outline-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>
                    <button class="button" type="submit" id="voucher-submit">
                        Create voucher
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Populate the shared modal when an existing voucher is edited.
    document.getElementById('voucherModal').addEventListener('show.bs.modal', function (event) {
        const button = event.relatedTarget;
        const isEdit = button && button.dataset.mode === 'edit';
        const form = this.querySelector('form');

        form.reset();

        form.querySelector('[name="action"]').value = isEdit ? 'update' : 'create';
        form.querySelector('[name="id"]').value = isEdit ? button.dataset.id : '0';
        form.querySelector('[name="code"]').value = isEdit ? button.dataset.code : '';
        form.querySelector('[name="code"]').required = isEdit;
        form.querySelector('[name="label"]').value = isEdit ? button.dataset.label : '';
        form.querySelector('[name="duration_minutes"]').value = isEdit
            ? button.dataset.duration
            : '60';
        form.querySelector('[name="data_limit_mb"]').value = isEdit
            ? button.dataset.dataLimit
            : '';
        form.querySelector('[name="max_devices"]').value = isEdit
            ? button.dataset.maxDevices
            : '1';
        form.querySelector('[name="max_uses"]').value = isEdit
            ? button.dataset.maxUses
            : '1';
        form.querySelector('[name="expires_at"]').value = isEdit
            ? button.dataset.expires
            : '';
        form.querySelector('[name="enabled"]').checked = isEdit
            ? button.dataset.enabled === '1'
            : true;

        document.getElementById('voucher-enabled-wrap').hidden = !isEdit;
        document.getElementById('voucherModalTitle').textContent = isEdit
            ? 'Edit voucher'
            : 'Create voucher';
        document.getElementById('voucher-submit').textContent = isEdit
            ? 'Save changes'
            : 'Create voucher';
    });
</script>
