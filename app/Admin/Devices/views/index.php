<?php /** @var array $devices */ ?>
<div class="heading"><div><h1>Devices</h1><p class="muted">Review devices seen on PixiePoint hotspots and whether they are still guests or already linked to an account.</p></div></div>
<section class="panel">
    <h2>Observed devices</h2>
    <p class="muted">Each row is a device appearance reported by the hotspot. Use the MAC, last IP, account link and last-seen time to identify it.</p>
    <table><thead><tr><th>MAC address</th><th>Account</th><th>Last IP</th><th>Sessions</th><th>Last seen</th></tr></thead>
    <tbody>
    <?php if (!$devices): ?><tr><td colspan="5" class="empty">No devices observed.</td></tr><?php endif; ?>
    <?php foreach ($devices as $d): ?><tr>
        <td class="code"><?= e($d['mac']) ?><div class="small text-body-secondary">Network identifier reported by the hotspot.</div></td>
        <td><?= e($d['email'] ?: 'Guest') ?><div class="small text-body-secondary"><?= $d['email'] ? 'Linked PixiePoint account.' : 'Not yet linked to an account.' ?></div></td>
        <td><?= e($d['last_ip'] ?: '—') ?><div class="small text-body-secondary">Most recent client IP seen.</div></td>
        <td><?= e($d['sessions']) ?><div class="small text-body-secondary">Recorded sessions for this device.</div></td>
        <td><?= e($d['last_seen_at']) ?><div class="small text-body-secondary">Most recent activity observed.</div></td>
    </tr><?php endforeach; ?>
    </tbody></table>
</section>
