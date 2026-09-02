<?php /** @var array $logs */ ?>
<div class="heading"><div><h1>Activity logs</h1><p class="muted">System activity for auditing and troubleshooting.</p></div></div>
<section class="panel">
    <h2>Recent activity</h2>
    <table><thead><tr><th>Event</th><th>Time</th></tr></thead>
    <tbody>
    <?php if (!$logs): ?><tr><td colspan="2" class="empty">No activity recorded yet.</td></tr><?php endif; ?>
    <?php foreach ($logs as $log): ?><tr>
        <td><?= e($log['summary'] ?? '—') ?></td>
        <td><?= e($log['when'] ?? '—') ?></td>
    </tr><?php endforeach; ?>
    </tbody></table>
</section>
