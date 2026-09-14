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
        'defaults'=>['coin'=>5,'relay'=>12,'status_led'=>16],
    ],
    'esp32'=>[
        'coin'=>[
            4=>'GPIO4 — General I/O',
            13=>'GPIO13 — HSPI MOSI / General I/O',
            14=>'GPIO14 — HSPI CLK / General I/O',
            16=>'GPIO16 — General I/O',
            17=>'GPIO17 — General I/O',
            18=>'GPIO18 — VSPI CLK / General I/O',
            19=>'GPIO19 — VSPI MISO / General I/O',
            21=>'GPIO21 — I²C SDA / General I/O',
            22=>'GPIO22 — I²C SCL / General I/O',
            23=>'GPIO23 — VSPI MOSI / General I/O',
            25=>'GPIO25 — DAC1 / General I/O',
            26=>'GPIO26 — DAC2 / General I/O',
            27=>'GPIO27 — General I/O',
            32=>'GPIO32 — ADC / General I/O',
            33=>'GPIO33 — ADC / General I/O',
        ],
        'output'=>[
            4=>'GPIO4 — General I/O',
            13=>'GPIO13 — HSPI MOSI / General I/O',
            14=>'GPIO14 — HSPI CLK / General I/O',
            16=>'GPIO16 — General I/O',
            17=>'GPIO17 — General I/O',
            18=>'GPIO18 — VSPI CLK / General I/O',
            19=>'GPIO19 — VSPI MISO / General I/O',
            21=>'GPIO21 — I²C SDA / General I/O',
            22=>'GPIO22 — I²C SCL / General I/O',
            23=>'GPIO23 — VSPI MOSI / General I/O',
            25=>'GPIO25 — DAC1 / General I/O',
            26=>'GPIO26 — DAC2 / General I/O',
            27=>'GPIO27 — General I/O',
            32=>'GPIO32 — ADC / General I/O',
            33=>'GPIO33 — ADC / General I/O',
        ],
        'defaults'=>['coin'=>27,'relay'=>26,'status_led'=>25],
    ],
];
$pinSelect=function(string $name,int $selected,array $options,string $class=''): string
{
    $html='<select name="'.e($name).'" class="form-select '.e($class).'" required>';
    foreach($options as $gpio=>$label)$html.='<option value="'.(int)$gpio.'"'.((int)$gpio===$selected?' selected':'').'>'.e($label).'</option>';
    return $html.'</select>';
};
?>
<div class="container-fluid py-3">
    <div id="vendo-ajax-message"><?= $flash ?? '' ?></div>
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2 mb-3">
        <div><h1 class="h4 mb-1">Manage Vendo</h1><div class="text-body-secondary small">Paired ESP8266/ESP32 devices for the selected MikroTik router.</div></div>
        <a class="btn btn-outline-secondary" href="/admin/routers/<?= (int)$routerId ?>#stations">Back to stations</a>
    </div>
    <?php if(!$gatewayConfigured): ?><div class="alert alert-warning">Vendo Gateway is not configured on this PixiePoint instance.</div><?php endif; ?>
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center"><span>Connected Vendos</span><span class="badge text-bg-secondary"><?= count($rows) ?></span></div>
        <div class="table-responsive"><table class="table table-hover align-middle mb-0"><thead><tr><th>Vendo</th><th>Station</th><th>Device</th><th>Firmware</th><th>Connection</th><th>State</th><th class="text-end">Actions</th></tr></thead><tbody>
        <?php if(!$rows): ?><tr><td colspan="7" class="text-center text-body-secondary py-4">No Vendo devices are paired with this router yet.</td></tr><?php endif; ?>
        <?php foreach($rows as $i=>$row):
            $device=$row['device'];$binding=$row['binding'];$config=$row['config']??[];$reported=$row['reported']??[];$pins=$config['hardware']['pins']??$reported['hardware']['pins']??[];$coin=$config['coin']??$reported['coin']??[];$selected=(bool)($row['selected']??false);$online=$device&&$device->lastSeenAt&&$device->lastSeenAt->getTimestamp()>=time()-180;$deviceId=(string)$binding['gateway_device_id'];
            $platform=strtolower((string)($reported['hardware']['platform']??''));if(!isset($gpioProfiles[$platform]))$platform='esp8266';$profile=$gpioProfiles[$platform];$defaults=$profile['defaults'];
        ?>
            <tr class="<?= $selected?'table-active':'' ?>" data-vendo-row="<?= e($deviceId) ?>">
                <td><strong data-vendo-name><?= e($binding['vendo_name']?:'Vendo') ?></strong><div class="small text-body-secondary"><code><?= e($deviceId) ?></code></div></td>
                <td><?= e($binding['station_name']?:'—') ?></td>
                <td><?= $device?e(trim(($device->hardwareModel??'').' '.($device->hardwareRevision??''))):'<span class="text-danger">Missing</span>' ?><div class="small text-body-secondary text-uppercase"><?= e($platform) ?></div></td>
                <td><?= $device?e($device->firmwareVersion??'—'):'—' ?></td>
                <td><span class="badge text-bg-<?= $online?'success':'secondary' ?>"><?= $online?'Online':'Offline' ?></span><?php if(!empty($reported['ip'])):?><div class="small text-body-secondary"><?= e($reported['ip']) ?></div><?php endif;?></td>
                <td><span data-device-state><?= $device?'<span class="badge text-bg-'.($device->state==='active'?'success':($device->state==='suspended'?'warning':'danger')).'">'.e($device->state).'</span>':'—' ?></span><?php if($device&&$device->lastSeenAt):?><div class="small text-body-secondary">Seen <?= e($device->lastSeenAt->format('Y-m-d H:i:s')) ?></div><?php endif;?></td>
                <td class="text-end"><button class="btn btn-sm btn-primary" type="button" data-bs-toggle="collapse" data-bs-target="#vg-manage-<?= $i ?>">Manage</button></td>
            </tr>
            <tr class="collapse <?= $selected?'show':'' ?>" id="vg-manage-<?= $i ?>" data-vendo-manage="<?= e($deviceId) ?>"><td colspan="7" class="bg-body-tertiary">
                <div class="row g-3 py-2">
                    <div class="col-lg-5">
                        <div class="border rounded p-3 h-100">
                            <div class="fw-semibold mb-3">Device controls</div>
                            <form method="post" class="row g-2 mb-3 js-vendo-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($deviceId) ?>"><input type="hidden" name="action" value="rename"><div class="col-8"><input name="vendo_name" maxlength="160" value="<?= e($binding['vendo_name']?:'Vendo') ?>" required></div><div class="col-4"><button class="btn btn-outline-primary w-100">Rename</button></div></form>
                            <form method="post" class="d-flex flex-wrap gap-2 js-vendo-form"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($deviceId) ?>"><button class="btn btn-sm btn-outline-success" name="action" value="coin_enable">Coin on</button><button class="btn btn-sm btn-outline-secondary" name="action" value="coin_disable">Coin off</button><button class="btn btn-sm btn-outline-primary" name="action" value="firmware_check">Check firmware</button><button class="btn btn-sm btn-outline-secondary" name="action" value="restart" onclick="return confirm('Restart this Vendo device?')">Restart</button><?php if($device&&$device->state==='active'):?><button class="btn btn-sm btn-outline-warning" name="action" value="suspend" data-state-action>Suspend</button><?php else:?><button class="btn btn-sm btn-outline-success" name="action" value="activate" data-state-action>Activate</button><?php endif;?><button class="btn btn-sm btn-outline-danger" name="action" value="revoke" onclick="return confirm('Revoke this device? It will require administrative recovery before it can enroll again.')">Revoke</button></form>
                            <div class="small text-body-secondary mt-3">Reported state: <?= e($reported['state']??'Unknown') ?> · RSSI: <?= isset($reported['rssi'])?e((string)$reported['rssi']).' dBm':'—' ?> · Coin: <span data-coin-state><?= !empty($reported['coin_enabled'])?'enabled':'disabled' ?></span></div>
                        </div>
                    </div>
                    <div class="col-lg-7">
                        <form method="post" class="border rounded p-3 js-vendo-form js-hardware-form" data-platform="<?= e($platform) ?>"><input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>"><input type="hidden" name="device_id" value="<?= e($deviceId) ?>"><input type="hidden" name="action" value="save_config"><div class="d-flex justify-content-between align-items-center mb-1"><div class="fw-semibold">Hardware configuration</div><span class="badge text-bg-secondary text-uppercase"><?= e($platform) ?></span></div><div class="small text-body-secondary mb-3">Only safe pins supported by the detected ESP platform are shown. A pin selected for one purpose becomes unavailable in the other selectors.</div><div class="row g-2"><div class="col-12 col-md-4"><label>Coin input</label><?= $pinSelect('coin_pin',(int)($pins['coin']??$defaults['coin']),$profile['coin'],'js-pin-select') ?></div><div class="col-12 col-md-4"><label>Relay output</label><?= $pinSelect('relay_pin',(int)($pins['relay']??$defaults['relay']),$profile['output'],'js-pin-select') ?></div><div class="col-12 col-md-4"><label>External status LED</label><?= $pinSelect('status_led_pin',(int)($pins['status_led']??$defaults['status_led']),$profile['output'],'js-pin-select') ?></div><div class="col-12 col-md-4"><label>Coin settle time</label><div class="input-group"><input class="form-control" type="number" min="50" max="2000" name="coin_settle_ms" value="<?= (int)($coin['settle_ms']??350) ?>" required><span class="input-group-text">ms</span></div></div><div class="col-12"><button class="btn btn-primary">Save hardware configuration</button></div></div></form>
                    </div>
                </div>
            </td></tr>
        <?php endforeach; ?>
        </tbody></table></div>
    </div>
