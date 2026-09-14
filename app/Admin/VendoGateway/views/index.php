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
                <p class="text-body-secondary">Select the station first and generate a one-time setup code. Enter that code on the ESP setup page together with Wi-Fi, Gateway URL and the Web Admin password. After <strong>Save</strong>, enrollment is automatic.</p>
                <form method="post" class="row g-2 align-items-end">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="setup_code">
                    <div class="col-md-8"><label class="form-label">Station</label><select class="form-select" name="vendo_id" required><option value="">Select station</option><?php foreach($stations as $station): ?><option value="<?= (int)$station['id'] ?>"><?= e($station['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-4"><button class="btn btn-primary w-100">Generate setup code</button></div>
                </form>
                <div class="small text-body-secondary mt-3"><strong>ESP setup:</strong> connect to <code>Vendo-XXXXXX</code> using <code>vendogateway</code>, open <code>http://192.168.4.1</code>, enter this PixiePoint address as Gateway, enter the generated 8-character setup code, create a Web Admin password, then save. After setup the AP is disabled. Hold the board's BOOT/FLASH button for 10 seconds while it is running to enter physical recovery mode.</div>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><span>Connected devices</span><span class="badge text-bg-secondary"><?= count($rows) ?></span></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Station</th><th>Device</th><th>Hardware</th><th>Firmware</th><th>State</th><th>Last seen</th><th class="text-end">Actions</th></tr></thead><tbody>
        <?php if(!$rows): ?><tr><td colspan="7" class="text-center text-body-secondary py-4">No Vendo Gateway devices are bound yet.</td></tr><?php endif; ?>
        <?php foreach($rows as $i=>$row): $device=$row['device'];$binding=$row['binding'];$config=$row['config']??[];$pins=$config['hardware']['pins']??[];$coin=$config['coin']??[]; ?>
            <tr><td><?= e($binding['vendo_name'] ?: 'Unbound') ?></td><td><code><?= e($binding['gateway_device_id']) ?></code></td><td><?= $device ? e(trim(($device->hardwareModel ?? '').' '.($device->hardwareRevision ?? ''))) : '<span class="text-danger">Missing</span>' ?></td><td><?= $device ? e($device->firmwareVersion ?? '—') : '—' ?></td><td><?= $device ? '<span class="badge text-bg-'.($device->state==='active'?'success':($device->state==='suspended'?'warning':'danger')).'">'.e($device->state).'</span>' : '—' ?></td><td><?= $device && $device->lastSeenAt ? e($device->lastSeenAt->format('Y-m-d H:i:s')) : 'Never' ?></td><td class="text-end"><div class="d-inline-flex gap-1 flex-wrap justify-content-end"><form method="post" class="d-inline-flex gap-1"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($binding['gateway_device_id']) ?>"><button class="btn btn-sm btn-outline-success" name="action" value="coin_enable">Coin on</button><button class="btn btn-sm btn-outline-secondary" name="action" value="coin_disable">Coin off</button><?php if($device && $device->state==='active'): ?><button class="btn btn-sm btn-outline-warning" name="action" value="suspend">Suspend</button><?php else: ?><button class="btn btn-sm btn-outline-success" name="action" value="activate">Activate</button><?php endif; ?><button class="btn btn-sm btn-outline-danger" name="action" value="revoke" onclick="return confirm('Revoke this device? It will need administrative recovery before it can enroll again.')">Revoke</button></form><button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="collapse" data-bs-target="#vg-config-<?= $i ?>">Configure</button></div></td></tr>
            <tr class="collapse" id="vg-config-<?= $i ?>"><td colspan="7" class="bg-body-tertiary"><form method="post" class="row g-2 align-items-end py-2"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($binding['gateway_device_id']) ?>"><input type="hidden" name="action" value="save_config"><div class="col-12"><div class="fw-semibold">Hardware configuration</div><div class="small text-body-secondary">Managed by PixiePoint and synchronized to the ESP. The ESP validates the GPIO mapping before applying it. GPIO0 is reserved for the built-in BOOT/FLASH recovery button.</div></div><div class="col-6 col-md-2"><label class="form-label small">Coin GPIO</label><input class="form-control" type="number" min="1" max="39" name="coin_pin" value="<?= (int)($pins['coin']??5) ?>" required></div><div class="col-6 col-md-2"><label class="form-label small">Relay GPIO</label><input class="form-control" type="number" min="1" max="39" name="relay_pin" value="<?= (int)($pins['relay']??12) ?>" required></div><div class="col-6 col-md-2"><label class="form-label small">Status LED GPIO</label><input class="form-control" type="number" min="1" max="39" name="status_led_pin" value="<?= (int)($pins['status_led']??2) ?>" required></div><div class="col-6 col-md-3"><label class="form-label small">Coin settle time (ms)</label><input class="form-control" type="number" min="50" max="2000" name="coin_settle_ms" value="<?= (int)($coin['settle_ms']??350) ?>" required></div><div class="col-md-3"><button class="btn btn-primary w-100">Save configuration</button></div></form></td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>
</div>
