<?php
/** @var array $user */
/** @var string $businessName */
/** @var string $message */
/** @var string $csrf */
?>

<div class="row justify-content-center">
    <div class="col-xl-7 col-lg-8">
        <section class="panel p-0 overflow-hidden">
            <div class="px-4 py-3 border-bottom">
                <h1 class="h4 mb-0">My Profile</h1>
            </div>

            <div class="p-4">
                <?= $message ?>

                <div class="d-flex align-items-center gap-3 mb-4">
                    <?php if (!empty($user['avatar_url'])): ?>
                        <img
                            src="<?= e($user['avatar_url']) ?>"
                            alt=""
                            class="rounded-circle object-fit-cover border"
                            width="88"
                            height="88"
                        >
                    <?php else: ?>
                        <div
                            class="rounded-circle border d-flex align-items-center justify-content-center fs-3 fw-bold"
                            style="width:88px;height:88px"
                        >
                            <?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?>
                        </div>
                    <?php endif; ?>

                    <div>
                        <h2 class="h5 mb-1"><?= e($user['name']) ?></h2>
                        <div class="text-body-secondary small mb-2"><?= e($user['email']) ?></div>
                        <button
                            class="btn btn-sm btn-outline-secondary"
                            type="button"
                            data-bs-toggle="modal"
                            data-bs-target="#avatarModal"
                        >
                            Change photo
                        </button>
                    </div>
                </div>

                <form method="post">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="save">

                    <div class="mb-3">
                        <label class="form-label" for="profile-name">Name</label>
                        <input
                            class="form-control"
                            id="profile-name"
                            name="name"
                            value="<?= e($user['name']) ?>"
                            required
                        >
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="profile-email">Email</label>
                        <input
                            class="form-control"
                            id="profile-email"
                            name="email"
                            type="email"
                            value="<?= e($user['email']) ?>"
                            required
                        >
                    </div>

                    <div class="mb-4">
                        <label class="form-label" for="profile-business-name">Business / Wi-Fi name</label>
                        <input
                            class="form-control"
                            id="profile-business-name"
                            name="business_name_template"
                            value="<?= e($user['business_name_template'] ?? '') ?>"
                            maxlength="255"
                            placeholder="Example: Juan's Wi-Fi"
                        >
                        <div class="form-text">
                            Owner-level name. Router and Vendo names inherit this when their own template is blank.
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center gap-3 pt-3 border-top">
                        <div class="small text-body-secondary text-truncate">
                            Effective name: <strong><?= e($businessName) ?></strong>
                        </div>
                        <button class="button flex-shrink-0" type="submit">Save Profile</button>
                    </div>
                </form>
            </div>
        </section>
    </div>
</div>

<div class="modal fade" id="avatarModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" id="avatarForm">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Profile Picture</h2>
                    <button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="avatar">
                    <input type="hidden" name="avatar_data" id="avatarData">
                    <input class="d-none" id="avatarFile" type="file" accept="image/jpeg,image/png,image/webp">

                    <div class="text-center mb-3">
                        <button class="btn btn-outline-secondary" id="avatarChoose" type="button">Choose photo</button>
                    </div>

                    <div
                        class="mx-auto overflow-hidden rounded-circle border"
                        style="width:280px;height:280px;touch-action:none;background:#07111f"
                    >
                        <canvas
                            id="avatarCanvas"
                            width="512"
                            height="512"
                            style="width:280px;height:280px;cursor:grab"
                        ></canvas>
                    </div>

                    <label class="form-label mt-3" for="avatarZoom">Zoom</label>
                    <input class="form-range" id="avatarZoom" type="range" min="1" max="3" step="0.01" value="1">
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                    <button class="button" type="submit">Upload photo</button>
                </div>
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
    let scale = 1;
    let x = 0;
    let y = 0;
    let dragging = false;
    let pointerX = 0;
    let pointerY = 0;

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
                scale = 1;
                zoom.value = 1;
                const base = Math.max(512 / image.width, 512 / image.height);
                x = (512 - image.width * base) / 2;
                y = (512 - image.height * base) / 2;
                draw();
            };
            image.src = reader.result;
        };
        reader.readAsDataURL(selected);
    });

    zoom.addEventListener('input', () => {
        const old = scale;
        scale = Number(zoom.value);
        const ratio = scale / old;
        x = 256 - (256 - x) * ratio;
        y = 256 - (256 - y) * ratio;
        draw();
    });

    canvas.addEventListener('pointerdown', event => {
        dragging = true;
        pointerX = event.clientX;
        pointerY = event.clientY;
        canvas.setPointerCapture(event.pointerId);
    });

    canvas.addEventListener('pointermove', event => {
        if (!dragging) return;
        x += (event.clientX - pointerX) * (512 / 280);
        y += (event.clientY - pointerY) * (512 / 280);
        pointerX = event.clientX;
        pointerY = event.clientY;
        draw();
    });

    canvas.addEventListener('pointerup', () => dragging = false);

    form.addEventListener('submit', event => {
        if (!image.width) {
            event.preventDefault();
            return;
        }
        document.getElementById('avatarData').value = canvas.toDataURL('image/webp', 0.86);
    });
})();
</script>
