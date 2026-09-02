<?php
/** @var string $message */
/** @var array $vendos */
/** @var array $routers */
/** @var bool $canManageVendos */
/** @var bool $isPlatformOwner */
/** @var string $csrf */
?>
<div class="heading">
    <div>
        <h1>Vendos</h1>
        <p class="muted">Configure the local PisoWiFi coin slots PixiePoint can offer on each hotspot.</p>
    </div>
    <?php if ($canManageVendos): ?><button class="button" type="button" data-bs-toggle="modal" data-bs-target="#vendoModal" data-mode="create">Add vendo</button><?php endif; ?>
</div>
<?= $message ?>

<section class="panel">
    <h2>Configured vendos</h2>
    <p class="muted">Each row shows which router owns the coin-slot path, which local address clients use, and whether the service is enabled.</p>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Name</th><th>Owner</th><th>Router</th><th>Interface</th><th>Local URL</th><th>Status</th><?php if ($canManageVendos): ?><th class="text-end">Action</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($vendos as $vendo): ?>
                <tr>
                    <td><strong><?= e($vendo['name']) ?></strong></td>
                    <td><?= e($vendo['owner_email'] ?: 'Platform') ?></td>
                    <td><?= e($vendo['router_name']) ?><div class="small text-body-secondary"><?= e($vendo['router_identity']) ?></div></td>
                    <td><?= e($vendo['interface_name'] ?: 'Any interface') ?></td>
                    <td class="code"><?= e($vendo['base_url']) ?></td>
                    <td><span class="badge <?= $vendo['enabled'] ? '' : 'off' ?>"><?= $vendo['enabled'] ? 'Enabled' : 'Disabled' ?></span></td>
                    <?php if ($canManageVendos): ?>
                    <td class="text-end"><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#vendoModal" data-mode="edit" data-id="<?= e($vendo['id']) ?>" data-name="<?= e($vendo['name']) ?>" data-router="<?= e($vendo['router_id']) ?>" data-url="<?= e($vendo['base_url']) ?>" data-interface="<?= e($vendo['interface_name'] ?? '') ?>" data-password-mode="<?= e($vendo['password_mode']) ?>" data-charging="<?= $vendo['charging_enabled'] ? '1' : '0' ?>" data-eload="<?= $vendo['eload_enabled'] ? '1' : '0' ?>" data-enabled="<?= $vendo['enabled'] ? '1' : '0' ?>">Edit</button></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$vendos): ?><tr><td colspan="<?= $canManageVendos ? 7 : 6 ?>" class="empty">No vendos configured. Use Add vendo to link your first coin-slot controller.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($canManageVendos): ?>
<div class="modal fade" id="vendoModal" tabindex="-1" aria-labelledby="vendoModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <div><h2 class="modal-title fs-5 mb-1" id="vendoModalTitle">Add vendo</h2><p class="small text-body-secondary mb-0">Link a physical coin-slot controller to a hotspot router.</p></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="id" value="0">
                    <div class="row g-3">
                        <div class="col-md-6"><label for="vendo-name">Name</label><input id="vendo-name" name="name" required maxlength="160"><small class="text-body-secondary">Friendly name customers and operators can recognize, such as Front Vendo.</small></div>
                        <div class="col-md-6"><label for="vendo-router">Router</label><select id="vendo-router" name="router_id" required><option value="">Select router</option><?php foreach ($routers as $router): ?><option value="<?= e($router['id']) ?>"><?= e($router['name']) ?> · <?= e($router['identity']) ?></option><?php endforeach; ?></select><small class="text-body-secondary">The hotspot router where this vendo should be available.</small></div>
                        <div class="col-md-6"><label for="vendo-url">Local base URL</label><input id="vendo-url" name="base_url" placeholder="http://10.0.0.2" required maxlength="255"><small class="text-body-secondary">Local address the connected customer device uses to reach the vendo controller.</small></div>
                        <div class="col-md-6"><label for="vendo-interface">Interface</label><input id="vendo-interface" name="interface_name" placeholder="Optional, e.g. bridge-HS" maxlength="128"><small class="text-body-secondary">Leave blank for any interface on the selected router.</small></div>
                        <div class="col-md-6"><label for="vendo-password">Password mode</label><select id="vendo-password" name="password_mode"><option value="blank">Blank password</option><option value="voucher">Voucher as password</option></select><small class="text-body-secondary">Controls how the generated voucher authenticates to MikroTik.</small></div>
                        <div class="col-md-6"><div class="form-check mt-4"><input class="form-check-input" type="checkbox" name="charging_enabled" value="1" id="vendo-charging"><label class="form-check-label" for="vendo-charging">Phone charging</label><div class="small text-body-secondary">Expose charging controls when the controller supports them.</div></div></div>
                        <div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="eload_enabled" value="1" id="vendo-eload"><label class="form-check-label" for="vendo-eload">E-load</label><div class="small text-body-secondary">Expose e-load controls when this vendo supports them.</div></div></div>
                        <div class="col-md-6" id="vendo-enabled-wrap" hidden><div class="form-check"><input class="form-check-input" type="checkbox" name="enabled" value="1" id="vendo-enabled"><label class="form-check-label" for="vendo-enabled">Enabled</label><div class="small text-body-secondary">Disabled vendos remain saved but are not offered to hotspot clients.</div></div></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="button" type="submit" id="vendo-submit">Add vendo</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('vendoModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const edit = button && button.dataset.mode === 'edit';
    const form = this.querySelector('form');
    form.reset();
    form.querySelector('[name="action"]').value = edit ? 'update' : 'create';
    form.querySelector('[name="id"]').value = edit ? button.dataset.id : '0';
    form.querySelector('[name="name"]').value = edit ? button.dataset.name : '';
    form.querySelector('[name="router_id"]').value = edit ? button.dataset.router : '';
    form.querySelector('[name="base_url"]').value = edit ? button.dataset.url : '';
    form.querySelector('[name="interface_name"]').value = edit ? button.dataset.interface : '';
    form.querySelector('[name="password_mode"]').value = edit ? button.dataset.passwordMode : 'blank';
    form.querySelector('[name="charging_enabled"]').checked = edit && button.dataset.charging === '1';
    form.querySelector('[name="eload_enabled"]').checked = edit && button.dataset.eload === '1';
    form.querySelector('[name="enabled"]').checked = edit ? button.dataset.enabled === '1' : true;
    document.getElementById('vendo-enabled-wrap').hidden = !edit;
    document.getElementById('vendoModalTitle').textContent = edit ? 'Edit vendo' : 'Add vendo';
    document.getElementById('vendo-submit').textContent = edit ? 'Save changes' : 'Add vendo';
});
</script>
<?php endif; ?>
