<?php
/** @var string $message */
/** @var array $routers */
/** @var bool $canManageRouters */
/** @var string $csrf */
?>
<div class="heading"><div><h1>Routers</h1><p class="muted">Register each MikroTik using its exact RouterOS identity.</p></div></div>
<?= $message ?>
<section class="panel">
    <h2>Add router</h2>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="form-grid">
            <div class="field"><label>Display name</label><input name="name" required></div>
            <div class="field"><label>RouterOS identity</label><input name="identity" required></div>
            <div class="field"><label>Public hostname / VPN IP</label><input name="public_host"></div>
            <div class="field"><label>Location</label><input name="location"></div>
        </div>
        <button class="button">Register router</button>
    </form>
</section>
<section class="panel">
    <table><thead><tr><th>Router</th><th>Identity</th><th>Address</th><?php if ($canManageRouters): ?><th>Login script API key</th><?php endif; ?><th>Status</th><th>Last seen</th></tr></thead>
    <tbody>
    <?php if (!$routers): ?><tr><td colspan="<?= $canManageRouters ? 6 : 5 ?>" class="empty">No routers registered.</td></tr><?php endif; ?>
    <?php foreach ($routers as $router): ?><tr>
        <td><?= e($router['name']) ?><div class="muted"><?= e($router['location']) ?></div></td>
        <td class="code"><?= e($router['identity']) ?></td><td><?= e($router['public_host'] ?: '—') ?></td>
        <?php if ($canManageRouters): ?><td><code class="code"><?= e($router['api_key']) ?></code></td><?php endif; ?>
        <td><span class="badge <?= $router['enabled'] ? '' : 'off' ?>"><?= $router['enabled'] ? 'Enabled' : 'Disabled' ?></span></td>
        <td><?= e($router['last_seen_at'] ?: 'Never') ?></td>
    </tr><?php endforeach; ?>
    </tbody></table>
</section>
