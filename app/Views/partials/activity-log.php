<?php
/** @var array $logs */
/** @var string $scope */
?>
<div class="border-top mt-4 pt-4" aria-label="Activity log">
    <div class="d-flex align-items-center justify-content-between gap-3 mb-3">
        <div><h3 class="fs-5 mb-1">Activity Log</h3><div class="small text-body-secondary">Recent activity for this page.</div></div>
        <?php if($logs): ?><span class="text-body-secondary small"><?= e(count($logs)) ?> recent</span><?php endif; ?>
    </div>
    <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
            <thead><tr><th class="text-nowrap" style="width:190px">Time</th><th>Activity</th></tr></thead>
            <tbody>
                <?php if(!$logs): ?><tr><td colspan="2" class="empty">No matching activity recorded yet.</td></tr><?php endif; ?>
                <?php foreach($logs as $log): ?><tr><td class="text-nowrap text-body-secondary"><?= e($log['created_at']??$log['occurred_at']??'—') ?></td><td><?= e($log['event']??$log['summary']??'—') ?></td></tr><?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
