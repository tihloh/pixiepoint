<?php
/** @var array $sessions */
?>

<div class="heading">
    <div>
        <h1>Sessions</h1>
        <p class="muted">Hotspot connections for guests and registered users.</p>
    </div>
</div>

<section class="panel">
    <h2>Recorded sessions</h2>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Account</th>
                    <th>Hotspot user</th>
                    <th>Device</th>
                    <th>Router</th>
                    <th>Status</th>
                    <th>Uptime</th>
                    <th>Transfer</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$sessions): ?>
                    <tr><td colspan="7" class="empty">No sessions recorded.</td></tr>
                <?php endif; ?>

                <?php foreach ($sessions as $session): ?>
                    <tr>
                        <td><?= e($session['account_email'] ?: 'Guest') ?></td>
                        <td><?= e($session['username'] ?: '—') ?></td>
                        <td class="code text-nowrap"><?= e($session['mac'] ?: '—') ?></td>
                        <td><?= e($session['router_name'] ?: '—') ?></td>
                        <td><span class="badge <?= $session['status'] === 'active' ? '' : 'off' ?>"><?= e($session['status']) ?></span></td>
                        <td class="text-nowrap"><?= e(duration_nice((int) $session['uptime_seconds'])) ?></td>
                        <td class="text-nowrap"><?= e(bytes_nice((int) $session['bytes_in'] + (int) $session['bytes_out'])) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
