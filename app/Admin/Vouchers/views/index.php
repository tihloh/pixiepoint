<?php
/** @var string $message */
/** @var array $router */
/** @var array $vouchers */
/** @var array $stations */
/** @var array $batches */
/** @var array $platforms */
/** @var string $status */
/** @var string $csrf */
$stationOptions=static function(?int $selected=null) use($stations):void{?><option value="0">All stations on this router</option><?php foreach($stations as $station):?><option value="<?= e($station['id']) ?>"<?= $selected===(int)$station['id']?' selected':'' ?>><?= e($station['name']) ?></option><?php endforeach;};
?>
<div class="heading">
    <div><h1>Access vouchers</h1><p class="muted">Generate platform-neutral promos and router-ready voucher scripts.</p><div class="small text-body-secondary mt-2">Router: <span class="fw-semibold text-body"><?= e($router['name']) ?></span> <span class="code ms-1"><?= e($router['identity']) ?></span></div></div>
    <div class="d-flex flex-wrap gap-2"><button class="btn btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#batchModal">Generate batch</button><button class="button" type="button" data-bs-toggle="modal" data-bs-target="#voucherModal" data-mode="create">Create voucher</button></div>
</div>
<?= $message ?>
<section class="panel">
    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
        <h2 class="mb-0">Vouchers</h2>
        <div class="btn-group btn-group-sm"><a class="btn btn-outline-secondary<?= $status==='active'?' active':'' ?>" href="/admin/vouchers?status=active">Active</a><a class="btn btn-outline-secondary<?= $status==='archived'?' active':'' ?>" href="/admin/vouchers?status=archived">Archived</a><a class="btn btn-outline-secondary<?= $status==='all'?' active':'' ?>" href="/admin/vouchers?status=all">All</a></div>
    </div>
    <div class="table-responsive"><table class="table align-middle"><thead><tr><th>Code / Promo</th><th>Assignment</th><th>Allowance</th><th>Usage</th><th>Status</th><th class="text-end">Actions</th></tr></thead><tbody>
    <?php if(!$vouchers):?><tr><td colspan="6" class="empty">No vouchers in this view.</td></tr><?php endif;?>
    <?php foreach($vouchers as $voucher):?>
        <tr>
            <td><strong class="code"><?= e($voucher['code']) ?></strong><div class="small text-body-secondary"><?= e($voucher['promo_name']?:($voucher['label']?:'No promo')) ?><?= $voucher['batch_key']?' · Batch '.e($voucher['batch_key']):'' ?></div></td>
            <td><?= e($voucher['station_name']?:'All router stations') ?></td>
            <td><?= e($voucher['duration_minutes']) ?> min<?= $voucher['data_limit_mb']?' · '.e($voucher['data_limit_mb']).' MB':'' ?><div class="small text-body-secondary"><?= e($voucher['max_devices']) ?> device(s)</div></td>
            <td><?= e($voucher['uses'].' / '.$voucher['max_uses']) ?><div class="small text-body-secondary"><?= e($voucher['expires_at']?:'No expiry') ?></div></td>
            <td><?php if($voucher['archived_at']):?><span class="badge off">Archived</span><?php else:?><span class="badge <?= $voucher['enabled']?'':'off' ?>"><?= $voucher['enabled']?'Enabled':'Disabled' ?></span><?php endif;?></td>
            <td class="text-end">
                <?php if(!$voucher['archived_at']):?><button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#voucherModal" data-mode="edit" data-voucher="<?= e(json_encode($voucher,JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE)) ?>">Edit</button><?php endif;?>
                <form method="post" class="d-inline" onsubmit="return confirm('Unused vouchers are permanently deleted. Used vouchers are archived. Continue?')"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= e($voucher['id']) ?>"><button class="btn btn-sm btn-outline-danger" type="submit"><?= ((int)$voucher['uses']>0||$voucher['archived_at'])?'Archive':'Delete' ?></button></form>
            </td>
        </tr>
    <?php endforeach;?>
    </tbody></table></div>
</section>
<?php if($batches):?><section class="panel"><h2>Recent generated batches</h2><div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Batch</th><th>Promo</th><th>Platform</th><th>Quantity</th><th>Generated</th><th></th></tr></thead><tbody><?php foreach($batches as $batch):?><tr><td class="code"><?= e($batch['batch_key']) ?></td><td><?= e($batch['promo_name']?:'No promo') ?></td><td><?= e($platforms[$batch['platform']]??$batch['platform']) ?></td><td><?= e($batch['voucher_count']) ?></td><td><?= e($batch['created_at']) ?></td><td class="text-end"><a class="btn btn-sm btn-outline-primary" href="/admin/vouchers/export?batch=<?= rawurlencode($batch['batch_key']) ?>">Download script</a></td></tr><?php endforeach;?></tbody></table></div></section><?php endif;?>

