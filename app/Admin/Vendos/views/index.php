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
        <p class="muted">Configure the local PisoWiFi coin slots that PixiePoint can offer on each hotspot.</p>
    </div>
</div>

<?= $message ?>

<?php if ($canManageVendos): ?>
<section class="panel">
    <h2>Add vendo</h2>
    <p class="muted">Link one physical coin-slot controller to a registered router and tell PixiePoint how the client browser reaches it.</p>
    <form method="post" class="row g-3">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="col-md-6"><label>Name</label><input name="name" required maxlength="160"><small class="text-body-secondary">Friendly name customers and operators can recognize, such as Front Vendo.</small></div>
        <div class="col-md-6"><label>Router</label><select name="router_id" required><option value="">Select router</option><?php foreach ($routers as $router): ?><option value="<?= e($router['id']) ?>"><?= e($router['name']) ?> · <?= e($router['identity']) ?></option><?php endforeach; ?></select><small class="text-body-secondary">Choose the hotspot router where this vendo should appear.</small></div>
        <div class="col-md-6"><label>Local base URL</label><input name="base_url" placeholder="http://10.0.0.2" required maxlength="255"><small class="text-body-secondary">Local address the connected customer device uses to reach the vendo controller.</small></div>
        <div class="col-md-6"><label>Interface</label><input name="interface_name" placeholder="Optional, e.g. bridge-HS" maxlength="128"><small class="text-body-secondary">Optional RouterOS interface match. Leave blank to allow any interface on the selected router.</small></div>
        <div class="col-md-4"><label>Password mode</label><select name="password_mode"><option value="blank">Blank</option><option value="voucher">Voucher</option></select><small class="text-body-secondary">Defines whether MikroTik hotspot login uses a blank password or the voucher as password.</small></div>
        <div class="col-md-4 d-flex flex-column justify-content-end"><label class="form-check"><input class="form-check-input" type="checkbox" name="charging_enabled" value="1"><span class="form-check-label">Phone charging</span></label><small class="text-body-secondary">Show charging controls when this vendo supports them.</small></div>
        <div class="col-md-4 d-flex flex-column justify-content-end"><label class="form-check"><input class="form-check-input" type="checkbox" name="eload_enabled" value="1"><span class="form-check-label">E-load</span></label><small class="text-body-secondary">Show e-load controls when this vendo supports them.</small></div>
        <div class="col-12"><button class="button" type="submit">Add vendo</button><small class="d-block text-body-secondary mt-2">Saves the vendo so matching hotspot clients can discover it online.</small></div>
    </form>
</section>
<?php endif; ?>

<section class="panel">
    <h2>Configured vendos</h2>
    <p class="muted">Shows which coin slots are assigned to which routers and the local address clients will use.</p>
    <table>
        <thead><tr><th>Name</th><th>Owner</th><th>Router</th><th>Interface</th><th>Local URL</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($vendos as $vendo): ?>
            <tr>
                <td><?= e($vendo['name']) ?></td>
                <td><?= e($vendo['owner_email'] ?: 'Platform') ?></td>
                <td><?= e($vendo['router_name']) ?></td>
                <td><?= e($vendo['interface_name'] ?: 'Any') ?></td>
                <td class="code"><?= e($vendo['base_url']) ?></td>
                <td><span class="badge <?= $vendo['enabled'] ? '' : 'off' ?>"><?= $vendo['enabled'] ? 'enabled' : 'disabled' ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$vendos): ?><tr><td colspan="6" class="empty">No vendos configured.</td></tr><?php endif; ?>
        </tbody>
    </table>
</section>
