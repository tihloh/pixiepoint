<?php /** @var array $sessions */ ?>
<div class="heading"><div><h1>Sessions</h1><p class="muted">Hotspot connections for guests and registered users.</p></div></div>
<section class="panel">
    <h2>Recorded sessions</h2>
    <table><thead><tr><th>Account</th><th>Hotspot user</th><th>Device</th><th>Router</th><th>Status</th><th>Uptime</th><th>Transfer</th></tr></thead>
    <tbody>
    <?php if (!$sessions): ?><tr><td colspan="7" class="empty">No sessions recorded.</td></tr><?php endif; ?>
    <?php foreach ($sessions as $s): ?><tr>
        <td><?= e($s['account_email'] ?: 'Guest') ?></td>
        <td><?= e($s['username'] ?: '—') ?></td>
        <td class="code"><?= e($s['mac'] ?: '—') ?></td>
        <td><?= e($s['router_name'] ?: '—') ?></td>
        <td><span class="badge <?= $s['status'] === 'active' ? '' : 'off' ?>"><?= e($s['status']) ?></span></td>
        <td><?= e(duration_nice((int) $s['uptime_seconds'])) ?></td>
        <td><?= e(bytes_nice((int) $s['bytes_in'] + (int) $s['bytes_out'])) ?></td>
    </tr><?php endforeach; ?>
    </tbody></table>
</section>
