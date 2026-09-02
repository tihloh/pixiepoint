<?php
/** @var array $user */
/** @var string $role */
/** @var array $metrics */
/** @var array $sessions */
/** @var string $deviceRecovery */
/** @var bool $hasManagement */
/** @var string $routerRegistrationCommand */
?>

<div class="heading">
    <div>
        <h1>My dashboard</h1>
        <p class="muted">Points, devices and recent Wi-Fi activity.</p>
    </div>
    <span class="badge"><?= e($role) ?></span>
</div>

<section class="grid" aria-label="Account summary">
    <?php foreach ($metrics as $label => $value): ?>
        <div class="metric">
            <small><?= e($label) ?></small>
            <strong><?= e($value) ?></strong>
        </div>
    <?php endforeach; ?>
</section>

<section class="panel">
    <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap">
        <div>
            <h2>Register a MikroTik</h2>
            <p class="muted mb-0">
                Run this command in the RouterOS Terminal of the MikroTik you want to claim.
            </p>
        </div>
        <button class="btn btn-outline-secondary" type="button" data-copy-router-registration>
            Copy command
        </button>
    </div>

    <textarea
        id="router-registration-command"
        class="form-control font-monospace mt-3"
        rows="6"
        readonly
    ><?= e($routerRegistrationCommand) ?></textarea>

    <p class="small text-body-secondary mt-2 mb-0">
        The command reads the RouterOS identity and hardware serial. Registration fails if either is already claimed.
    </p>
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

<script>
    document.querySelector('[data-copy-router-registration]').addEventListener('click', async function () {
        const command = document.getElementById('router-registration-command').value;

        await navigator.clipboard.writeText(command);
        this.textContent = 'Copied';

        setTimeout(() => {
            this.textContent = 'Copy command';
        }, 1200);
    });
</script>
