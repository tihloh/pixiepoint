<?php
/** @var array $logs */
?>

<div class="heading">
    <div>
        <h1>Activity logs</h1>
        <p class="muted">See who did what, what was affected, and what changed.</p>
    </div>
</div>

<section class="panel">
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <h2 class="mb-0">Recent activity</h2>
        <span class="text-body-secondary small"><?= e(count($logs)) ?> events</span>
    </div>

    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th>Who</th>
                    <th>Event</th>
                    <th>What</th>
                    <th>Changes</th>
                    <th class="text-nowrap">Time</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!$logs): ?>
                    <tr><td colspan="5" class="empty">No activity recorded yet.</td></tr>
                <?php endif; ?>

                <?php foreach($logs as $log): ?>
                    <tr>
                        <td class="text-nowrap"><strong><?= e($log['who']??'Someone') ?></strong></td>
                        <td><?= e($log['event']??$log['summary']??'—') ?></td>
                        <td><?= e($log['what']??'—') ?></td>
                        <td>
                            <?php $details=$log['details']??[]; ?>
                            <?php if($details): ?>
                                <div class="d-flex flex-column gap-1">
                                    <?php foreach($details as $detail): ?>
                                        <div class="small">
                                            <strong><?= e($detail['field']??'Field') ?>:</strong>
                                            <span class="text-body-secondary"><?= e($detail['before']??'None') ?></span>
                                            <span aria-hidden="true">→</span>
                                            <span><?= e($detail['now']??'None') ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span class="text-body-secondary">—</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-nowrap"><?= e($log['created_at']??$log['occurred_at']??'—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
