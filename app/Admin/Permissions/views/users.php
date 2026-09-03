<?php
/** @var array $users */
?>

<div class="heading">
    <div>
        <h1>Permissions</h1>
        <p class="muted">Choose a user to review or override access.</p>
    </div>
</div>

<section class="panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <strong><?= e($user['name']) ?></strong>
                            <div class="small text-body-secondary"><?= e($user['email']) ?></div>
                        </td>
                        <td><?= e(str_replace('_', ' ', (string) $user['platform_role'])) ?></td>
                        <td>
                            <span class="badge <?= $user['active'] ? '' : 'off' ?>">
                                <?= $user['active'] ? 'Active' : 'Disabled' ?>
                            </span>
                        </td>
                        <td class="text-end">
                            <a
                                class="btn btn-sm btn-outline-secondary"
                                href="/admin/permissions/<?= e($user['id']) ?>"
                            >
                                Manage
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
