<?php /** @var array $sessions */ ?>
<div class="heading"><div><h1>Sessions</h1><p class="muted">Review hotspot authentication and accounting records for both guests and registered PixiePoint users.</p></div></div>
<section class="panel">
    <h2>Recorded hotspot sessions</h2>
    <p class="muted">Each row shows who connected, which hotspot account and device were used, where the session ran and how much time/data was recorded.</p>
    <table><thead><tr><th>Account</th><th>Hotspot user</th><th>Device</th><th>Router</th><th>Status</th><th>Uptime</th><th>Transfer</th></tr></thead>
    <tbody>
    <?php if (!$sessions): ?><tr><td colspan="7" class="empty">No sessions recorded.</td></tr><?php endif; ?>
    <?php foreach ($sessions as $s): ?><tr>
        <td><?= e($s['account_email'] ?: 'Guest') ?><div class="small text-body-secondary">PixiePoint account linked to the session, if any.</div></td>
        <td><?= e($s['username'] ?: '—') ?><div class="small text-body-secondary">Voucher or MikroTik hotspot username used.</div></td>
        <td class="code"><?= e($s['mac'] ?: '—') ?><div class="small text-body-secondary">Client device MAC reported by the hotspot.</div></td>
        <td><?= e($s['router_name'] ?: '—') ?><div class="small text-body-secondary">Router that handled the session.</div></td>
        <td><span class="badge <?= $s['status'] === 'active' ? '' : 'off' ?>"><?= e($s['status']) ?></span><div class="small text-body-secondary">Current or last recorded session state.</div></td>
        <td><?= e(duration_nice((int)$s['uptime_seconds'])) ?><div class="small text-body-secondary">Total connected time reported.</div></td>
        <td><?= e(bytes_nice((int)$s['bytes_in'] + (int)$s['bytes_out'])) ?><div class="small text-body-secondary">Combined download and upload traffic.</div></td>
    </tr><?php endforeach; ?>
    </tbody></table>
</section>
