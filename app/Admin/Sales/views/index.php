<?php
/** @var array $summary */
/** @var array $events */
/** @var array $router */
?>

<div class="heading">
    <div>
        <h1>Vendo sales</h1>
        <p class="muted">
            Sales, extensions and points from <strong><?= e($router['name']) ?></strong>
            <span class="text-body-secondary">· <?= e($router['identity']) ?></span>
        </p>
    </div>
</div>

<section class="grid" aria-label="Today's sales summary">
    <div class="metric">
        <small>Today sales</small>
        <strong>₱<?= e(number_format((int) ($summary['total'] ?? 0))) ?></strong>
    </div>
    <div class="metric">
        <small>Transactions</small>
        <strong><?= e($summary['transactions'] ?? 0) ?></strong>
    </div>
    <div class="metric">
        <small>Points awarded</small>
        <strong><?= e($summary['points'] ?? 0) ?></strong>
    </div>
</section>

<section class="panel">
    <h2>Sales records</h2>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>Router</th>
                    <th>Vendo</th>
                    <th>Voucher</th>
                    <th>Device</th>
                    <th>Type</th>
                    <th>Amount</th>
                    <th>Points</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$events): ?>
                    <tr><td colspan="8" class="empty">No sales recorded.</td></tr>
                <?php endif; ?>

                <?php foreach ($events as $event): ?>
                    <tr>
                        <td class="text-nowrap"><?= e($event['created_at']) ?></td>
                        <td><?= e($event['router_name']) ?></td>
                        <td><?= e($event['vendo_name'] ?: '—') ?></td>
                        <td class="code text-nowrap"><?= e($event['username']) ?></td>
                        <td class="code text-nowrap"><?= e($event['device_mac'] ?: $event['mac'] ?: '—') ?></td>
                        <td class="text-nowrap"><?= $event['is_extension'] ? 'Extension' : 'New access' ?></td>
                        <td class="text-nowrap">₱<?= e(number_format((int) $event['amount_pesos'])) ?></td>
                        <td><?= e($event['points_awarded']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>