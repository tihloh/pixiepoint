<?php
/** @var array $router */
/** @var string $message */
/** @var string $csrf */
?>

<div class="heading">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge">Router Settings</span>
            <span class="text-body-secondary small">#<?= e($router['id']) ?></span>
        </div>
        <h1><?= e($router['name']) ?></h1>
        <p class="muted mb-0">
            Configure this MikroTik router. RouterOS identity is managed by the router itself.
        </p>
    </div>
    <div class="actions">
        <a class="btn btn-outline-secondary" href="/admin/routers/<?= e($router['id']) ?>">Back to router</a>
        <a class="btn btn-outline-secondary" href="/admin/routers/<?= e($router['id']) ?>/team">Team</a>
    </div>
</div>

<?= $message ?>

<section class="panel">
    <form method="post">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

        <div class="form-grid">
            <div class="field">
                <label for="router-name">Display name</label>
                <input
                    id="router-name"
                    name="name"
                    value="<?= e($router['name']) ?>"
                    required
                    maxlength="160"
                >
            </div>

            <div class="field">
                <label for="router-identity">RouterOS identity</label>
                <input
                    id="router-identity"
                    value="<?= e($router['identity']) ?>"
                    readonly
                >
            </div>

            <div class="field">
                <label for="router-host">Public hostname / VPN IP</label>
                <input
                    id="router-host"
                    name="public_host"
                    value="<?= e($router['public_host'] ?? '') ?>"
                    placeholder="router.example.com or 10.10.0.2"
                    maxlength="255"
                >
            </div>

            <div class="field">
                <label for="router-location">Location</label>
                <input
                    id="router-location"
                    name="location"
                    value="<?= e($router['location'] ?? '') ?>"
                    placeholder="Branch, site or area"
                    maxlength="255"
                >
            </div>
        </div>

        <div class="form-check mt-3">
            <input
                class="form-check-input"
                type="checkbox"
                name="enabled"
                value="1"
                id="router-enabled"
                <?= $router['enabled'] ? 'checked' : '' ?>
            >
            <label class="form-check-label" for="router-enabled">Enabled</label>
        </div>

        <div class="d-flex justify-content-end mt-4">
            <button class="button" type="submit">Save changes</button>
        </div>
    </form>
</section>
