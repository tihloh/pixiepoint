<?php /** @var array $devices */ ?>
<div class="heading"><div><h1>Devices</h1><p class="muted">Devices seen on your hotspots and their linked accounts.</p></div></div>
<section class="panel">
    <h2>Observed devices</h2>
    <table><thead><tr><th>MAC address</th><th>Account</th><th>Last IP</th><th>Sessions</th><th>Last seen</th></tr></thead>
    <tbody>
    <?php if (!$devices): ?><tr><td colspan="5" class="empty">No devices observed.</td></tr><?php endif; ?>
    <?php foreach ($devices as $d): ?><tr>
        <td class="code"><?= e($d['mac']) ?></td>
        <td><?= e($d['email'] ?: 'Guest') ?></td>
        <td><?= e($d['last_ip'] ?: '—') ?></td>
        <td><?= e($d['sessions']) ?></td>
        <td><?= e($d['last_seen_at']) ?></td>
    </tr><?php endforeach; ?>
    </tbody></table>
</section>
