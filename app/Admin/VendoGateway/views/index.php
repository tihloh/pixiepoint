<?php declare(strict_types=1); ?>
<div class="container-fluid py-3">
    <?= $flash ?? '' ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h1 class="h4 mb-1">Vendo Gateway</h1><div class="text-body-secondary small">Native ESP8266/ESP32 vendo devices connected directly to PixiePoint.</div></div>
        <span class="badge text-bg-secondary">Protocol v1</span>
    </div>

    <div class="card mb-3">
        <div class="card-header">Enroll a device</div>
        <div class="card-body">
            <?php if(!$routerId): ?>
                <div class="alert alert-warning mb-0">Select a router first, then return here to enroll a Vendo Gateway device.</div>
            <?php elseif(!$stations): ?>
                <div class="alert alert-warning mb-0">This router has no enabled stations.</div>
            <?php else: ?>
                <p class="text-body-secondary">Select the station first and generate a one-time setup code. Enter that code on the ESP setup page together with Wi-Fi, Gateway URL and the new local setup password. After <strong>Save</strong>, enrollment is automatic.</p>
                <form method="post" class="row g-2 align-items-end">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="setup_code">
                    <div class="col-md-8"><label class="form-label">Station</label><select class="form-select" name="vendo_id" required><option value="">Select station</option><?php foreach($stations as $station): ?><option value="<?= (int)$station['id'] ?>"><?= e($station['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><button class="btn btn-primary w-100">Generate setup code</button></div>
                </form>
                <div class="small text-body-secondary mt-3"><strong>ESP setup:</strong> connect to <code>Vendo-XXXXXX</code> using <code>vendogateway</code>, open <code>http://192.168.4.1</code>, enter this PixiePoint address as Gateway, enter the generated 8-character setup code, choose a new setup password, then save.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><span>Connected devices</span><span class="badge text-bg-secondary"><?= count($rows) ?></span></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Station</th><th>Device</th><th>Hardware</th><th>Firmware</th><th>State</th><th>Last seen</th><th class="text-end">Actions</th></tr></thead><tbody>
        <?php if(!$rows): ?><tr><td colspan="7" class="text-center text-body-secondary py-4">No Vendo Gateway devices are bound yet.</td></tr><?php endif; ?>
        <?php foreach($rows as $row): $device=$row['device'];$binding=$row['binding']; ?><tr><td><?= e($binding['vendo_name'] ?: 'Unbound') ?></td><td><code><?= e($binding['gateway_device_id']) ?></code></td><td><?= $device ? e(trim(($device->hardwareModel ?? '').' '.($device->hardwareRevision ?? ''))) : '<span class="text-danger">Missing</span>' ?></td><td><?= $device ? e($device->firmwareVersion ?? '—') : '—' ?></td><td><?= $device ? '<span class="badge text-bg-'.($device->state==='active'?'success':($device->state==='suspended'?'warning':'danger')).'">'.e($device->state).'</span>' : '—' ?></td><td><?= $device && $device->lastSeenAt ? e($device->lastSeenAt->format('Y-m-d H:i:s')) : 'Never' ?></td><td class="text-end"><form method="post" class="d-inline-flex gap-1 flex-wrap justify-content-end"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($binding['gateway_device_id']) ?>"><button class="btn btn-sm btn-outline-success" name="action" value="coin_enable">Coin on</button><button class="btn btn-sm btn-outline-secondary" name="action" value="coin_disable">Coin off</button><?php if($device && $device->state==='active'): ?><button class="btn btn-sm btn-outline-warning" name="action" value="suspend">Suspend</button><?php else: ?><button class="btn btn-sm btn-outline-success" name="action" value="activate">Activate</button><?php endif; ?><button class="btn btn-sm btn-outline-danger" name="action" value="revoke" onclick="return confirm('Revoke this device? It will need administrative recovery before it can enroll again.')">Revoke</button></form></td></tr><?php endforeach; ?>
        </tbody></table></div>
    </div>
</div>
