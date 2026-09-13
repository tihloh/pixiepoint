<?php declare(strict_types=1); ?>
<div class="container-fluid py-3">
    <?= $flash ?? '' ?>
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div><h1 class="h4 mb-1">Vendo Gateway</h1><div class="text-body-secondary small">Native ESP8266/ESP32 vendo devices connected directly to PixiePoint.</div></div>
        <span class="badge text-bg-secondary">Protocol v1</span>
    </div>

    <div class="card mb-3">
        <div class="card-header">Pair a device</div>
        <div class="card-body">
            <?php if(!$routerId): ?>
                <div class="alert alert-warning mb-0">Select a router first, then return here to bind a Vendo Gateway device to one of its stations.</div>
            <?php elseif(!$stations): ?>
                <div class="alert alert-warning mb-0">This router has no enabled stations.</div>
            <?php else: ?>
                <form method="post" class="row g-2 align-items-end">
                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="claim">
                    <div class="col-md-4"><label class="form-label">Pairing code</label><input class="form-control" name="pairing_code" inputmode="numeric" maxlength="6" pattern="[0-9]{6}" placeholder="000000" required></div>
                    <div class="col-md-5"><label class="form-label">Station</label><select class="form-select" name="vendo_id" required><option value="">Select station</option><?php foreach($stations as $station): ?><option value="<?= (int)$station['id'] ?>"><?= e($station['name']) ?></option><?php endforeach; ?></select></div>
                    <div class="col-md-3"><button class="btn btn-primary w-100">Pair device</button></div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><span>Connected devices</span><span class="badge text-bg-secondary"><?= count($rows) ?></span></div>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead><tr><th>Station</th><th>Device</th><th>Hardware</th><th>Firmware</th><th>State</th><th>Last seen</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                <?php if(!$rows): ?><tr><td colspan="7" class="text-center text-body-secondary py-4">No Vendo Gateway devices are bound yet.</td></tr><?php endif; ?>
                <?php foreach($rows as $row): $device=$row['device'];$binding=$row['binding']; ?>
                    <tr>
                        <td><?= e($binding['vendo_name'] ?: 'Unbound') ?></td>
                        <td><code><?= e($binding['gateway_device_id']) ?></code></td>
                        <td><?= $device ? e(trim(($device->hardwareModel ?? '').' '.($device->hardwareRevision ?? ''))) : '<span class="text-danger">Missing</span>' ?></td>
                        <td><?= $device ? e($device->firmwareVersion ?? '—') : '—' ?></td>
                        <td><?= $device ? '<span class="badge text-bg-'.($device->state==='active'?'success':($device->state==='suspended'?'warning':'danger')).'">'.e($device->state).'</span>' : '—' ?></td>
                        <td><?= $device && $device->lastSeenAt ? e($device->lastSeenAt->format('Y-m-d H:i:s')) : 'Never' ?></td>
                        <td class="text-end">
                            <form method="post" class="d-inline-flex gap-1 flex-wrap justify-content-end">
                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($binding['gateway_device_id']) ?>">
                                <button class="btn btn-sm btn-outline-success" name="action" value="coin_enable">Coin on</button>
                                <button class="btn btn-sm btn-outline-secondary" name="action" value="coin_disable">Coin off</button>
                                <?php if($device && $device->state==='active'): ?><button class="btn btn-sm btn-outline-warning" name="action" value="suspend">Suspend</button><?php else: ?><button class="btn btn-sm btn-outline-success" name="action" value="activate">Activate</button><?php endif; ?>
                                <button class="btn btn-sm btn-outline-danger" name="action" value="revoke" onclick="return confirm('Revoke this device? It will need administrative recovery before it can pair again.')">Revoke</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
