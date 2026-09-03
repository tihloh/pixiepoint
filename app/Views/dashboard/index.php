<?php
/** @var array $user */
/** @var string $role */
/** @var array $metrics */
/** @var array $sessions */
/** @var string $deviceRecovery */
/** @var bool $hasManagement */
/** @var string $routerRegistrationCommand */

$isRouterOwner = ($user['platform_role'] ?? 'member') === 'pisowifi_owner';
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

<?php if (!$isRouterOwner): ?>
    <div class="text-end mb-3">
        <button
            class="btn btn-link btn-sm text-body-secondary"
            type="button"
            data-bs-toggle="modal"
            data-bs-target="#register-router-modal"
        >
            Own a PisoWiFi? Register your MikroTik
        </button>
    </div>
<?php endif; ?>

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

<?php if (!$isRouterOwner): ?>
    <div class="modal fade" id="register-router-modal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title fs-5">Register your MikroTik</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">
                    <p class="mb-3">
                        Run this command in the RouterOS Terminal of the MikroTik you own.
                    </p>

                    <textarea
                        id="router-registration-command"
                        class="form-control font-monospace"
                        rows="6"
                        readonly
                    ><?= e($routerRegistrationCommand) ?></textarea>
                </div>

                <div class="modal-footer">
                    <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">
                        Close
                    </button>
                    <button class="btn btn-primary" type="button" data-copy-router-registration>
                        Copy command
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        const copyRouterRegistration = document.querySelector('[data-copy-router-registration]');

        copyRouterRegistration?.addEventListener('click', async function () {
            const command = document.getElementById('router-registration-command').value;

            await navigator.clipboard.writeText(command);
            this.textContent = 'Copied';

            setTimeout(() => {
                this.textContent = 'Copy command';
            }, 1200);
        });
    </script>
<?php endif; ?>
