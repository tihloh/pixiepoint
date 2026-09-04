<?php
/** @var array $user */
/** @var string $message */
/** @var string $csrf */

$isPlatformOwner = ($user['platform_role'] ?? '') === 'platform_owner';
?>

<div class="row justify-content-center">
    <div class="col-xl-8 col-lg-9">
        <div class="card border shadow overflow-hidden">
            <div class="card-header d-flex justify-content-between align-items-center px-4 py-3">
                <h1 class="h4 mb-0">Edit User</h1>
                <a class="btn btn-sm btn-outline-secondary" href="/admin/users">Back</a>
            </div>

            <div class="card-body p-4">
                <?= $message ?>

                <div class="d-flex align-items-center gap-3 mb-4">
                    <?php if (!empty($user['avatar_url'])): ?>
                        <img src="<?= e($user['avatar_url']) ?>" alt="" class="rounded-circle object-fit-cover border" width="72" height="72">
                    <?php else: ?>
                        <div class="rounded-circle border d-flex align-items-center justify-content-center fs-4 fw-bold" style="width:72px;height:72px">
                            <?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?>
                        </div>
                    <?php endif; ?>

                    <div>
                        <strong class="d-block mb-1"><?= e($user['name']) ?></strong>
                        <span class="text-body-secondary small"><?= e($user['email']) ?></span>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#avatarModal">Change photo</button>
                        </div>
                    </div>
                </div>

                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="save">

                    <div class="mb-3">
                        <label class="form-label" for="user-name">Name</label>
                        <input class="form-control" id="user-name" name="name" value="<?= e($user['name']) ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="user-email">Email</label>
                        <input class="form-control" id="user-email" name="email" type="email" value="<?= e($user['email']) ?>" required>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label" for="user-role">Role</label>
                            <?php if ($isPlatformOwner): ?>
                                <input class="form-control" value="Platform Owner" readonly>
                            <?php else: ?>
                                <select class="form-select" id="user-role" name="platform_role">
                                    <option value="member" <?= ($user['platform_role'] ?? '') === 'member' ? 'selected' : '' ?>>Member</option>
                                    <option value="pisowifi_owner" <?= ($user['platform_role'] ?? '') === 'pisowifi_owner' ? 'selected' : '' ?>>PisoWiFi Owner</option>
                                </select>
                            <?php endif; ?>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label" for="user-status">Status</label>
                            <select class="form-select" id="user-status" onchange="document.getElementById('activeField').checked=this.value==='1'">
                                <option value="1" <?= !empty($user['active']) ? 'selected' : '' ?>>Active</option>
                                <option value="0" <?= empty($user['active']) ? 'selected' : '' ?>>Disabled</option>
                            </select>
                            <input type="checkbox" class="d-none" id="activeField" name="active" value="1" <?= !empty($user['active']) ? 'checked' : '' ?>>
                        </div>
                    </div>
                </form>
            </div>

            <div class="card-footer d-flex justify-content-end gap-2 px-4 py-3">
                <a class="btn btn-outline-secondary" href="/admin/users">Cancel</a>
                <button class="button" type="submit" onclick="this.closest('.card').querySelector('.card-body form').requestSubmit()">Save Changes</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="avatarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="avatarForm">
                <div class="modal-header"><h2 class="modal-title fs-5">Profile Picture</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><input type="hidden" name="action" value="avatar"><input type="hidden" name="avatar_data" id="avatarData"><input class="d-none" id="avatarFile" type="file" accept="image/jpeg,image/png,image/webp">
                    <div class="text-center mb-3"><button class="btn btn-outline-secondary" id="avatarChoose" type="button">Choose photo</button></div>
                    <div class="mx-auto overflow-hidden rounded-circle border" style="width:280px;height:280px;touch-action:none;background:#07111f"><canvas id="avatarCanvas" width="512" height="512" style="width:280px;height:280px;cursor:grab"></canvas></div>
                    <label class="form-label mt-3" for="avatarZoom">Zoom</label><input class="form-range" id="avatarZoom" type="range" min="1" max="3" step="0.01" value="1">
                </div>
                <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="button" type="submit">Upload photo</button></div>
            </form>
        </div>
    </div>
</div>

<script>
(() => {
    const file = document.getElementById('avatarFile');
    const choose = document.getElementById('avatarChoose');
    const canvas = document.getElementById('avatarCanvas');
    const ctx = canvas.getContext('2d');
    const zoom = document.getElementById('avatarZoom');
    const form = document.getElementById('avatarForm');
    const image = new Image();
    let scale = 1, x = 0, y = 0, dragging = false, pointerX = 0, pointerY = 0;

    choose.addEventListener('click', () => file.click());
    function draw() {
        ctx.clearRect(0, 0, 512, 512);
        if (!image.width) return;
        const base = Math.max(512 / image.width, 512 / image.height);
        const renderedScale = base * scale;
        const width = image.width * renderedScale;
        const height = image.height * renderedScale;
        x = Math.min(0, Math.max(512 - width, x));
        y = Math.min(0, Math.max(512 - height, y));
        ctx.drawImage(image, x, y, width, height);
    }
    file.addEventListener('change', () => {
        const selected = file.files[0];
        if (!selected) return;
        choose.textContent = 'Choose another photo';
        const reader = new FileReader();
        reader.onload = () => {
            image.onload = () => {
                scale = 1; zoom.value = 1;
                const base = Math.max(512 / image.width, 512 / image.height);
                x = (512 - image.width * base) / 2; y = (512 - image.height * base) / 2; draw();
            };
            image.src = reader.result;
        };
        reader.readAsDataURL(selected);
    });
    zoom.addEventListener('input', () => {
        const old = scale; scale = Number(zoom.value); const ratio = scale / old;
        x = 256 - (256 - x) * ratio; y = 256 - (256 - y) * ratio; draw();
    });
    canvas.addEventListener('pointerdown', event => { dragging = true; pointerX = event.clientX; pointerY = event.clientY; canvas.setPointerCapture(event.pointerId); });
    canvas.addEventListener('pointermove', event => { if (!dragging) return; x += (event.clientX - pointerX) * (512 / 280); y += (event.clientY - pointerY) * (512 / 280); pointerX = event.clientX; pointerY = event.clientY; draw(); });
    canvas.addEventListener('pointerup', () => dragging = false);
    form.addEventListener('submit', event => { if (!image.width) { event.preventDefault(); return; } document.getElementById('avatarData').value = canvas.toDataURL('image/webp', 0.86); });
})();
</script>
