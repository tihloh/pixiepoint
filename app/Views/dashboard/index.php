<?php
/** @var array $user */
/** @var string $role */
/** @var array $metrics */
/** @var array $sessions */
/** @var string $deviceRecovery */
/** @var bool $hasManagement */
?>
<div class="heading">
    <div>
        <span class="badge rounded-pill text-bg-primary mb-2"><?= e($role) ?></span>
        <h1>Welcome, <?= e($user['name']) ?></h1>
        <p class="muted">This is your PixiePoint home. The navigation and tools shown to you are based on your account role and permissions.</p>
    </div>
</div>

<div class="alert alert-info border-0 mb-4">
    <strong>One account, permission-based access.</strong>
    Members see their own account activity. Staff and operators get the management tools assigned to them. Platform administrators receive broader access without using a separate website or login screen.
</div>

<section class="grid">
    <?php foreach ($metrics as $label => $value): ?>
        <div class="metric">
            <small><?= e($label) ?></small>
            <strong><?= e($value) ?></strong>
        </div>
    <?php endforeach; ?>
</section>

<?= $deviceRecovery ?>

<section class="panel">
    <h2>What you can do here</h2>
    <div class="online-dashboard-help">
        <div>
            <strong>Account &amp; rewards</strong>
            <small>Keep your PixiePoint identity, points and account-linked hotspot activity together.</small>
        </div>
        <div>
            <strong>Devices</strong>
            <small>Link or recover devices so activity can follow your account even when device identifiers change.</small>
        </div>
        <div>
            <strong>Wi-Fi history</strong>
            <small>Review recent sessions that were associated with this account.</small>
        </div>
        <?php if ($hasManagement): ?>
        <div>
            <strong>Management tools</strong>
            <small>Additional menu items are visible because this account has management permissions. Features you are not allowed to use remain hidden.</small>
        </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($hasManagement): ?>
<section class="panel">
    <h2>Management access</h2>
    <p class="muted mb-0">Use the navigation above to manage only the PixiePoint resources permitted for your account, such as routers, vendos, vouchers, devices, sessions, sales or logs.</p>
</section>
<?php endif; ?>

<section class="panel">
    <div class="d-flex flex-column flex-md-row justify-content-between gap-2 mb-3">
        <div>
            <h2 class="mb-1">Recent Wi-Fi sessions</h2>
            <p class="muted mb-0">Only sessions linked to your PixiePoint account are shown here. Guest hotspot access can still work without registration.</p>
        </div>
    </div>
    <table>
        <thead><tr><th>Access</th><th>Device</th><th>Router</th><th>Status</th><th>Updated</th></tr></thead>
        <tbody>
        <?php if (!$sessions): ?>
            <tr><td colspan="5" class="empty">No account-linked sessions yet. After you use PixiePoint Wi-Fi while signed in or claim a device, matching activity can appear here.</td></tr>
        <?php else: ?>
            <?php foreach ($sessions as $session): ?>
            <tr>
                <td><?= e($session['username'] ?: '—') ?></td>
                <td class="code"><?= e($session['mac'] ?: '—') ?></td>
                <td><?= e($session['router_name'] ?: '—') ?></td>
                <td><span class="badge <?= $session['status'] === 'active' ? '' : 'off' ?>"><?= e($session['status']) ?></span></td>
                <td><?= e($session['updated_at']) ?></td>
            </tr>
            <?php endforeach; ?>
        <?php endif; ?>
        </tbody>
    </table>
</section>
