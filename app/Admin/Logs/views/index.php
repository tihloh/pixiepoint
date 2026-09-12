<?php
/** @var array $logs */
?>

<div class="heading">
    <div>
        <h1>Activity logs</h1>
        <p class="muted">A readable history of account and system activity.</p>
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
                    <th class="text-nowrap" style="width:190px">Time</th>
                    <th>Activity</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!$logs): ?><tr><td colspan="2" class="empty">No activity recorded yet.</td></tr><?php endif; ?>
                <?php foreach($logs as $log): ?>
                    <tr>
                        <td class="text-nowrap text-body-secondary"><?= e($log['created_at']??$log['occurred_at']??'—') ?></td>
                        <td>
                            <div><?= e($log['activity']??$log['event']??'—') ?></div>
                            <?php $details=$log['details']??[]; ?>
                            <?php if($details): ?>
                                <div class="small text-body-secondary mt-1 d-flex flex-wrap gap-x-3 gap-y-1">
                                    <?php foreach($details as $detail): ?>
                                        <span class="me-3">
                                            <strong><?= e($detail['field']??'Field') ?>:</strong>
                                            <?= e($detail['before']??'None') ?>
                                            <span aria-hidden="true">→</span>
                                            <?= e($detail['now']??'None') ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
