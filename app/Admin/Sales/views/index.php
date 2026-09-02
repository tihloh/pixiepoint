<?php
/** @var array $summary */
/** @var array $events */
?>
<div class="heading"><div><h1>Vendo sales</h1><p class="muted">Idempotent sales recorded by authenticated RouterOS login events.</p></div></div>
<section class="grid">
    <div class="metric"><small>Today sales</small><strong>₱<?= e(number_format((int)($summary['total'] ?? 0))) ?></strong></div>
    <div class="metric"><small>Transactions</small><strong><?= e($summary['transactions'] ?? 0) ?></strong></div>
    <div class="metric"><small>Points awarded</small><strong><?= e($summary['points'] ?? 0) ?></strong></div>
</section>
<section class="panel">
    <table><thead><tr><th>Time</th><th>Router</th><th>Vendo</th><th>Voucher</th><th>Device</th><th>Type</th><th>Amount</th><th>Points</th></tr></thead>
    <tbody>
    <?php if (!$events): ?><tr><td colspan="8" class="empty">No login sales recorded.</td></tr><?php endif; ?>
    <?php foreach ($events as $event): ?><tr>
        <td><?= e($event['created_at']) ?></td><td><?= e($event['router_name']) ?></td><td><?= e($event['vendo_name'] ?: '—') ?></td><td class="code"><?= e($event['username']) ?></td><td class="code"><?= e($event['device_mac'] ?: $event['mac'] ?: '—') ?></td>
        <td><?= $event['is_extension'] ? 'Extension' : 'New access' ?></td><td>₱<?= e(number_format((int)$event['amount_pesos'])) ?></td><td><?= e($event['points_awarded']) ?></td>
    </tr><?php endforeach; ?>
    </tbody></table>
</section>
