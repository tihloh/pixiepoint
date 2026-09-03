<?php
/** @var array $users */
/** @var bool $canManagePermissions */
?>

<div class="heading">
    <div>
        <h1>Users</h1>
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
                    <th>Created</th>
                    <?php if ($canManagePermissions): ?>
                        <th class="text-end">Action</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!$users): ?>
                    <tr>
                        <td colspan="<?= $canManagePermissions ? 5 : 4 ?>" class="empty">
                            No users found.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <strong><?= e($user['name']) ?></strong>
                            <div class="small text-body-secondary"><?= e($user['email']) ?></div>
                        </td>
                        <td><?= e(ucwords(str_replace('_', ' ', (string) $user['platform_role']))) ?></td>
                        <td>
                            <span class="badge <?= $user['active'] ? '' : 'off' ?>">
                                <?= $user['active'] ? 'Active' : 'Disabled' ?>
                            </span>
                        </td>
                        <td><?= e($user['created_at']) ?></td>
                        <?php if ($canManagePermissions): ?>
                            <td class="text-end">
                                <a
                                    class="btn btn-sm btn-outline-secondary"
                                    href="/admin/users/<?= e($user['id']) ?>"
                                >
                                    Manage
                                </a>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
