<?php
/** @var string $message */
/** @var array $vendos */
/** @var array $routers */
/** @var array $themes */
/** @var bool $canManageVendos */
/** @var string $csrf */
?>

<div class="heading">
    <div>
        <h1>Hotspot Stations</h1>
        <p class="muted">Configure each customer-facing hotspot. A station can use a Vendo controller or run as voucher-only.</p>
    </div>
    <?php if ($canManageVendos): ?>
        <button class="button" type="button" data-bs-toggle="modal" data-bs-target="#stationModal" data-mode="create">Add station</button>
    <?php endif; ?>
</div>

<?= $message ?>

<section class="panel">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 align-items-md-center mb-3">
        <div>
            <h2 class="mb-1">Configured stations</h2>
            <p class="muted mb-0">Vendo stations support coin insertion. Voucher stations show the portal without requiring a Vendo device.</p>
        </div>
        <a class="btn btn-outline-primary" href="/emulator/">Test portal</a>
    </div>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Name / Wi-Fi</th><th>Type</th><th>Router</th><th>Server IP</th><th>Interface</th><th>Controller</th><th>Theme</th><th>Status</th><?php if ($canManageVendos): ?><th class="text-end">Action</th><?php endif; ?></tr></thead>
            <tbody>
            <?php foreach ($vendos as $v): $type = ($v['station_type'] ?? (trim((string)($v['base_url'] ?? '')) !== '' ? 'vendo' : 'voucher')); ?>
                <tr>
                    <td><strong><?= e($v['name']) ?></strong><?php if (!empty($v['debug_enabled'])): ?><div class="small text-warning">Debug enabled</div><?php endif; ?></td>
                    <td><span class="badge rounded-pill <?= $type === 'vendo' ? 'text-bg-primary' : 'text-bg-info' ?>"><?= $type === 'vendo' ? 'Vendo' : 'Voucher / Hotspot' ?></span></td>
                    <td><?= e($v['router_name']) ?><div class="small text-body-secondary"><?= e($v['router_identity']) ?></div></td>
                    <td class="code"><?= e($v['server_ip'] ?: 'Not set') ?></td>
                    <td><?= e($v['interface_name'] ?: '—') ?></td>
                    <td class="code"><?= $type === 'vendo' ? e(preg_replace('~^https?://~i','',(string)$v['base_url'])) : 'Not required' ?></td>
                    <td><?= e($v['portal_theme_id'] ? 'Custom' : 'Router') ?></td>
                    <td><span class="badge <?= $v['enabled'] ? '' : 'off' ?>"><?= $v['enabled'] ? 'Enabled' : 'Disabled' ?></span></td>
                    <?php if ($canManageVendos): ?>
                    <td class="text-end"><div class="d-inline-flex gap-1">
                        <form method="post" class="d-inline"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="toggle_debug"><input type="hidden" name="id" value="<?= e($v['id']) ?>"><input type="hidden" name="debug_enabled" value="<?= !empty($v['debug_enabled']) ? '0' : '1' ?>"><button class="btn btn-sm <?= !empty($v['debug_enabled']) ? 'btn-warning' : 'btn-outline-secondary' ?>" type="submit">Debug: <?= !empty($v['debug_enabled']) ? 'On' : 'Off' ?></button></form>
                        <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#stationModal" data-mode="edit" data-id="<?= e($v['id']) ?>" data-name="<?= e($v['name']) ?>" data-type="<?= e($type) ?>" data-router="<?= e($v['router_id']) ?>" data-theme="<?= e($v['portal_theme_id'] ?? 0) ?>" data-url="<?= e(preg_replace('~^https?://~i','',(string)$v['base_url'])) ?>" data-server-ip="<?= e($v['server_ip'] ?? '') ?>" data-subnet="<?= e($v['client_subnet'] ?? '') ?>" data-interface="<?= e($v['interface_name'] ?? '') ?>" data-password-mode="<?= e($v['password_mode']) ?>" data-charging="<?= $v['charging_enabled'] ? '1' : '0' ?>" data-eload="<?= $v['eload_enabled'] ? '1' : '0' ?>" data-enabled="<?= $v['enabled'] ? '1' : '0' ?>">Edit</button>
                    </div></td>
                    <?php endif; ?>
                </tr>
            <?php endforeach; ?>
            <?php if (!$vendos): ?><tr><td colspan="<?= $canManageVendos ? 9 : 8 ?>" class="empty">No hotspot stations configured. Add a Vendo station or a voucher-only station.</td></tr><?php endif; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($canManageVendos): ?>
