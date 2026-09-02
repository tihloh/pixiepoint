<?php
/** @var string $message */
/** @var array $vendos */
/** @var array $routers */
/** @var bool $canManageVendos */
/** @var string $csrf */
?>
<div class="heading">
    <div>
        <h1>Vendos</h1>
        <p class="muted">Configure local PisoWiFi coin slots and the MikroTik hotspot server that identifies each one.</p>
    </div>
    <?php if ($canManageVendos): ?><button class="button" type="button" data-bs-toggle="modal" data-bs-target="#vendoModal" data-mode="create">Add vendo</button><?php endif; ?>
</div>
<?= $message ?>

<section class="panel">
    <h2>Configured vendos</h2>
    <p class="muted">PixiePoint matches the RouterOS identity and MikroTik server IP sent by the hotspot page to select the correct vendo automatically.</p>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Name</th><th>Router</th><th>Server IP</th><th>Interface</th><th>Local URL</th><th>Status</th><?php if ($canManageVendos): ?><th class="text-end">Action</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($vendos as $vendo): ?>
                <tr>
                    <td><strong><?= e($vendo['name']) ?></strong></td>
                    <td><?= e($vendo['router_name']) ?><div class="small text-body-secondary"><?= e($vendo['router_identity']) ?></div></td>
                    <td class="code"><?= e($vendo['server_ip'] ?: 'Not set') ?></td>
                    <td><?= e($vendo['interface_name'] ?: 'Any interface') ?></td>
                    <td class="code"><?= e($vendo['base_url']) ?></td>
                    <td><span class="badge <?= $vendo['enabled'] ? '' : 'off' ?>"><?= $vendo['enabled'] ? 'Enabled' : 'Disabled' ?></span></td>
                    <?php if ($canManageVendos): ?>
                    <td class="text-end"><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#vendoModal" data-mode="edit" data-id="<?= e($vendo['id']) ?>" data-name="<?= e($vendo['name']) ?>" data-router="<?= e($vendo['router_id']) ?>" data-url="<?= e($vendo['base_url']) ?>" data-server-ip="<?= e($vendo['server_ip'] ?? '') ?>" data-interface="<?= e($vendo['interface_name'] ?? '') ?>" data-password-mode="<?= e($vendo['password_mode']) ?>" data-charging="<?= $vendo['charging_enabled'] ? '1' : '0' ?>" data-eload="<?= $vendo['eload_enabled'] ? '1' : '0' ?>" data-enabled="<?= $vendo['enabled'] ? '1' : '0' ?>">Edit</button></td>
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
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="post">
        <div class="modal-header">
            <div><h2 class="modal-title fs-5 mb-1" id="vendoModalTitle">Add vendo</h2><p class="small text-body-secondary mb-0">Link a coin-slot controller to its MikroTik hotspot server.</p></div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="id" value="0">
            <div class="row g-3">
                <div class="col-md-6"><label for="vendo-name">Name</label><input id="vendo-name" name="name" required maxlength="160"><small class="text-body-secondary">Friendly coin-slot name shown to customers and operators.</small></div>
                <div class="col-md-6"><label for="vendo-router">Router</label><select id="vendo-router" name="router_id" required><option value="">Select router</option><?php foreach ($routers as $router): ?><option value="<?= e($router['id']) ?>"><?= e($router['name']) ?> · <?= e($router['identity']) ?></option><?php endforeach; ?></select><small class="text-body-secondary">RouterOS identity is matched first when the hosted portal opens.</small></div>
                <div class="col-md-6"><label for="vendo-server-ip">Server IP</label><input id="vendo-server-ip" name="server_ip" placeholder="10.0.3.1" required maxlength="45"><small class="text-body-secondary">MikroTik Hotspot server address. PixiePoint compares this with <code>$(server-address)</code> to identify the vendo.</small></div>
                <div class="col-md-6"><label for="vendo-url">Local base URL</label><input id="vendo-url" name="base_url" placeholder="http://10.0.3.2" required maxlength="255"><small class="text-body-secondary">Actual local URL of the vendo controller used for coin-slot requests.</small></div>
                <div class="col-md-6"><label for="vendo-interface">Interface</label><input id="vendo-interface" name="interface_name" placeholder="Optional, e.g. bridge-HS" maxlength="128"><small class="text-body-secondary">Optional extra identification when several hotspot services share the same router.</small></div>
                <div class="col-md-6"><label for="vendo-password">Password mode</label><select id="vendo-password" name="password_mode"><option value="blank">Blank password</option><option value="voucher">Voucher as password</option></select><small class="text-body-secondary">Controls how the voucher authenticates to MikroTik.</small></div>
                <div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="charging_enabled" value="1" id="vendo-charging"><label class="form-check-label" for="vendo-charging">Phone charging</label><div class="small text-body-secondary">Show charging controls when supported.</div></div></div>
                <div class="col-md-6"><div class="form-check"><input class="form-check-input" type="checkbox" name="eload_enabled" value="1" id="vendo-eload"><label class="form-check-label" for="vendo-eload">E-load</label><div class="small text-body-secondary">Show e-load controls when supported.</div></div></div>
                <div class="col-md-6" id="vendo-enabled-wrap" hidden><div class="form-check"><input class="form-check-input" type="checkbox" name="enabled" value="1" id="vendo-enabled"><label class="form-check-label" for="vendo-enabled">Enabled</label><div class="small text-body-secondary">Disabled vendos are never offered to hotspot clients.</div></div></div>
            </div>
        </div>
        <div class="modal-footer"><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="button" type="submit" id="vendo-submit">Add vendo</button></div>
    </form></div></div>
</div>
<script>
document.getElementById('vendoModal').addEventListener('show.bs.modal', function (event) {
    const b = event.relatedTarget, edit = b && b.dataset.mode === 'edit', f = this.querySelector('form');
    f.reset();
    f.querySelector('[name="action"]').value = edit ? 'update' : 'create';
    f.querySelector('[name="id"]').value = edit ? b.dataset.id : '0';
    f.querySelector('[name="name"]').value = edit ? b.dataset.name : '';
    f.querySelector('[name="router_id"]').value = edit ? b.dataset.router : '';
    f.querySelector('[name="server_ip"]').value = edit ? b.dataset.serverIp : '';
    f.querySelector('[name="base_url"]').value = edit ? b.dataset.url : '';
    f.querySelector('[name="interface_name"]').value = edit ? b.dataset.interface : '';
    f.querySelector('[name="password_mode"]').value = edit ? b.dataset.passwordMode : 'blank';
    f.querySelector('[name="charging_enabled"]').checked = edit && b.dataset.charging === '1';
    f.querySelector('[name="eload_enabled"]').checked = edit && b.dataset.eload === '1';
    f.querySelector('[name="enabled"]').checked = edit ? b.dataset.enabled === '1' : true;
    document.getElementById('vendo-enabled-wrap').hidden = !edit;
    document.getElementById('vendoModalTitle').textContent = edit ? 'Edit vendo' : 'Add vendo';
    document.getElementById('vendo-submit').textContent = edit ? 'Save changes' : 'Add vendo';
});
</script>
<?php endif; ?>
