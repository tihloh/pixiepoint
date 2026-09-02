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
        <h1>My dashboard</h1>
        <p class="muted">Points, devices and recent Wi-Fi activity.</p>
    </div>
</div>

<section class="grid" aria-label="Account summary">
    <?php foreach ($metrics as $label => $value): ?>
        <div class="metric">
            <small><?= e($label) ?></small>
            <strong><?= e($value) ?></strong>
        </div>
    <?php endforeach; ?>
</section>

<?= $deviceRecovery ?>

<section class="panel">
    <h2>Recent Wi-Fi sessions</h2>

    <table>
        <thead>
            <tr>
                <th>Access</th>
                <th>Device</th>
                <th>Router</th>
                <th>Status</th>
                <th>Updated</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!$sessions): ?>
                <tr>
                    <td colspan="5" class="empty">No recent sessions.</td>
                </tr>
            <?php else: ?>
                <?php foreach ($sessions as $session): ?>
                    <tr>
                        <td><?= e($session['username'] ?: '—') ?></td>
                        <td class="code"><?= e($session['mac'] ?: '—') ?></td>
                        <td><?= e($session['router_name'] ?: '—') ?></td>
                        <td>
                            <span class="badge <?= $session['status'] === 'active' ? '' : 'off' ?>">
                                <?= e($session['status']) ?>
                            </span>
                        </td>
                        <td><?= e($session['updated_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</section>
