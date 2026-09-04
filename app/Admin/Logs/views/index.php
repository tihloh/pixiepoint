<?php
/** @var array $logs */
?>

<div class="heading">
    <div>
        <h1>Activity logs</h1>
        <p class="muted">System activity for auditing and troubleshooting.</p>
    </div>
</div>

<section class="panel">
    <h2>Recent activity</h2>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Event</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$logs): ?>
                    <tr><td colspan="2" class="empty">No activity recorded yet.</td></tr>
                <?php endif; ?>

                <?php foreach ($logs as $log): ?>
                    <tr>
                        <td><?= e($log['event'] ?? '—') ?></td>
                        <td class="text-nowrap"><?= e($log['created_at'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
