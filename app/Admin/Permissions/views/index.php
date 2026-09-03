<?php
/** @var array $user */ /** @var array $groups */ /** @var array $groupIds */
/** @var array $definitions */ /** @var array $resolved */ /** @var array $overrides */
/** @var bool $canEditUser */ /** @var bool $canPermissions */ /** @var bool $canGroups */
/** @var bool $isPlatformOwnerActor */ /** @var string $message */ /** @var string $csrf */
$isOwner = ($user['platform_role'] ?? '') === 'platform_owner';
?>

<div class="heading"><div><h1>Manage user</h1></div><a class="btn btn-outline-secondary" href="/admin/users">Back</a></div>
<?= $message ?>

<form method="post" id="userForm">
<input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="save">
<div class="row g-4 align-items-start">
    <div class="col-lg-4">
        <section class="panel">
            <div class="text-center mb-4">
                <?php if (!empty($user['avatar_url'])): ?><img src="<?= e($user['avatar_url']) ?>" alt="" class="rounded-circle object-fit-cover border mb-3" width="112" height="112"><?php else: ?><div class="rounded-circle border d-inline-flex align-items-center justify-content-center fs-1 fw-bold mb-3" style="width:112px;height:112px"><?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?></div><?php endif; ?>
                <h2 class="h4 mb-1"><?= e($user['name']) ?></h2><div class="text-body-secondary small"><?= e($user['email']) ?></div>
                <?php if ($canEditUser): ?><button class="btn btn-sm btn-outline-secondary mt-3" type="button" data-bs-toggle="modal" data-bs-target="#avatarModal">Change photo</button><?php endif; ?>
            </div>
            <div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" value="<?= e($user['name']) ?>" <?= $canEditUser ? '' : 'readonly' ?>></div>
            <div class="mb-3"><label class="form-label">Email</label><input class="form-control" type="email" name="email" value="<?= e($user['email']) ?>" <?= $canEditUser ? '' : 'readonly' ?>></div>
            <div class="mb-3"><label class="form-label">Role</label>
                <?php if ($isPlatformOwnerActor && !$isOwner): ?><select class="form-select" name="platform_role"><option value="member" <?= ($user['platform_role'] ?? '') === 'member' ? 'selected' : '' ?>>Member</option><option value="pisowifi_owner" <?= ($user['platform_role'] ?? '') === 'pisowifi_owner' ? 'selected' : '' ?>>PisoWiFi Owner</option></select>
                <?php else: ?><input class="form-control" value="<?= e(ucwords(str_replace('_', ' ', (string) $user['platform_role']))) ?>" readonly><?php endif; ?>
            </div>
            <?php if ($canEditUser && $thisCanManage = true): ?><div class="form-check form-switch mb-4"><input class="form-check-input" type="checkbox" name="active" id="active" <?= !empty($user['active']) ? 'checked' : '' ?>><label class="form-check-label" for="active">Active account</label></div><?php endif; ?>
            <?php if ($groups): ?><div class="mb-2"><label class="form-label">Groups</label><div class="d-flex flex-wrap gap-2"><?php foreach ($groups as $group): ?><label class="btn btn-sm btn-outline-secondary"><input class="form-check-input me-1" type="checkbox" name="groups[]" value="<?= e($group->id) ?>" <?= in_array((int) $group->id, $groupIds, true) ? 'checked' : '' ?> <?= $canGroups ? '' : 'disabled' ?>><?= e($group->name) ?></label><?php endforeach; ?></div></div><?php endif; ?>
        </section>
    </div>

    <div class="col-lg-8">
        <section class="panel">
            <div class="d-flex justify-content-between align-items-center mb-3"><h2 class="h5 mb-0">Permissions</h2><?php if ($isOwner): ?><span class="badge">Full access</span><?php endif; ?></div>
            <div class="table-responsive"><table class="table align-middle mb-0"><thead><tr><th>Permission</th><th>Effective</th><th>Override</th></tr></thead><tbody>
            <?php foreach ($definitions as $permission => $definition): $result = $resolved[$permission] ?? null; $override = array_key_exists($permission, $overrides) ? ($overrides[$permission] ? 'allow' : 'deny') : 'inherit'; ?>
                <tr><td><strong><?= e($definition['name'] ?? $permission) ?></strong><div class="small text-body-secondary"><?= e($permission) ?></div></td><td><span class="badge <?= ($isOwner || ($result?->allowed ?? false)) ? '' : 'off' ?>"><?= ($isOwner || ($result?->allowed ?? false)) ? 'Allowed' : 'Denied' ?></span></td><td><?php if ($isOwner): ?><span class="text-body-secondary">Platform owner</span><?php elseif ($canPermissions): ?><select class="form-select form-select-sm" name="permissions[<?= e($permission) ?>]"><option value="inherit" <?= $override === 'inherit' ? 'selected' : '' ?>>Inherit</option><option value="allow" <?= $override === 'allow' ? 'selected' : '' ?>>Allow</option><option value="deny" <?= $override === 'deny' ? 'selected' : '' ?>>Deny</option></select><?php else: ?><span class="text-body-secondary"><?= e($result?->source ?? 'default') ?></span><?php endif; ?></td></tr>
            <?php endforeach; ?>
            </tbody></table></div>
        </section>
    </div>
