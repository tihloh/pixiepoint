<?php
/** @var string $message */
/** @var array $routers */
/** @var bool $canManageRouters */
/** @var bool $canCreateRouters */
/** @var string $csrf */
?>

<div class="heading">
    <div>
        <h1>Gateways</h1>
        <p class="muted">Select a gateway linked to your PixiePoint account.</p>
    </div>
</div>

<?= $message ?>

<section class="panel">
    <h2>Gateways</h2>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Gateway name</th><th>Identity</th><th>Address</th><th>Status</th><th>Last seen</th></tr></thead>
            <tbody>
                <?php if (!$routers): ?><tr><td colspan="5" class="empty">No gateways linked to this account yet.</td></tr><?php endif; ?>
                <?php foreach ($routers as $router): ?>
                    <tr role="button" tabindex="0" onclick="window.location.href='/admin/routers/<?= e($router['id']) ?>'" onkeydown="if(event.key==='Enter'||event.key===' '){event.preventDefault();window.location.href='/admin/routers/<?= e($router['id']) ?>'}">
                        <td><a class="text-decoration-none text-body" href="/admin/routers/<?= e($router['id']) ?>"><strong><?= e($router['name']) ?></strong><div class="small text-body-secondary"><?= e($router['location'] ?: 'No location set') ?></div></a></td>
                        <td class="code"><?= e($router['identity']) ?></td>
                        <td><?= e($router['public_host'] ?: 'Not set') ?></td>
                        <td><span class="badge <?= $router['enabled'] ? '' : 'off' ?>"><?= $router['enabled'] ? 'Enabled' : 'Disabled' ?></span></td>
                        <td><?= e($router['last_seen_at'] ?: 'Never') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>