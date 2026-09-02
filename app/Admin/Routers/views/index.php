<?php
/** @var string $message */
/** @var array $routers */
/** @var bool $canManageRouters */
/** @var string $csrf */
?>
<div class="heading">
    <div>
        <h1>Routers</h1>
        <p class="muted">Register MikroTik routers so PixiePoint can recognize hotspot traffic and accounting events.</p>
    </div>
    <?php if ($canManageRouters): ?>
        <button class="button" type="button" data-bs-toggle="modal" data-bs-target="#routerModal" data-mode="create">Add router</button>
    <?php endif; ?>
</div>
<?= $message ?>

<section class="panel">
    <h2>Registered routers</h2>
    <p class="muted">Each row shows the router identity PixiePoint matches, where it is located, whether it is enabled, and when it last contacted the service.</p>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Router</th><th>Identity</th><th>Address</th><?php if ($canManageRouters): ?><th>Agent key</th><?php endif; ?><th>Status</th><th>Last seen</th><?php if ($canManageRouters): ?><th class="text-end">Action</th><?php endif; ?></tr></thead>
            <tbody>
            <?php if (!$routers): ?><tr><td colspan="<?= $canManageRouters ? 7 : 5 ?>" class="empty">No routers registered. Use Add router to register your first MikroTik.</td></tr><?php endif; ?>
            <?php foreach ($routers as $router):
                $setupUrl = 'https://hs.portalx.win/api/router/install?token=' . rawurlencode((string)$router['api_key']);
                $setupCommand = '/tool fetch url="' . $setupUrl . '" mode=https dst-path="PixiePointAgent.rsc"; /system scheduler remove [find name="pixiepoint-agent"]; /system script remove [find name="pixiepoint-agent"]; /system script add name="pixiepoint-agent" source=[/file get [find name="PixiePointAgent.rsc"] contents] policy=read,write,test; /system scheduler add name="pixiepoint-agent" interval=5s start-time=startup on-event="/system script run pixiepoint-agent" policy=read,write,test; /file remove [find name="PixiePointAgent.rsc"]';
            ?><tr>
                <td><strong><?= e($router['name']) ?></strong><div class="small text-body-secondary"><?= e($router['location'] ?: 'No location set') ?></div></td>
                <td class="code"><?= e($router['identity']) ?></td>
                <td><?= e($router['public_host'] ?: 'Not set') ?></td>
                <?php if ($canManageRouters): ?><td><code class="code"><?= e($router['api_key']) ?></code><div class="small text-body-secondary">Authenticates this router to the PixiePoint agent.</div></td><?php endif; ?>
                <td><span class="badge <?= $router['enabled'] ? '' : 'off' ?>"><?= $router['enabled'] ? 'Enabled' : 'Disabled' ?></span></td>
                <td><?= e($router['last_seen_at'] ?: 'Never') ?></td>
                <?php if ($canManageRouters): ?>
                <td class="text-end text-nowrap">
                    <button class="btn btn-sm btn-outline-primary" type="button" data-bs-toggle="modal" data-bs-target="#routerSetupModal" data-router="<?= e($router['name']) ?>" data-command="<?= e($setupCommand) ?>">Setup</button>
                    <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="modal" data-bs-target="#routerModal" data-mode="edit" data-id="<?= e($router['id']) ?>" data-name="<?= e($router['name']) ?>" data-identity="<?= e($router['identity']) ?>" data-host="<?= e($router['public_host'] ?? '') ?>" data-location="<?= e($router['location'] ?? '') ?>" data-enabled="<?= $router['enabled'] ? '1' : '0' ?>">Edit</button>
                </td>
                <?php endif; ?>
            </tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($canManageRouters): ?>
<div class="modal fade" id="routerSetupModal" tabindex="-1" aria-labelledby="routerSetupModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <div><h2 class="modal-title fs-5 mb-1" id="routerSetupModalTitle">Setup MikroTik</h2><p class="small text-body-secondary mb-0">Paste this once in the MikroTik Terminal. PixiePoint installs the agent and one 5-second scheduler.</p></div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <label class="form-label" for="router-setup-command">Terminal command</label>
                <textarea id="router-setup-command" class="form-control font-monospace" rows="8" readonly></textarea>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-copy-router-command>Copy command</button>
                <button type="button" class="button" data-bs-dismiss="modal">Done</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="routerModal" tabindex="-1" aria-labelledby="routerModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <form method="post">
                <div class="modal-header">
                    <div><h2 class="modal-title fs-5 mb-1" id="routerModalTitle">Add router</h2><p class="small text-body-secondary mb-0">Tell PixiePoint how to identify this MikroTik.</p></div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                    <input type="hidden" name="action" value="create">
                    <input type="hidden" name="id" value="0">
                    <div class="form-grid">
                        <div class="field"><label for="router-name">Display name</label><input id="router-name" name="name" required><small class="text-body-secondary">Friendly name shown in PixiePoint, such as Main Shop Router.</small></div>
                        <div class="field"><label for="router-identity">RouterOS identity</label><input id="router-identity" name="identity" required><small class="text-body-secondary">Must exactly match <code>/system identity</code> on the MikroTik.</small></div>
                        <div class="field"><label for="router-host">Public hostname / VPN IP</label><input id="router-host" name="public_host" placeholder="router.example.com or 10.10.0.2"><small class="text-body-secondary">Optional direct address. The outbound PixiePoint agent does not require it.</small></div>
                        <div class="field"><label for="router-location">Location</label><input id="router-location" name="location" placeholder="Branch, site or area"><small class="text-body-secondary">Optional physical location shown beside the router name.</small></div>
                    </div>
                    <div class="form-check mt-2" id="router-enabled-wrap" hidden><input class="form-check-input" type="checkbox" name="enabled" value="1" id="router-enabled"><label class="form-check-label" for="router-enabled">Enabled</label><div class="small text-body-secondary">Disabled routers remain saved but cannot poll PixiePoint.</div></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button class="button" type="submit" id="router-submit">Register router</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
document.getElementById('routerSetupModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    document.getElementById('routerSetupModalTitle').textContent = 'Setup ' + (button.dataset.router || 'MikroTik');
    document.getElementById('router-setup-command').value = button.dataset.command || '';
});
document.querySelector('[data-copy-router-command]').addEventListener('click', async function () {
    const field = document.getElementById('router-setup-command');
    await navigator.clipboard.writeText(field.value);
    this.textContent = 'Copied';
    setTimeout(() => { this.textContent = 'Copy command'; }, 1200);
});
document.getElementById('routerModal').addEventListener('show.bs.modal', function (event) {
    const button = event.relatedTarget;
    const edit = button && button.dataset.mode === 'edit';
    const form = this.querySelector('form');
    form.reset();
    form.querySelector('[name="action"]').value = edit ? 'update' : 'create';
    form.querySelector('[name="id"]').value = edit ? button.dataset.id : '0';
    form.querySelector('[name="name"]').value = edit ? button.dataset.name : '';
    form.querySelector('[name="identity"]').value = edit ? button.dataset.identity : '';
    form.querySelector('[name="public_host"]').value = edit ? button.dataset.host : '';
    form.querySelector('[name="location"]').value = edit ? button.dataset.location : '';
    form.querySelector('[name="enabled"]').checked = edit ? button.dataset.enabled === '1' : true;
    document.getElementById('router-enabled-wrap').hidden = !edit;
    document.getElementById('routerModalTitle').textContent = edit ? 'Edit router' : 'Add router';
    document.getElementById('router-submit').textContent = edit ? 'Save changes' : 'Register router';
});
</script>
<?php endif; ?>
