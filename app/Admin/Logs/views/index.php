<?php /** @var array $logs */ ?>
<div class="heading"><div><h1>Activity logs</h1><p class="muted">Structured audit history provided by Prefab Logs.</p></div></div>
<section class="panel">
    <table><thead><tr><th>Action</th><th>Actor</th><th>Subject</th><th>Message</th><th>Time</th></tr></thead>
    <tbody>
    <?php if (!$logs): ?><tr><td colspan="5" class="empty">No activity recorded yet.</td></tr><?php endif; ?>
    <?php foreach ($logs as $log): ?><tr>
        <td class="code"><?= e($log['action'] ?? '') ?></td><td><?= e($log['actor_id'] ?? 'System') ?></td>
        <td><?= e(($log['subject_type'] ?? '—') . (($log['subject_id'] ?? null) !== null ? ' #' . $log['subject_id'] : '')) ?></td>
        <td><?= e($log['message'] ?? '—') ?></td><td><?= e($log['created_at'] ?? $log['occurred_at'] ?? '—') ?></td>
    </tr><?php endforeach; ?>
    </tbody></table>
</section>
