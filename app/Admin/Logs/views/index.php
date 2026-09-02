<?php /** @var array $logs */ ?>
<div class="heading"><div><h1>Activity logs</h1><p class="muted">Review important PixiePoint actions for auditing, troubleshooting and accountability.</p></div></div>
<section class="panel">
    <h2>Recorded activity</h2>
    <p class="muted">Each row identifies what happened, who performed it, which record was affected, the recorded message and when it occurred.</p>
    <table><thead><tr><th>Action</th><th>Actor</th><th>Subject</th><th>Message</th><th>Time</th></tr></thead>
    <tbody>
    <?php if (!$logs): ?><tr><td colspan="5" class="empty">No activity recorded yet.</td></tr><?php endif; ?>
    <?php foreach ($logs as $log): ?><tr>
        <td class="code"><?= e($log['action'] ?? '') ?><div class="small text-body-secondary">Machine-readable action that was recorded.</div></td>
        <td><?= e($log['actor_id'] ?? 'System') ?><div class="small text-body-secondary">Account or system process that performed the action.</div></td>
        <td><?= e(($log['subject_type'] ?? '—') . (($log['subject_id'] ?? null) !== null ? ' #' . $log['subject_id'] : '')) ?><div class="small text-body-secondary">Resource or record affected by the action.</div></td>
        <td><?= e($log['message'] ?? '—') ?><div class="small text-body-secondary">Additional context saved with the event.</div></td>
        <td><?= e($log['created_at'] ?? $log['occurred_at'] ?? '—') ?><div class="small text-body-secondary">When PixiePoint recorded the event.</div></td>
    </tr><?php endforeach; ?>
    </tbody></table>
</section>
