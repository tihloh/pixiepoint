<?php
/** @var array $users */
/** @var bool $canManage */
/** @var bool $canManageGroups */
/** @var string $message */
/** @var string $csrf */
?>

<div class="heading">
    <div><h1>Users</h1></div>
    <div class="d-flex gap-2">
        <?php if ($canManageGroups): ?><a class="btn btn-outline-secondary" href="/admin/groups">Groups</a><?php endif; ?>
        <?php if ($canManage): ?><button class="button" type="button" data-bs-toggle="modal" data-bs-target="#addUserModal">Add user</button><?php endif; ?>
    </div>
</div>

<?= $message ?>
<section class="panel"><div class="table-responsive"><table class="table align-middle mb-0">
    <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Created</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($users as $user): ?>
        <tr>
            <td><div class="d-flex align-items-center gap-3">
                <?php if (!empty($user['avatar_url'])): ?><img src="<?= e($user['avatar_url']) ?>" alt="" width="42" height="42" class="rounded-circle object-fit-cover"><?php else: ?><div class="rounded-circle border d-flex align-items-center justify-content-center fw-bold" style="width:42px;height:42px"><?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?></div><?php endif; ?>
                <div><strong><?= e($user['name']) ?></strong><div class="small text-body-secondary"><?= e($user['email']) ?></div></div>
            </div></td>
            <td><?= e(ucwords(str_replace('_', ' ', (string) ($user['platform_role'] ?? 'member')))) ?></td>
            <td><span class="badge <?= !empty($user['active']) ? '' : 'off' ?>"><?= !empty($user['active']) ? 'Active' : 'Disabled' ?></span></td>
            <td><?= e($user['created_at'] ?? '') ?></td>
            <td class="text-end"><a class="btn btn-sm btn-outline-secondary" href="/admin/users/<?= e($user['id']) ?>">Manage</a></td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table></div></section>

<?php if ($canManage): ?>
<div class="modal fade" id="addUserModal" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><form method="post">
    <div class="modal-header"><h2 class="modal-title fs-5">Add user</h2><button class="btn-close" type="button" data-bs-dismiss="modal"></button></div>
    <div class="modal-body"><input type="hidden" name="_csrf" value="<?= e($csrf) ?>"><div class="mb-3"><label class="form-label">Name</label><input class="form-control" name="name" required></div><div><label class="form-label">Email</label><input class="form-control" name="email" type="email" required></div></div>
    <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancel</button><button class="button" type="submit">Add user</button></div>
</form></div></div></div>
<?php endif; ?>
