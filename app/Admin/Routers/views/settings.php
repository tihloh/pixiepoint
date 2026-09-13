<?php
/** @var array $router */
/** @var array<int,array<string,mixed>> $themes */
/** @var array $portalFeatures */
/** @var string $message */
/** @var string $csrf */
$featureLabels=['voucher_login'=>'Voucher login','member_login'=>'Member login','qr_scan'=>'QR code / scanner','trial'=>'Trial / free time','points'=>'Points system','points_convert'=>'Convert points','points_play'=>'Play with points','points_share'=>'Share points'];
$featureOn=static fn(string $key):bool=>(bool)($portalFeatures[$key]['value']??($key==='voucher_login'));
?>

<div class="heading mb-4">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1"><span class="badge">Router Settings</span><span class="text-body-secondary small">#<?= e($router['id']) ?></span></div>
        <h1 class="mb-2"><?= e($router['name']) ?></h1>
        <p class="muted mb-0">Configure this MikroTik router and its hotspot portal.</p>
    </div>
    <div class="actions"><a class="btn btn-outline-secondary" href="/admin/routers/<?= e($router['id']) ?>">Back to router</a><a class="btn btn-outline-secondary" href="/admin/routers/<?= e($router['id']) ?>/team">Team</a></div>
</div>

<?= $message ?>

<form method="post">
    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

    <section class="panel mb-4">
        <h2 class="mb-4">Router configuration</h2>
        <div class="row g-4">
            <div class="col-md-6 field"><label for="router-name">Router / Wi-Fi name</label><input id="router-name" name="name" value="<?= e($router['name']) ?>" required maxlength="160"></div>
            <div class="col-md-6 field"><label for="router-identity">RouterOS identity</label><input id="router-identity" value="<?= e($router['identity']) ?>" readonly></div>
            <div class="col-md-6 field"><label for="router-host">Public hostname / VPN IP</label><input id="router-host" name="public_host" value="<?= e($router['public_host'] ?? '') ?>" placeholder="router.example.com or 10.10.0.2" maxlength="255"></div>
            <div class="col-md-6 field"><label for="router-location">Location</label><input id="router-location" name="location" value="<?= e($router['location'] ?? '') ?>" placeholder="Branch, site or area" maxlength="255"></div>
            <div class="col-md-6 field"><label for="router-theme">Portal theme</label><select id="router-theme" name="portal_theme_id"><option value="0">System default</option><?php foreach ($themes as $theme): ?><option value="<?= e($theme['id']) ?>" <?= (int)($router['portal_theme_id']??0)===(int)$theme['id']?'selected':'' ?>><?= e($theme['name']) ?></option><?php endforeach; ?></select></div>
        </div>
        <div class="form-check form-switch mt-4"><input class="form-check-input flex-shrink-0" style="width:2.5em;height:1.25em" type="checkbox" name="enabled" value="1" id="router-enabled" <?= $router['enabled']?'checked':'' ?>><label class="form-check-label ms-2" for="router-enabled">Enabled</label></div>
    </section>

    <section class="panel">
        <h2 class="mb-2">Portal features</h2>
        <p class="muted mb-4">Choose the features available on this router's hotspot portal.</p>
        <div class="row gx-4 gy-3">
            <?php foreach($featureLabels as $key=>$label): ?><div class="col-md-4"><div class="form-check form-switch"><input class="form-check-input flex-shrink-0" style="width:2.5em;height:1.25em" type="checkbox" name="<?= e($key) ?>" value="1" id="router-<?= e($key) ?>" <?= $featureOn($key)?'checked':'' ?>><label class="form-check-label ms-2" for="router-<?= e($key) ?>"><?= e($label) ?></label></div></div><?php endforeach; ?>
            <div class="col-md-3 mt-4"><label>Trial minutes</label><input type="number" name="trial_minutes" min="1" max="1440" value="<?= e($portalFeatures['trial']['config']['minutes']??10) ?>"></div>
            <div class="col-md-3 mt-4"><label>Convert points</label><input type="number" name="convert_points" min="1" value="<?= e($portalFeatures['points_convert']['config']['points']??10) ?>"><small class="text-body-secondary mt-1 d-block">Points required</small></div>
            <div class="col-md-3 mt-4"><label>Convert to minutes</label><input type="number" name="convert_minutes" min="1" value="<?= e($portalFeatures['points_convert']['config']['minutes']??5) ?>"></div>
        </div>
    </section>

    <div class="d-flex justify-content-end mt-4"><button class="button" type="submit">Save changes</button></div>
</form>
