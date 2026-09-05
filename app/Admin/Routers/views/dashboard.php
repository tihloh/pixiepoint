<?php
/** @var array $router */
/** @var array<string,array{label:string,value:mixed}> $metrics */
/** @var array $recentSessions */
/** @var bool $canManageRouters */
/** @var bool $canManageTeam */
/** @var bool $canViewSales */
/** @var string $csrf */
?>

<div class="heading">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge">Router</span>
            <span class="text-body-secondary small">#<?= e($router['id']) ?></span>
        </div>
        <h1><?= e($router['name']) ?></h1>
        <p class="muted mb-0">
            <?= e($router['identity']) ?>
            <?php if (!empty($router['location'])): ?>
                · <?= e($router['location']) ?>
            <?php endif; ?>
        </p>
    </div>
    <div class="actions">
        <?php if ($canManageTeam): ?>
            <a class="btn btn-outline-secondary" href="/admin/routers/<?= e($router['id']) ?>/team">Team</a>
        <?php endif; ?>
        <?php if ($canManageRouters): ?>
            <a class="btn btn-outline-secondary" href="/admin/routers/<?= e($router['id']) ?>/settings">Router settings</a>
        <?php endif; ?>
    </div>
</div>

<section class="grid" aria-label="Router summary">
    <?php foreach ($metrics as $metric): ?>
        <div class="metric">
            <small><?= e($metric['label']) ?></small>
            <strong>
                <?php if ($metric['label'] === 'Sales today'): ?>
                    ₱<?= e(number_format((float) $metric['value'], 2)) ?>
                <?php else: ?>
                    <?= e((int) $metric['value']) ?>
                <?php endif; ?>
            </strong>
        </div>
    <?php endforeach; ?>
</section>

<section class="panel">
    <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-2 mb-3">
        <div>
            <h2 class="mb-1">Router overview</h2>
            <p class="muted mb-0">Manage everything associated with this MikroTik from the navigation.</p>
        </div>
        <span class="badge <?= $router['enabled'] ? '' : 'off' ?>">
            <?= $router['enabled'] ? 'Enabled' : 'Disabled' ?>
        </span>
    </div>

    <div class="row g-2">
        <?php if (isset($metrics['vendos'])): ?>
            <div class="col-sm-6 col-xl">
                <a class="btn btn-outline-secondary w-100" href="/admin/vendos">Vendos</a>
            </div>
        <?php endif; ?>
        <?php if (isset($metrics['vouchers'])): ?>
            <div class="col-sm-6 col-xl">
                <a class="btn btn-outline-secondary w-100" href="/admin/vouchers">Vouchers</a>
            </div>
        <?php endif; ?>
        <?php if (isset($metrics['devices'])): ?>
            <div class="col-sm-6 col-xl">
                <a class="btn btn-outline-secondary w-100" href="/admin/devices">Devices</a>
            </div>
        <?php endif; ?>
        <?php if (isset($metrics['sessions'])): ?>
            <div class="col-sm-6 col-xl">
                <a class="btn btn-outline-secondary w-100" href="/admin/sessions">Sessions</a>
            </div>
        <?php endif; ?>
        <?php if ($canViewSales): ?>
            <div class="col-sm-6 col-xl">
                <a class="btn btn-outline-secondary w-100" href="/admin/sales">Sales</a>
            </div>
        <?php endif; ?>
    </div>
</section>

<?php if ($recentSessions): ?>
    <section class="panel">
        <h2>Recent sessions</h2>
        <table>
            <thead>
                <tr>
                    <th>Access</th>
                    <th>Device</th>
                    <th>Status</th>
                    <th>Updated</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($recentSessions as $session): ?>
                    <tr>
                        <td><?= e($session['username'] ?: '—') ?></td>
                        <td class="code"><?= e($session['mac'] ?: '—') ?></td>
                        <td>
                            <span class="badge <?= $session['status'] === 'active' ? '' : 'off' ?>">
                                <?= e($session['status']) ?>
                            </span>
                        </td>
                        <td><?= e($session['updated_at']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>
