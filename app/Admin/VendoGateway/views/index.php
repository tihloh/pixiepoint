<?php declare(strict_types=1);
$gpioProfiles=[
    'esp8266'=>[
        'coin'=>[
            4=>'GPIO4 (D2) — General I/O',
            5=>'GPIO5 (D1) — General I/O',
            12=>'GPIO12 (D6) — SPI MISO / General I/O',
            13=>'GPIO13 (D7) — SPI MOSI / General I/O',
            14=>'GPIO14 (D5) — SPI CLK / General I/O',
        ],
        'output'=>[
            4=>'GPIO4 (D2) — General I/O',
            5=>'GPIO5 (D1) — General I/O',
            12=>'GPIO12 (D6) — SPI MISO / General I/O',
            13=>'GPIO13 (D7) — SPI MOSI / General I/O',
            14=>'GPIO14 (D5) — SPI CLK / General I/O',
            16=>'GPIO16 (D0) — General output / wake',
        ],
        'defaults'=>['coin'=>5,'relay'=>12],
    ],
    'esp32'=>[
        'coin'=>[
            4=>'GPIO4 — General I/O',13=>'GPIO13 — HSPI MOSI / General I/O',14=>'GPIO14 — HSPI CLK / General I/O',16=>'GPIO16 — General I/O',17=>'GPIO17 — General I/O',18=>'GPIO18 — VSPI CLK / General I/O',19=>'GPIO19 — VSPI MISO / General I/O',21=>'GPIO21 — I²C SDA / General I/O',22=>'GPIO22 — I²C SCL / General I/O',23=>'GPIO23 — VSPI MOSI / General I/O',25=>'GPIO25 — DAC1 / General I/O',26=>'GPIO26 — DAC2 / General I/O',27=>'GPIO27 — General I/O',32=>'GPIO32 — ADC / General I/O',33=>'GPIO33 — ADC / General I/O',
        ],
        'output'=>[
            4=>'GPIO4 — General I/O',13=>'GPIO13 — HSPI MOSI / General I/O',14=>'GPIO14 — HSPI CLK / General I/O',16=>'GPIO16 — General I/O',17=>'GPIO17 — General I/O',18=>'GPIO18 — VSPI CLK / General I/O',19=>'GPIO19 — VSPI MISO / General I/O',21=>'GPIO21 — I²C SDA / General I/O',22=>'GPIO22 — I²C SCL / General I/O',23=>'GPIO23 — VSPI MOSI / General I/O',25=>'GPIO25 — DAC1 / General I/O',26=>'GPIO26 — DAC2 / General I/O',27=>'GPIO27 — General I/O',32=>'GPIO32 — ADC / General I/O',33=>'GPIO33 — ADC / General I/O',
        ],
        'defaults'=>['coin'=>27,'relay'=>26],
    ],
];
$pinSelect=function(string $name,int $selected,array $options,string $class=''): string
{
    $html='<select name="'.e($name).'" class="form-select '.e($class).'" required>';
    foreach($options as $gpio=>$label)$html.='<option value="'.(int)$gpio.'"'.((int)$gpio===$selected?' selected':'').'>'.e($label).'</option>';
    return $html.'</select>';
};
$totalVendos=count($rows);
$onlineVendos=0;
$activeVendos=0;
$updatesAvailable=0;
foreach($rows as $row){
    $summaryDevice=$row['device']??null;
    $summaryFirmware=$row['firmware']??null;
    if($summaryDevice&&$summaryDevice->lastSeenAt&&$summaryDevice->lastSeenAt->getTimestamp()>=time()-180)$onlineVendos++;
    if(($summaryDevice?->state??null)==='active')$activeVendos++;
    if(($summaryFirmware['update_available']??null)===true)$updatesAvailable++;
}
?>
<div class="container-fluid py-3">
    <div id="vendo-ajax-message"><?= $flash ?? '' ?></div>

    <div class="d-flex flex-column flex-lg-row justify-content-between align-items-lg-center gap-3 mb-4">
        <div>
            <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                <h1 class="h3 mb-0">Vendo Manager</h1>
                <span class="badge rounded-pill text-bg-secondary"><?= e($totalVendos) ?> device<?= $totalVendos===1?'':'s' ?></span>
            </div>
            <p class="text-body-secondary mb-0">Manage ESP8266/ESP32 Vendo devices paired to the selected gateway.</p>
        </div>
        <a class="btn btn-outline-secondary align-self-start align-self-lg-auto" href="/admin/routers/<?= (int)$routerId ?>#stations">Back to gateway</a>
    </div>

    <?php if(!$gatewayConfigured): ?>
        <div class="alert alert-warning">
            <strong>Vendo Gateway is not configured.</strong>
            <div class="small mt-1">Device commands and configuration changes are unavailable until the gateway service is configured.</div>
        </div>
    <?php endif; ?>

    <div class="row g-3 mb-4" aria-label="Vendo summary">
        <div class="col-6 col-xl-3"><div class="card card-body h-100"><div class="small text-body-secondary">Total Vendos</div><div class="fs-3 fw-semibold"><?= e($totalVendos) ?></div></div></div>
        <div class="col-6 col-xl-3"><div class="card card-body h-100"><div class="small text-body-secondary">Online now</div><div class="fs-3 fw-semibold"><?= e($onlineVendos) ?></div></div></div>
        <div class="col-6 col-xl-3"><div class="card card-body h-100"><div class="small text-body-secondary">Active</div><div class="fs-3 fw-semibold"><?= e($activeVendos) ?></div></div></div>
        <div class="col-6 col-xl-3"><div class="card card-body h-100"><div class="small text-body-secondary">Updates available</div><div class="fs-3 fw-semibold"><?= e($updatesAvailable) ?></div></div></div>
    </div>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-end gap-2 mb-3">
        <div><h2 class="h5 mb-1">Vendo devices</h2><div class="small text-body-secondary">Open a device to manage controls, GPIO configuration, and firmware policy.</div></div>
        <?php if($rows): ?><div class="small text-body-secondary">Only one device panel stays open at a time.</div><?php endif; ?>
    </div>

    <?php if(!$rows): ?>
        <div class="card card-body text-center py-5">
            <div class="h5 mb-2">No Vendos paired yet</div>
            <p class="text-body-secondary mb-3">Pair a Vendo from a hotspot station on the selected gateway.</p>
            <div><a class="btn btn-primary" href="/admin/routers/<?= (int)$routerId ?>#stations">Go to hotspot stations</a></div>
        </div>
    <?php else: ?>
        <div id="vendo-device-list">
            <?php foreach($rows as $i=>$row):
                $device=$row['device'];
                $binding=$row['binding'];
                $config=$row['config']??[];
                $reported=$row['reported']??[];
                $firmware=$row['firmware']??null;
                $pins=$config['hardware']['pins']??$reported['hardware']['pins']??[];
                $coin=$config['coin']??$reported['coin']??[];
                $fwConfig=$config['firmware']??[];
                $selected=(bool)($row['selected']??false);
                $online=$device&&$device->lastSeenAt&&$device->lastSeenAt->getTimestamp()>=time()-180;
                $deviceId=(string)$binding['gateway_device_id'];
                $platform=strtolower((string)($firmware['target']??$reported['hardware']['platform']??''));
                $platformKnown=isset($gpioProfiles[$platform]);
                $profile=$platformKnown?$gpioProfiles[$platform]:null;
                $defaults=$profile['defaults']??[];
                $currentFirmware=(string)($firmware['current_version']??$device?->firmwareVersion??'—');
                $latestFirmware=(string)($firmware['latest_version']??'');
                $updateAvailable=$firmware['update_available']??null;
                $state=(string)($device?->state??'unknown');
                $stateClass=match($state){'active'=>'success','suspended'=>'warning','revoked'=>'danger',default=>'secondary'};
                $hardware=trim((string)($device?->hardwareModel??'').' '.(string)($device?->hardwareRevision??''))?:'Unknown hardware';
                $lastSeen=$device?->lastSeenAt?->format('Y-m-d H:i:s')??'Never';
                $ip=trim((string)($reported['ip']??''))?:'Not reported';
                $rssi=isset($reported['rssi'])?(string)$reported['rssi'].' dBm':'Not reported';
                $coinEnabled=!empty($reported['coin_enabled']);
            ?>
                <article class="card mb-3 overflow-hidden" data-vendo-row="<?= e($deviceId) ?>">
                    <div class="card-body">
                        <div class="d-flex flex-column flex-xl-row justify-content-between gap-3">
                            <div class="min-w-0">
                                <div class="d-flex flex-wrap align-items-center gap-2 mb-1">
                                    <h3 class="h5 mb-0" data-vendo-name><?= e($binding['vendo_name']?:'Vendo') ?></h3>
                                    <span class="badge text-bg-<?= $online?'success':'secondary' ?>"><?= $online?'Online':'Offline' ?></span>
                                    <span data-device-state><span class="badge text-bg-<?= e($stateClass) ?>"><?= e(ucfirst($state)) ?></span></span>
                                    <?php if($updateAvailable===true): ?><span class="badge text-bg-warning">Firmware update</span><?php endif; ?>
                                </div>
                                <div class="small text-body-secondary"><?= e($binding['station_name']?:'No station') ?><span class="mx-1">·</span><code><?= e($deviceId) ?></code></div>
                            </div>
                            <div><button class="btn btn-primary" type="button" data-vendo-toggle data-bs-toggle="collapse" data-bs-target="#vg-manage-<?= $i ?>" aria-expanded="<?= $selected?'true':'false' ?>" aria-controls="vg-manage-<?= $i ?>"><?= $selected?'Close':'Manage' ?></button></div>
                        </div>

                        <div class="row g-3 mt-1 small">
                            <div class="col-6 col-md-4 col-xl"><div class="text-body-secondary">Device</div><div class="fw-medium"><?= e($hardware) ?></div><div class="text-body-secondary text-uppercase"><?= e($platformKnown?$platform:'Target unknown') ?></div></div>
                            <div class="col-6 col-md-4 col-xl"><div class="text-body-secondary">IP address</div><div class="fw-medium font-monospace"><?= e($ip) ?></div></div>
                            <div class="col-6 col-md-4 col-xl"><div class="text-body-secondary">Signal</div><div class="fw-medium"><?= e($rssi) ?></div></div>
                            <div class="col-6 col-md-4 col-xl"><div class="text-body-secondary">Coin input</div><div class="fw-medium" data-coin-summary><?= $coinEnabled?'Enabled':'Disabled' ?></div></div>
                            <div class="col-6 col-md-4 col-xl"><div class="text-body-secondary">Firmware</div><div class="fw-medium" data-firmware-summary><?= $currentFirmware==='—'?'Unknown':'v'.e(ltrim($currentFirmware,'vV')) ?></div><?php if($updateAvailable===true): ?><div class="text-warning">v<?= e(ltrim($latestFirmware,'vV')) ?> available</div><?php elseif($updateAvailable===false): ?><div class="text-body-secondary">Up to date</div><?php else: ?><div class="text-body-secondary">Latest unknown</div><?php endif; ?></div>
                            <div class="col-6 col-md-4 col-xl"><div class="text-body-secondary">Last seen</div><div class="fw-medium"><?= e($lastSeen) ?></div></div>
                        </div>
                    </div>

                    <div class="collapse <?= $selected?'show':'' ?>" id="vg-manage-<?= $i ?>" data-vendo-manage="<?= e($deviceId) ?>" data-bs-parent="#vendo-device-list">
                        <div class="border-top bg-body-tertiary p-3 p-lg-4">
                            <div class="row g-4">
                                <div class="col-xl-5">
                                    <div class="card h-100">
                                        <div class="card-header"><strong>Device controls</strong></div>
                                        <div class="card-body">
                                            <form method="post" class="js-vendo-form mb-4">
                                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($deviceId) ?>"><input type="hidden" name="action" value="rename">
                                                <label class="form-label">Vendo name</label>
                                                <div class="input-group"><input class="form-control" name="vendo_name" maxlength="160" value="<?= e($binding['vendo_name']?:'Vendo') ?>" required><button class="btn btn-outline-primary">Rename</button></div>
                                            </form>

                                            <div class="mb-2 fw-semibold">Coin acceptor</div>
                                            <form method="post" class="d-flex flex-wrap gap-2 js-vendo-form mb-4">
                                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($deviceId) ?>">
                                                <button class="btn btn-sm btn-outline-success" name="action" value="coin_enable">Enable coin</button><button class="btn btn-sm btn-outline-secondary" name="action" value="coin_disable">Disable coin</button>
                                                <span class="small text-body-secondary align-self-center">Reported: <strong class="text-body" data-coin-state><?= $coinEnabled?'enabled':'disabled' ?></strong></span>
                                            </form>

                                            <div class="mb-2 fw-semibold">Device state</div>
                                            <form method="post" class="d-flex flex-wrap gap-2 js-vendo-form">
                                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($deviceId) ?>">
                                                <button class="btn btn-sm btn-outline-secondary" name="action" value="restart" onclick="return confirm('Restart this Vendo device?')">Restart</button>
                                                <?php if($device&&$device->state==='active'): ?><button class="btn btn-sm btn-outline-warning" name="action" value="suspend" data-state-action>Suspend</button><?php else: ?><button class="btn btn-sm btn-outline-success" name="action" value="activate" data-state-action>Activate</button><?php endif; ?>
                                            </form>

                                            <div class="border-top mt-4 pt-3">
                                                <div class="small text-body-secondary mb-2">Danger zone</div>
                                                <form method="post" class="js-vendo-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($deviceId) ?>"><button class="btn btn-sm btn-outline-danger" name="action" value="revoke" onclick="return confirm('Revoke this device? It will require administrative recovery before it can enroll again.')">Revoke device</button></form>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-xl-7">
                                    <div class="card h-100">
                                        <div class="card-header d-flex justify-content-between align-items-center gap-2"><strong>Hardware configuration</strong><span class="badge text-bg-secondary text-uppercase"><?= e($platformKnown?$platform:'Unknown target') ?></span></div>
                                        <div class="card-body">
                                            <?php if($platformKnown): ?>
                                                <form method="post" class="js-vendo-form js-hardware-form" data-platform="<?= e($platform) ?>">
                                                    <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($deviceId) ?>"><input type="hidden" name="action" value="save_config">
                                                    <p class="small text-body-secondary">Choose separate GPIO pins for the coin input and relay output. The built-in LED is reserved for device status.</p>
                                                    <div class="row g-3">
                                                        <div class="col-md-6"><label class="form-label">Coin input</label><?= $pinSelect('coin_pin',(int)($pins['coin']??$defaults['coin']),$profile['coin'],'js-pin-select') ?></div>
                                                        <div class="col-md-6"><label class="form-label">Relay output</label><?= $pinSelect('relay_pin',(int)($pins['relay']??$defaults['relay']),$profile['output'],'js-pin-select') ?></div>
                                                        <div class="col-md-6"><label class="form-label">Coin settle time</label><div class="input-group"><input class="form-control" type="number" min="50" max="2000" name="coin_settle_ms" value="<?= (int)($coin['settle_ms']??350) ?>" required><span class="input-group-text">ms</span></div><div class="form-text">Accepted range: 50–2000 ms.</div></div>
                                                        <div class="col-12"><button class="btn btn-primary">Save hardware configuration</button></div>
                                                    </div>
                                                </form>
                                            <?php else: ?><div class="alert alert-warning mb-0">Hardware configuration becomes available after the device reports whether it is ESP32 or ESP8266.</div><?php endif; ?>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="card">
                                        <div class="card-header d-flex flex-column flex-md-row justify-content-between gap-2">
                                            <div><strong>Firmware</strong><div class="small text-body-secondary">Check releases, queue an update, or set the device's automatic update policy.</div></div>
                                            <div class="small text-md-end">
                                                <div><span class="text-body-secondary">Installed</span> <strong><?= $currentFirmware==='—'?'Unknown':'v'.e(ltrim($currentFirmware,'vV')) ?></strong></div>
                                                <div><span class="text-body-secondary">Latest</span> <strong data-firmware-latest><?= $latestFirmware!==''?'v'.e(ltrim($latestFirmware,'vV')):'Unknown' ?></strong></div>
                                                <div class="text-body-secondary"><span data-firmware-target><?= ($firmware['target']??null)?'Target: '.e(strtoupper((string)$firmware['target'])):'Target unknown' ?></span><span data-firmware-checked><?= !empty($firmware['checked_at'])?' · Checked '.e((string)$firmware['checked_at']):'' ?></span></div>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
                                                <span data-firmware-state><?php if($updateAvailable===true):?><span class="badge text-bg-warning">Update available</span><?php elseif($updateAvailable===false):?><span class="badge text-bg-success">Up to date</span><?php else:?><span class="badge text-bg-secondary">Release status unknown</span><?php endif;?></span>
                                                <form method="post" class="d-flex flex-wrap gap-2 js-vendo-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($deviceId) ?>"><button class="btn btn-sm btn-outline-primary" name="action" value="firmware_check">Check now</button><button class="btn btn-sm btn-primary" data-firmware-update <?= $updateAvailable===true?'':'disabled' ?> name="action" value="firmware_update" onclick="return confirm('Queue firmware update for this Vendo? The device will wait until it is safe to update.')"><?= $updateAvailable===true?'Update to v'.e(ltrim($latestFirmware,'vV')):'Update firmware' ?></button></form>
                                            </div>
                                            <p class="small text-body-secondary" data-firmware-error><?= e($firmware['error']??'') ?></p>
                                            <form method="post" class="row g-3 align-items-end js-vendo-form">
                                                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($deviceId) ?>"><input type="hidden" name="action" value="save_firmware">
                                                <div class="col-sm-6 col-xl-3"><div class="form-check form-switch"><input class="form-check-input js-firmware-auto-check" type="checkbox" role="switch" name="auto_check" id="fw-check-<?= $i ?>" <?= ($fwConfig['auto_check']??true)?'checked':'' ?>><label class="form-check-label" for="fw-check-<?= $i ?>">Automatic checks</label></div><div class="form-text">Let the ESP check for releases.</div></div>
                                                <div class="col-sm-6 col-xl-3"><label class="form-label">Check interval</label><select class="form-select" name="check_interval_hours"><?php foreach([1,2,3,4,6,8,12,24] as $hours):?><option value="<?= $hours ?>" <?= (int)($fwConfig['check_interval_hours']??2)===$hours?'selected':'' ?>><?= $hours ?> hour<?= $hours===1?'':'s' ?></option><?php endforeach;?></select></div>
                                                <div class="col-sm-6 col-xl-3"><div class="form-check form-switch"><input class="form-check-input js-firmware-auto-update" type="checkbox" role="switch" name="auto_update" id="fw-update-<?= $i ?>" <?= ($fwConfig['auto_update']??false)?'checked':'' ?>><label class="form-check-label" for="fw-update-<?= $i ?>">Automatic update</label></div><div class="form-text">Installs only while the ESP is idle.</div></div>
                                                <div class="col-sm-6 col-xl-2"><label class="form-label">Channel</label><select class="form-select" name="channel"><option value="stable" selected>Stable</option></select></div>
                                                <div class="col-xl-1"><button class="btn btn-outline-primary w-100">Save</button></div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<script>