</div>
<script>
(()=>{
const box=document.getElementById('vendo-ajax-message');
const esc=s=>$('<div>').text(s??'').html();
const message=(text,ok=true)=>{box.innerHTML=`<div class="alert ${ok?'alert-success':'alert-danger'}">${esc(text)}</div>`;box.scrollIntoView({behavior:'smooth',block:'nearest'});};
const setBusy=(button,busy)=>{if(!button)return;if(busy){button.dataset.oldText=button.innerHTML;button.disabled=true;button.innerHTML='<span class="spinner-border spinner-border-sm me-1"></span>Working...';}else{button.disabled=false;if(button.dataset.oldText)button.innerHTML=button.dataset.oldText;}};
const syncPinChoices=form=>{const selects=[...form.querySelectorAll('.js-pin-select')],chosen=selects.map(s=>s.value).filter(Boolean);selects.forEach(select=>[...select.options].forEach(option=>{if(!option.value)return;const used=option.value!==select.value&&chosen.includes(option.value);option.disabled=used;option.hidden=used;}));};
document.querySelectorAll('.js-hardware-form').forEach(form=>{syncPinChoices(form);form.querySelectorAll('.js-pin-select').forEach(select=>select.addEventListener('change',()=>syncPinChoices(form)));});
const updateUi=(form,data)=>{const id=form.querySelector('[name=device_id]')?.value,manage=document.querySelector(`[data-vendo-manage="${CSS.escape(id||'')}"]`),row=document.querySelector(`[data-vendo-row="${CSS.escape(id||'')}"]`);if(!manage||!row)return;
if(data.action==='rename'&&data.vendo_name){row.querySelector('[data-vendo-name]').textContent=data.vendo_name;form.querySelector('[name=vendo_name]').value=data.vendo_name;}
if(data.action==='coin_enable')manage.querySelector('[data-coin-state]').textContent='enable queued';
if(data.action==='coin_disable')manage.querySelector('[data-coin-state]').textContent='disable queued';
if(['activate','suspend','revoke'].includes(data.action)){const state=data.action==='activate'?'active':data.action==='suspend'?'suspended':'revoked',cls=state==='active'?'success':state==='suspended'?'warning':'danger';row.querySelector('[data-device-state]').innerHTML=`<span class="badge text-bg-${cls}">${state}</span>`;const b=manage.querySelector('[data-state-action]');if(b&&data.action!=='revoke'){if(state==='active'){b.value='suspend';b.textContent='Suspend';b.className='btn btn-sm btn-outline-warning';}else{b.value='activate';b.textContent='Activate';b.className='btn btn-sm btn-outline-success';}}}
};
document.querySelectorAll('.js-vendo-form').forEach(form=>form.addEventListener('submit',async e=>{e.preventDefault();const button=e.submitter||form.querySelector('button[type=submit],button:not([type])'),fd=new FormData(form);if(button?.name&&!fd.has(button.name))fd.append(button.name,button.value);setBusy(button,true);try{const r=await fetch(location.pathname+location.search,{method:'POST',body:fd,headers:{'X-Requested-With':'XMLHttpRequest','Accept':'application/json'}});const data=await r.json().catch(()=>({ok:false,message:'Invalid server response.'}));message(data.message||'Action completed.',!!data.ok);if(data.ok)updateUi(form,data);}catch(err){message('Could not contact PixiePoint. Please try again.',false);}finally{setBusy(button,false);}}));
})();
</script>
