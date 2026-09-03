<?php
/** @var array $users */
/** @var bool $canManage */
/** @var bool $canManagePermissions */
/** @var bool $canManageGroups */
/** @var string $message */
/** @var string $csrf */
?>

<?= $message ?>

<section class="panel p-0 overflow-hidden">
    <div class="d-flex justify-content-between align-items-center px-4 py-3 border-bottom">
        <div class="d-flex align-items-center gap-3">
            <h1 class="h4 mb-0">Users</h1>
            <?php if ($canManageGroups): ?>
                <a class="btn btn-sm btn-link text-decoration-none" href="/admin/groups">Groups</a>
            <?php endif; ?>
        </div>

        <?php if ($canManage): ?>
            <button class="button" type="button" data-bs-toggle="modal" data-bs-target="#addUserModal">
                Add User
            </button>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="text-end">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$users): ?>
                    <tr><td colspan="5" class="empty">No users found.</td></tr>
                <?php endif; ?>

                <?php foreach ($users as $user): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-3">
                                <?php if (!empty($user['avatar_url'])): ?>
                                    <img src="<?= e($user['avatar_url']) ?>" alt="" width="44" height="44" class="rounded-circle object-fit-cover border">
                                <?php else: ?>
                                    <div class="rounded-circle border d-flex align-items-center justify-content-center fw-bold flex-shrink-0" style="width:44px;height:44px">
                                        <?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?>
                                    </div>
                                <?php endif; ?>
                                <div><strong><?= e($user['name']) ?></strong><div class="small text-body-secondary"><?= e($user['email']) ?></div></div>
                            </div>
                        </td>
                        <td><span class="badge off"><?= e(ucwords(str_replace('_', ' ', (string) ($user['platform_role'] ?? 'member')))) ?></span></td>
                        <td><span class="badge <?= !empty($user['active']) ? '' : 'off' ?>"><?= !empty($user['active']) ? 'Active' : 'Disabled' ?></span></td>
                        <td><?= e($user['created_at'] ?? '') ?></td>
                        <td class="text-end text-nowrap">
                            <?php if ($canManage): ?><a class="btn btn-sm btn-outline-secondary" href="/admin/users/<?= e($user['id']) ?>/edit">Edit</a><?php endif; ?>
                            <?php if ($canManagePermissions): ?><a class="btn btn-sm btn-outline-primary" href="/admin/users/<?= e($user['id']) ?>/permissions">Permissions</a><?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php if ($canManage): ?>
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog"><div class="modal-content"><form method="post">
        <div class="modal-header"><h2 class="modal-title fs-5">Add User</h2><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Close"></button></div>
        <div class="modal-body">
            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
            <div class="mb-3"><label class="form-label" for="new-user-name">Name</label><input class="form-control" id="new-user-name" name="name" required></div>
            <div><label class="form-label" for="new-user-email">Email</label><input class="form-control" id="new-user-email" name="email" type="email" required></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="button" type="submit">Add User</button></div>
    </form></div></div>
</div>
<?php endif; ?>
