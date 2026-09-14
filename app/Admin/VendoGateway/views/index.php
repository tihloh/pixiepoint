<?php declare(strict_types=1); ?>
<div class="container-fluid py-3">
    <?= $flash ?? '' ?>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div><h1 class="h4 mb-1">Manage Vendo</h1><div class="text-body-secondary small">Paired ESP8266/ESP32 devices for the selected MikroTik router.</div></div>
        <a class="btn btn-outline-secondary" href="/admin/routers/<?= (int)$routerId ?>#stations">Back to stations</a>
    </div>
    <?php if(!$gatewayConfigured): ?><div class="alert alert-warning">Vendo Gateway is not configured on this PixiePoint instance.</div><?php endif; ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><span>Connected Vendos</span><span class="badge text-bg-secondary"><?= count($rows) ?></span></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Vendo</th><th>Station</th><th>Device</th><th>Firmware</th><th>Connection</th><th>State</th><th class="text-end">Actions</th></tr></thead><tbody>
        <?php if(!$rows): ?><tr><td colspan="7" class="text-center text-body-secondary py-4">No Vendo devices are paired with this router yet.</td></tr><?php endif; ?>
        <?php foreach($rows as $i=>$row): $device=$row['device'];$binding=$row['binding'];$config=$row['config']??[];$reported=$row['reported']??[];$pins=$config['hardware']['pins']??$reported['hardware']['pins']??[];$coin=$config['coin']??$reported['coin']??[];$selected=(bool)($row['selected']??false);$online=$device&&$device->lastSeenAt&&$device->lastSeenAt->getTimestamp()>=time()-180; ?>
            <tr class="<?= $selected?'table-active':'' ?>">
                <td><strong><?= e($binding['vendo_name']?:'Vendo') ?></strong><div class="small text-body-secondary"><code><?= e($binding['gateway_device_id']) ?></code></div></td>
                <td><?= e($binding['station_name']?:'—') ?></td>
                <td><?= $device?e(trim(($device->hardwareModel??'').' '.($device->hardwareRevision??''))):'<span class="text-danger">Missing</span>' ?></td>
                <td><?= $device?e($device->firmwareVersion??'—'):'—' ?></td>
                <td><span class="badge text-bg-<?= $online?'success':'secondary' ?>"><?= $online?'Online':'Offline' ?></span><?php if(!empty($reported['ip'])):?><div class="small text-body-secondary"><?= e($reported['ip']) ?></div><?php endif;?></td>
                <td><?= $device?'<span class="badge text-bg-'.($device->state==='active'?'success':($device->state==='suspended'?'warning':'danger')).'">'.e($device->state).'</span>':'—' ?><?php if($device&&$device->lastSeenAt):?><div class="small text-body-secondary">Seen <?= e($device->lastSeenAt->format('Y-m-d H:i:s')) ?></div><?php endif;?></td>
                <td class="text-end"><button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#vg-manage-<?= $i ?>">Manage</button></td>
            </tr>
            <tr class="collapse <?= $selected?'show':'' ?>" id="vg-manage-<?= $i ?>"><td colspan="7" class="bg-body-tertiary">
                <div class="row g-3 py-2">
                    <div class="col-lg-5">
                        <div class="border rounded p-3 h-100">
                            <div class="fw-semibold mb-3">Device controls</div>
                            <form method="post" class="row g-2 mb-3"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($binding['gateway_device_id']) ?>"><div class="col-8"><input name="vendo_name" maxlength="160" value="<?= e($binding['vendo_name']?:'Vendo') ?>" required></div><div class="col-4"><button class="btn btn-outline-primary w-100" name="action" value="rename">Rename</button></div></form>
                            <form method="post" class="d-flex flex-wrap gap-2"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($binding['gateway_device_id']) ?>"><button class="btn btn-sm btn-outline-success" name="action" value="coin_enable">Coin on</button><button class="btn btn-sm btn-outline-secondary" name="action" value="coin_disable">Coin off</button><button class="btn btn-sm btn-outline-primary" name="action" value="firmware_check">Check firmware</button><button class="btn btn-sm btn-outline-secondary" name="action" value="restart" onclick="return confirm('Restart this Vendo device?')">Restart</button><?php if($device&&$device->state==='active'):?><button class="btn btn-sm btn-outline-warning" name="action" value="suspend">Suspend</button><?php else:?><button class="btn btn-sm btn-outline-success" name="action" value="activate">Activate</button><?php endif;?><button class="btn btn-sm btn-outline-danger" name="action" value="revoke" onclick="return confirm('Revoke this device? It will require administrative recovery before it can enroll again.')">Revoke</button></form>
                            <div class="small text-body-secondary mt-3">Reported state: <?= e($reported['state']??'Unknown') ?> · RSSI: <?= isset($reported['rssi'])?e((string)$reported['rssi']).' dBm':'—' ?> · Coin: <?= !empty($reported['coin_enabled'])?'enabled':'disabled' ?></div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <form method="post" class="border rounded p-3"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($binding['gateway_device_id']) ?>"><input type="hidden" name="action" value="save_config"><div class="fw-semibold mb-1">Hardware configuration</div><div class="small text-body-secondary mb-3">Saved in PixiePoint and synchronized to the ESP.</div><div class="row g-2"><div class="col-6 col-md-3"><label>Coin GPIO</label><input type="number" min="1" max="39" name="coin_pin" value="<?= (int)($pins['coin']??5) ?>" required></div><div class="col-6 col-md-3"><label>Relay GPIO</label><input type="number" min="1" max="39" name="relay_pin" value="<?= (int)($pins['relay']??12) ?>" required></div><div class="col-6 col-md-3"><label>Status LED GPIO</label><input type="number" min="1" max="39" name="status_led_pin" value="<?= (int)($pins['status_led']??13) ?>" required></div><div class="col-6 col-md-3"><label>Settle ms</label><input type="number" min="50" max="2000" name="coin_settle_ms" value="<?= (int)($coin['settle_ms']??350) ?>" required></div><div class="col-12"><button class="button">Save hardware configuration</button></div></div></form>
                    </div>
                </div>
            </td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>
</div>