</div>
<?php if ($canEditUser || $canPermissions || $canGroups): ?><div class="d-flex justify-content-end mt-4"><button class="button px-4" type="submit">Save changes</button></div><?php endif; ?>
</form>

<?php if ($canEditUser): ?>
<div class="modal fade" id="avatarModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-dialog-centered"><div class="modal-content"><form method="post" id="avatarForm">
<div class="modal-header"><h2 class="modal-title fs-5">Profile picture</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
<div class="modal-body"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="avatar"><input type="hidden" name="avatar_data" id="avatarData"><input class="form-control mb-3" id="avatarFile" type="file" accept="image/jpeg,image/png,image/webp"><div class="mx-auto overflow-hidden rounded-circle border position-relative" style="width:280px;height:280px;touch-action:none;background:#07111f"><canvas id="avatarCanvas" width="512" height="512" style="width:280px;height:280px;cursor:grab"></canvas></div><label class="form-label mt-3" for="avatarZoom">Zoom</label><input class="form-range" id="avatarZoom" type="range" min="1" max="3" step="0.01" value="1"></div>
<div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="button" type="submit">Upload photo</button></div>
</form></div></div></div>
<script>
(() => {
    const file = document.getElementById('avatarFile'), canvas = document.getElementById('avatarCanvas'), ctx = canvas.getContext('2d'), zoom = document.getElementById('avatarZoom'), form = document.getElementById('avatarForm');
    const img = new Image(); let scale = 1, x = 0, y = 0, dragging = false, px = 0, py = 0;
    function draw() { ctx.clearRect(0,0,512,512); if (!img.width) return; const base = Math.max(512/img.width,512/img.height), s = base*scale, w=img.width*s,h=img.height*s; x=Math.min(0,Math.max(512-w,x)); y=Math.min(0,Math.max(512-h,y)); ctx.drawImage(img,x,y,w,h); }
    file.addEventListener('change', () => { const f=file.files[0]; if(!f)return; const r=new FileReader(); r.onload=()=>{img.onload=()=>{scale=1;zoom.value=1;const b=Math.max(512/img.width,512/img.height);x=(512-img.width*b)/2;y=(512-img.height*b)/2;draw();};img.src=r.result;};r.readAsDataURL(f); });
    zoom.addEventListener('input',()=>{const old=scale;scale=+zoom.value;const ratio=scale/old;x=256-(256-x)*ratio;y=256-(256-y)*ratio;draw();});
    canvas.addEventListener('pointerdown',e=>{dragging=true;px=e.clientX;py=e.clientY;canvas.setPointerCapture(e.pointerId);}); canvas.addEventListener('pointermove',e=>{if(!dragging)return;x+=(e.clientX-px)*(512/280);y+=(e.clientY-py)*(512/280);px=e.clientX;py=e.clientY;draw();}); canvas.addEventListener('pointerup',()=>dragging=false);
    form.addEventListener('submit',e=>{if(!img.width){e.preventDefault();return;} document.getElementById('avatarData').value=canvas.toDataURL('image/webp',0.86);});
})();
</script>
<?php endif; ?>