(()=>{
const box=document.getElementById('vendo-ajax-message');
const esc=s=>$('<div>').text(s??'').html();
const message=(text,ok=true)=>{box.innerHTML=`<div class="alert ${ok?'alert-success':'alert-danger'}">${esc(text)}</div>`;box.scrollIntoView({behavior:'smooth',block:'nearest'});};
const setBusy=(button,busy)=>{if(!button)return;if(busy){button.dataset.oldText=button.innerHTML;button.dataset.oldDisabled=button.disabled?'1':'0';button.disabled=true;button.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Working...';}else{button.disabled=button.dataset.oldDisabled==='1';if(button.dataset.oldText)button.innerHTML=button.dataset.oldText;}};
const syncPinChoices=form=>{const selects=[...form.querySelectorAll('.js-pin-select')],chosen=selects.map(s=>s.value).filter(Boolean);selects.forEach(select=>[...select.options].forEach(option=>{if(!option.value)return;const used=option.value!==select.value&&chosen.includes(option.value);option.disabled=used;option.hidden=used;}));};
document.querySelectorAll('.js-hardware-form').forEach(form=>{syncPinChoices(form);form.querySelectorAll('.js-pin-select').forEach(select=>select.addEventListener('change',()=>syncPinChoices(form)));});
document.querySelectorAll('.js-firmware-auto-check').forEach(check=>{const update=check.form?.querySelector('.js-firmware-auto-update');if(!update)return;const sync=()=>{if(!check.checked)update.checked=false;update.disabled=!check.checked;};sync();check.addEventListener('change',sync);});
document.querySelectorAll('[data-vendo-manage]').forEach(panel=>{const button=document.querySelector(`[data-bs-target="#${CSS.escape(panel.id)}"]`);panel.addEventListener('shown.bs.collapse',()=>{if(button){button.textContent='Close';button.setAttribute('aria-expanded','true');}});panel.addEventListener('hidden.bs.collapse',()=>{if(button){button.textContent='Manage';button.setAttribute('aria-expanded','false');}});});
const firmwareBadge=state=>state===true?'<span class="badge text-bg-warning">Update available</span>':state===false?'<span class="badge text-bg-success">Up to date</span>':'<span class="badge text-bg-secondary">Release status unknown</span>';
const updateUi=(form,data)=>{
    const id=form.querySelector('[name=device_id]')?.value,manage=document.querySelector(`[data-vendo-manage="${CSS.escape(id||'')}"]`),row=document.querySelector(`[data-vendo-row="${CSS.escape(id||'')}"]`);
    if(!manage||!row)return;
    if(data.firmware){
        const fw=data.firmware,available=fw.update_available===true,latest=fw.latest_version?'v'+String(fw.latest_version).replace(/^v/i,''):'Unknown',button=manage.querySelector('[data-firmware-update]'),summary=row.querySelector('[data-firmware-summary]');
        if(summary)summary.textContent=fw.current_version?'v'+String(fw.current_version).replace(/^v/i,''):'Unknown';
        manage.querySelector('[data-firmware-latest]').textContent=latest;manage.querySelector('[data-firmware-state]').innerHTML=firmwareBadge(fw.update_available);manage.querySelector('[data-firmware-error]').textContent=fw.error||'';
        const target=manage.querySelector('[data-firmware-target]'),checked=manage.querySelector('[data-firmware-checked]');if(target)target.textContent=fw.target?'Target: '+String(fw.target).toUpperCase():'Target unknown';if(checked)checked.textContent=fw.checked_at?' · Checked '+fw.checked_at:'';button.disabled=!available;button.textContent=available?'Update to '+latest:'Update firmware';
    }
    if(data.action==='rename'&&data.vendo_name){row.querySelector('[data-vendo-name]').textContent=data.vendo_name;form.querySelector('[name=vendo_name]').value=data.vendo_name;}
    if(data.action==='coin_enable'){manage.querySelector('[data-coin-state]').textContent='enable queued';row.querySelector('[data-coin-summary]').textContent='Enable queued';}
    if(data.action==='coin_disable'){manage.querySelector('[data-coin-state]').textContent='disable queued';row.querySelector('[data-coin-summary]').textContent='Disable queued';}
    if(['activate','suspend','revoke'].includes(data.action)){const state=data.action==='activate'?'active':data.action==='suspend'?'suspended':'revoked',cls=state==='active'?'success':state==='suspended'?'warning':'danger';row.querySelector('[data-device-state]').innerHTML=`<span class="badge text-bg-${cls}">${state.charAt(0).toUpperCase()+state.slice(1)}</span>`;const b=manage.querySelector('[data-state-action]');if(b&&data.action!=='revoke'){if(state==='active'){b.value='suspend';b.textContent='Suspend';b.className='btn btn-sm btn-outline-warning';}else{b.value='activate';b.textContent='Activate';b.className='btn btn-sm btn-outline-success';}}}
};
document.querySelectorAll('.js-vendo-form').forEach(form=>form.addEventListener('submit',async e=>{e.preventDefault();const button=e.submitter||form.querySelector('button[type=submit],button:not([type])'),fd=new FormData(form);if(button?.name&&!fd.has(button.name))fd.append(button.name,button.value);setBusy(button,true);try{const r=await fetch(location.pathname+location.search,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});const data=await r.json().catch(()=>({ok:false,message:'Invalid server response.'}));message(data.message||'Action completed.',!!data.ok);if(data.ok)updateUi(form,data);}catch(err){message('Could not contact PixiePoint. Please try again.',false);}finally{setBusy(button,false);}}));
})();
</script>