<div class="modal fade" id="batchModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="post"><div class="modal-header"><h2 class="modal-title fs-5">Generate voucher batch</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="generate"><div class="row g-3">
    <div class="col-md-6"><label>Promo name</label><input name="promo_name" placeholder="Example: Weekend 3 Hours"></div>
    <div class="col-md-3"><label>Quantity</label><input name="quantity" type="number" min="1" max="1000" value="10" required></div>
    <div class="col-md-3"><label>Random characters</label><input name="code_length" type="number" min="4" max="32" value="8" required></div>
    <div class="col-md-6"><label>Code prefix</label><input name="prefix" maxlength="24" placeholder="Optional, e.g. WEEKEND-"><small class="text-body-secondary">Non-alphanumeric characters are removed.</small></div>
    <div class="col-md-6"><label>Hotspot Station</label><select name="station_id"><?php $stationOptions();?></select></div>
    <div class="col-md-6"><label>Output platform</label><select name="platform"><?php foreach($platforms as $key=>$label):?><option value="<?= e($key) ?>"><?= e($label) ?></option><?php endforeach;?></select></div>
    <div class="col-md-6"><label>RouterOS user profile</label><input name="platform_profile" placeholder="Optional existing profile"></div>
    <div class="col-md-4"><label>Duration (minutes)</label><input name="duration_minutes" type="number" min="1" value="60" required></div>
    <div class="col-md-4"><label>Data limit (MB)</label><input name="data_limit_mb" type="number" min="1" placeholder="Unlimited"></div>
    <div class="col-md-4"><label>Expires at</label><input name="expires_at" type="datetime-local"></div>
    <div class="col-md-6"><label>Maximum devices</label><input name="max_devices" type="number" min="1" value="1" required></div>
    <div class="col-md-6"><label>Maximum uses</label><input name="max_uses" type="number" min="1" value="1" required></div>
</div><div class="alert alert-info mt-3 mb-0">PixiePoint owns the promo and voucher definitions. The selected adapter produces the installation script for the target router platform.</div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="button" type="submit">Generate vouchers</button></div></form></div></div></div>

<div class="modal fade" id="voucherModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="post"><div class="modal-header"><h2 class="modal-title fs-5" id="voucherModalTitle">Create voucher</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="create"><input type="hidden" name="id" value="0"><div class="row g-3">
    <div class="col-md-6"><label>Code</label><input name="code" placeholder="Automatic if blank"></div><div class="col-md-6"><label>Label / promo</label><input name="label"></div>
    <div class="col-md-6"><label>Hotspot Station</label><select name="station_id"><?php $stationOptions();?></select></div><div class="col-md-3"><label>Duration (minutes)</label><input name="duration_minutes" type="number" min="1" value="60" required></div><div class="col-md-3"><label>Data limit (MB)</label><input name="data_limit_mb" type="number" min="1"></div>
    <div class="col-md-4"><label>Maximum devices</label><input name="max_devices" type="number" min="1" value="1" required></div><div class="col-md-4"><label>Maximum uses</label><input name="max_uses" type="number" min="1" value="1" required></div><div class="col-md-4"><label>Expires at</label><input name="expires_at" type="datetime-local"></div>
    <div class="col-12" id="voucherEnabledWrap" hidden><label class="d-inline-flex align-items-center gap-2"><input class="form-check-input m-0" type="checkbox" name="enabled" value="1"> Enabled</label></div>
</div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="button" type="submit" id="voucherSubmit">Create voucher</button></div></form></div></div></div>
<script>
document.getElementById('voucherModal').addEventListener('show.bs.modal',function(event){const button=event.relatedTarget,edit=button?.dataset.mode==='edit',form=this.querySelector('form');form.reset();let v={};if(edit){try{v=JSON.parse(button.dataset.voucher);}catch(e){v={};}}form.elements.action.value=edit?'update':'create';form.elements.id.value=edit?v.id:'0';for(const name of ['code','label','station_id','duration_minutes','data_limit_mb','max_devices','max_uses'])if(form.elements[name])form.elements[name].value=edit?(v[name]??''):(name==='duration_minutes'?'60':(['max_devices','max_uses'].includes(name)?'1':''));form.elements.expires_at.value=edit&&v.expires_at?String(v.expires_at).replace(' ','T').slice(0,16):'';form.elements.enabled.checked=edit&&String(v.enabled)==='1';form.elements.code.required=edit;document.getElementById('voucherEnabledWrap').hidden=!edit;document.getElementById('voucherModalTitle').textContent=edit?'Edit voucher':'Create voucher';document.getElementById('voucherSubmit').textContent=edit?'Save changes':'Create voucher';});
</script>