<div class="modal fade" id="stationModal" tabindex="-1" aria-labelledby="stationModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="post">
        <div class="modal-header"><div><h2 class="modal-title fs-5 mb-1" id="stationModalTitle">Add hotspot station</h2><p class="small text-body-secondary mb-0">Choose whether this hotspot has a coin-slot Vendo controller or accepts existing vouchers only.</p></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create"><input type="hidden" name="id" value="0">
            <div class="row g-3">
                <div class="col-md-6"><label>Station type</label><select name="station_type" id="station-type" required><option value="vendo">Vendo station</option><option value="voucher">Voucher / Hotspot station</option></select><small class="text-body-secondary">Voucher stations do not require a Vendo controller.</small></div>
                <div class="col-md-6"><label>Name / Wi-Fi name</label><input name="name" required maxlength="160"><small class="text-body-secondary">Customer-facing hotspot or business name.</small></div>
                <div class="col-md-6"><label>Router</label><select name="router_id" required><option value="">Select router</option><?php foreach ($routers as $r): ?><option value="<?= e($r['id']) ?>"><?= e($r['name']) ?> · <?= e($r['identity']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label>Portal theme</label><select name="portal_theme_id"><option value="0">Inherit router theme</option><?php foreach ($themes as $theme): ?><option value="<?= e($theme['id']) ?>"><?= e($theme['name']) ?></option><?php endforeach; ?></select></div>
                <div class="col-md-6"><label>Server IP</label><input name="server_ip" placeholder="10.0.3.1" required maxlength="45"><small class="text-body-secondary">Matches MikroTik $(server-address).</small></div>
                <div class="col-md-6"><label>Client subnet</label><input name="client_subnet" placeholder="Optional, e.g. 10.0.3.0/24" maxlength="64"></div>
                <div class="col-md-6"><label>Interface</label><input name="interface_name" placeholder="Optional, e.g. bridge-HS" maxlength="128"></div>
                <div class="col-md-6"><label>Password mode</label><select name="password_mode"><option value="blank">Blank password</option><option value="voucher">Voucher as password</option></select></div>

                <div class="col-12" id="vendo-fields">
                    <div class="border rounded-3 p-3">
                        <div class="fw-semibold mb-3">Vendo controller</div>
                        <div class="row g-3">
                            <div class="col-md-6"><label>Controller address</label><input name="base_url" placeholder="10.0.3.2" maxlength="255"><small class="text-body-secondary">IP, hostname, or http:// / https:// URL of the local Vendo controller.</small></div>
                            <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="charging_enabled" value="1" id="station-charging"><label class="form-check-label" for="station-charging">Phone charging</label></div></div>
                            <div class="col-md-3 d-flex align-items-end"><div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="eload_enabled" value="1" id="station-eload"><label class="form-check-label" for="station-eload">E-load</label></div></div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6" id="station-enabled-wrap" hidden><div class="form-check"><input class="form-check-input" type="checkbox" name="enabled" value="1" id="station-enabled"><label class="form-check-label" for="station-enabled">Enabled</label></div></div>
            </div>
        </div>
        <div class="modal-footer"><a class="btn btn-outline-info me-auto" href="/emulator/">Open emulator</a><button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button><button class="button" type="submit" id="station-submit">Add station</button></div>
    </form></div></div>
</div>
<script>
(function(){
    const modal=document.getElementById('stationModal');
    const type=document.getElementById('station-type');
    const vendoFields=document.getElementById('vendo-fields');
    function syncType(){const isVendo=type.value==='vendo';vendoFields.hidden=!isVendo;modal.querySelector('[name="base_url"]').required=isVendo;}
    type.addEventListener('change',syncType);
    modal.addEventListener('show.bs.modal',function(event){
        const button=event.relatedTarget;const isEdit=button&&button.dataset.mode==='edit';const form=this.querySelector('form');form.reset();
        form.querySelector('[name="action"]').value=isEdit?'update':'create';form.querySelector('[name="id"]').value=isEdit?button.dataset.id:'0';
        form.querySelector('[name="station_type"]').value=isEdit?button.dataset.type:'vendo';form.querySelector('[name="name"]').value=isEdit?button.dataset.name:'';form.querySelector('[name="router_id"]').value=isEdit?button.dataset.router:'';form.querySelector('[name="portal_theme_id"]').value=isEdit?button.dataset.theme:'0';form.querySelector('[name="server_ip"]').value=isEdit?button.dataset.serverIp:'';form.querySelector('[name="client_subnet"]').value=isEdit?button.dataset.subnet:'';form.querySelector('[name="base_url"]').value=isEdit?button.dataset.url:'';form.querySelector('[name="interface_name"]').value=isEdit?button.dataset.interface:'';form.querySelector('[name="password_mode"]').value=isEdit?button.dataset.passwordMode:'blank';form.querySelector('[name="charging_enabled"]').checked=isEdit&&button.dataset.charging==='1';form.querySelector('[name="eload_enabled"]').checked=isEdit&&button.dataset.eload==='1';form.querySelector('[name="enabled"]').checked=isEdit?button.dataset.enabled==='1':true;
        document.getElementById('station-enabled-wrap').hidden=!isEdit;document.getElementById('stationModalTitle').textContent=isEdit?'Edit hotspot station':'Add hotspot station';document.getElementById('station-submit').textContent=isEdit?'Save changes':'Add station';syncType();
    });
})();
</script>
<?php endif; ?>
