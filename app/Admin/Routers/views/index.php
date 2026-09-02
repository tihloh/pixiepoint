<?php
/** @var string $message */
/** @var array $routers */
/** @var bool $canManageRouters */
/** @var string $csrf */
?>
<div class="heading"><div><h1>Routers</h1><p class="muted">Register MikroTik routers so PixiePoint can recognize hotspot traffic and accounting events.</p></div></div>
<?= $message ?>
<section class="panel">
    <h2>Add router</h2>
    <p class="muted">Add one MikroTik by entering the identity and address PixiePoint should use to recognize it.</p>
    <form method="post">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <div class="form-grid">
            <div class="field"><label>Display name</label><input name="name" required><small class="text-body-secondary">Friendly name shown in PixiePoint, such as Main Shop Router.</small></div>
            <div class="field"><label>RouterOS identity</label><input name="identity" required><small class="text-body-secondary">Must exactly match <code>/system identity</code> on the MikroTik.</small></div>
            <div class="field"><label>Public hostname / VPN IP</label><input name="public_host" placeholder="router.example.com or 10.10.0.2"><small class="text-body-secondary">Address used to validate or reach the router when required.</small></div>
            <div class="field"><label>Location</label><input name="location" placeholder="Branch, site or area"><small class="text-body-secondary">Optional physical location to help identify the router.</small></div>
        </div>
        <button class="button">Register router</button>
        <small class="d-block text-body-secondary mt-2">Saves this router and generates its PixiePoint API key.</small>
    </form>
</section>
<section class="panel">
    <h2>Registered routers</h2>
    <p class="muted">Shows every router available to this account, its identity, address, status and last contact with PixiePoint.</p>
    <table><thead><tr><th>Router</th><th>Identity</th><th>Address</th><?php if ($canManageRouters): ?><th>Login script API key</th><?php endif; ?><th>Status</th><th>Last seen</th></tr></thead>
    <tbody>
    <?php if (!$routers): ?><tr><td colspan="<?= $canManageRouters ? 6 : 5 ?>" class="empty">No routers registered.</td></tr><?php endif; ?>
    <?php foreach ($routers as $router): ?><tr>
        <td><?= e($router['name']) ?><div class="muted"><?= e($router['location']) ?></div></td>
        <td class="code"><?= e($router['identity']) ?></td><td><?= e($router['public_host'] ?: '—') ?></td>
        <?php if ($canManageRouters): ?><td><code class="code"><?= e($router['api_key']) ?></code><div class="small text-body-secondary">Use this key in the PixiePoint RouterOS integration.</div></td><?php endif; ?>
        <td><span class="badge <?= $router['enabled'] ? '' : 'off' ?>"><?= $router['enabled'] ? 'Enabled' : 'Disabled' ?></span></td>
        <td><?= e($router['last_seen_at'] ?: 'Never') ?></td>
    </tr><?php endforeach; ?>
    </tbody></table>
</section>
