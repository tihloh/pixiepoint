<?php
/** @var array $router */
/** @var array<string,array{label:string,value:mixed}> $metrics */
/** @var array $recentSessions */
/** @var array $themes */
/** @var array $portalFeatures */
/** @var bool $canManageRouter */
/** @var bool $canManageTeam */
/** @var string $message */
/** @var string $csrf */
$featureLabels=['voucher_login'=>'Voucher login','member_login'=>'Member login','qr_scan'=>'QR code / scanner','trial'=>'Trial / free time','points'=>'Points system','points_convert'=>'Convert points','points_play'=>'Play with points','points_share'=>'Share points'];
$featureOn=static fn(string $key):bool=>(bool)($portalFeatures[$key]['value']??($key==='voucher_login'));
?>

<div class="heading">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1"><span class="badge">Router</span><span class="text-body-secondary small">#<?= e($router['id']) ?></span></div>
        <h1><?= e($router['name']) ?></h1>
        <p class="muted mb-0"><?= e($router['identity']) ?><?php if(!empty($router['location'])): ?> · <?= e($router['location']) ?><?php endif;?></p>
    </div>
    <div class="actions">
        <a class="btn btn-outline-secondary" href="/admin/portal-themes">Portal themes</a>
        <?php if($canManageTeam):?><a class="btn btn-outline-secondary" href="/admin/routers/<?= e($router['id']) ?>/team">Team</a><?php endif;?>
        <?php if($canManageRouter):?><button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#routerSettingsModal">Router settings</button><button class="btn btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#portalFeaturesModal">Portal features</button><?php endif;?>
    </div>
</div>

<?= $message ?>

<section class="grid" aria-label="Router summary">
    <?php foreach($metrics as $metric):?><div class="metric"><small><?= e($metric['label']) ?></small><strong><?= $metric['label']==='Sales today'?'₱'.e(number_format((float)$metric['value'],2)):e((int)$metric['value']) ?></strong></div><?php endforeach;?>
</section>

<section class="panel">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3"><div><h2 class="mb-1">Portal</h2><p class="muted mb-0">The router uses its selected theme and portal features for hotspot clients.</p></div><span class="badge text-bg-<?= $router['enabled']?'success':'secondary' ?>"><?= $router['enabled']?'Enabled':'Disabled' ?></span></div>
    <div class="row g-3">
        <div class="col-md-4"><small class="text-body-secondary d-block">Theme</small><strong><?php $themeName='System default';foreach($themes as $theme)if((int)($router['portal_theme_id']??0)===(int)$theme['id']){$themeName=(string)$theme['name'];break;}echo e($themeName); ?></strong></div>
        <div class="col-md-4"><small class="text-body-secondary d-block">Public host</small><strong><?= e($router['public_host']?:'—') ?></strong></div>
        <div class="col-md-4"><small class="text-body-secondary d-block">Last seen</small><strong><?= e($router['last_seen_at']?:'Never') ?></strong></div>
    </div>
    <div class="d-flex flex-wrap gap-2 mt-4"><?php $shown=0;foreach($featureLabels as $key=>$label):if(!$featureOn($key))continue;$shown++;?><span class="badge text-bg-success"><?= e($label) ?></span><?php endforeach;?><?php if(!$shown):?><span class="text-body-secondary small">No optional portal features enabled.</span><?php endif;?></div>
</section>

<?php if($recentSessions):?><section class="panel"><h2>Recent sessions</h2><table><thead><tr><th>Access</th><th>Device</th><th>Status</th><th>Updated</th></tr></thead><tbody><?php foreach($recentSessions as $session):?><tr><td><?= e($session['username']?:'—') ?></td><td class="code"><?= e($session['mac']?:'—') ?></td><td><span class="badge <?= $session['status']==='active'?'':'off' ?>"><?= e($session['status']) ?></span></td><td><?= e($session['updated_at']) ?></td></tr><?php endforeach;?></tbody></table></section><?php endif;?>

<?php if($canManageRouter):?>
<div class="modal fade" id="routerSettingsModal" tabindex="-1"><div class="modal-dialog modal-lg"><div class="modal-content"><form method="post" action="/admin/routers/<?= e($router['id']) ?>/settings"><div class="modal-header"><h2 class="modal-title fs-5">Router settings</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="router_settings"><div class="row g-3"><div class="col-md-6"><label>Router / Wi-Fi name</label><input name="name" value="<?= e($router['name']) ?>" required maxlength="160"></div><div class="col-md-6"><label>RouterOS identity</label><input value="<?= e($router['identity']) ?>" readonly></div><div class="col-md-6"><label>Public hostname / VPN IP</label><input name="public_host" value="<?= e($router['public_host']??'') ?>" maxlength="255"></div><div class="col-md-6"><label>Location</label><input name="location" value="<?= e($router['location']??'') ?>" maxlength="255"></div><div class="col-md-6"><label>Portal theme</label><select name="portal_theme_id"><option value="0">System default</option><?php foreach($themes as $theme):?><option value="<?= e($theme['id']) ?>" <?= (int)($router['portal_theme_id']??0)===(int)$theme['id']?'selected':'' ?>><?= e($theme['name']) ?></option><?php endforeach;?></select></div><div class="col-12"><label class="d-inline-flex align-items-center gap-2"><input class="form-check-input m-0" style="width:1rem;height:1rem" type="checkbox" name="enabled" value="1" <?= $router['enabled']?'checked':'' ?>> Enabled</label></div></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="button" type="submit">Save settings</button></div></form></div></div></div>

<div class="modal fade" id="portalFeaturesModal" tabindex="-1"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><form method="post" action="/admin/routers/<?= e($router['id']) ?>/settings"><div class="modal-header"><div><h2 class="modal-title fs-5">Portal features</h2><div class="small text-body-secondary">Features available on this router's hotspot portal.</div></div><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div><div class="modal-body"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="portal_features"><div class="row g-3"><?php foreach($featureLabels as $key=>$label):?><div class="col-md-4"><label class="d-inline-flex align-items-center gap-2"><input class="form-check-input m-0" style="width:1rem;height:1rem" type="checkbox" name="<?= e($key) ?>" value="1" <?= $featureOn($key)?'checked':'' ?>> <?= e($label) ?></label></div><?php endforeach;?><div class="col-md-4"><label>Trial minutes</label><input type="number" name="trial_minutes" min="1" max="1440" value="<?= e($portalFeatures['trial']['config']['minutes']??10) ?>"></div><div class="col-md-4"><label>Convert points</label><input type="number" name="convert_points" min="1" value="<?= e($portalFeatures['points_convert']['config']['points']??10) ?>"></div><div class="col-md-4"><label>Convert to minutes</label><input type="number" name="convert_minutes" min="1" value="<?= e($portalFeatures['points_convert']['config']['minutes']??5) ?>"></div></div></div><div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="button" type="submit">Save features</button></div></form></div></div></div>
<?php endif;?>
