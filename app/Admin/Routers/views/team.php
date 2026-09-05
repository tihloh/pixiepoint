<?php
/** @var array|null $router */
/** @var array $members */
/** @var string $message */
/** @var bool $canManageTeam */
/** @var string|null $currentRole */
/** @var bool|null $isPlatformOwner */
/** @var string $csrf */

$roleLabels = [
    'owner' => 'Owner',
    'manager' => 'Manager',
    'operator' => 'Operator',
    'viewer' => 'Viewer',
];
?>

<div class="heading">
    <div>
        <h1>Router team</h1>
        <?php if ($router): ?>
            <p class="muted">
                <?= e($router['name']) ?>
                <span class="code"><?= e($router['identity']) ?></span>
            </p>
        <?php endif; ?>
    </div>

    <div class="actions">
        <?php if ($canManageTeam && $router): ?>
            <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#add-team-member-modal">
                Add team member
            </button>
        <?php endif; ?>
        <a class="btn btn-outline-secondary" href="/admin/routers">Back to routers</a>
    </div>
</div>

<?= $message ?>

<?php if ($router): ?>
    <?php if ($canManageTeam): ?>
        <div class="modal fade" id="add-team-member-modal" tabindex="-1" aria-labelledby="add-team-member-modal-label" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content">
                    <form method="post">
                        <div class="modal-header">
                            <h2 class="modal-title fs-5" id="add-team-member-modal-label">Add or update team member</h2>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>

                        <div class="modal-body">
                            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                            <input type="hidden" name="action" value="save">

                            <div class="mb-3">
                                <label class="form-label" for="team-email">Account email</label>
                                <input
                                    class="form-control"
                                    id="team-email"
                                    type="email"
                                    name="email"
                                    autocomplete="off"
                                    required
                                >
                                <div class="form-text">The person must already have a PixiePoint account.</div>
                            </div>

                            <div>
                                <label class="form-label" for="team-role">Role</label>
                                <select class="form-select" id="team-role" name="role" required>
                                    <?php if (($isPlatformOwner ?? false) || $currentRole === 'owner'): ?>
                                        <option value="owner">Owner</option>
                                    <?php endif; ?>
                                    <option value="manager">Manager</option>
                                    <option value="operator" selected>Operator</option>
                                    <option value="viewer">Viewer</option>
                                </select>
                                <div class="form-text">Managers manage staff, operators handle daily operations, viewers are read-only.</div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button class="btn btn-secondary" type="button" data-bs-dismiss="modal">Cancel</button>
                            <button class="btn btn-primary" type="submit">Save member</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <section class="panel">
        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
            <h2 class="mb-0">Team members</h2>
            <span class="text-body-secondary small"><?= e(count($members)) ?> member<?= count($members) === 1 ? '' : 's' ?></span>
        </div>

        <div class="table-responsive">
            <table class="table align-middle">
                <thead>
                    <tr>
                        <th>Member</th>
                        <th>Role</th>
                        <th>Added</th>
                        <?php if ($canManageTeam): ?>
                            <th class="text-end">Action</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$members): ?>
                        <tr>
                            <td colspan="<?= $canManageTeam ? 4 : 3 ?>" class="empty">
                                No team members assigned.
                            </td>
                        </tr>
                    <?php endif; ?>

                    <?php foreach ($members as $member): ?>
                        <?php
                        $memberRole = (string) $member['role'];
                        $canChangeMember = $canManageTeam
                            && (
                                ($isPlatformOwner ?? false)
                                || $currentRole === 'owner'
                                || $memberRole !== 'owner'
                            );
                        ?>
                        <tr>
                            <td>
                                <strong><?= e($member['name'] ?: $member['email']) ?></strong>
                                <?php if (!empty($member['name'])): ?>
                                    <div class="small text-body-secondary">
                                        <?= e($member['email']) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td><?= e($roleLabels[$memberRole] ?? ucfirst($memberRole)) ?></td>
                            <td><?= e($member['created_at']) ?></td>

                            <?php if ($canManageTeam): ?>
                                <td class="text-end">
                                    <?php if ($canChangeMember): ?>
                                        <form method="post" class="d-inline">
                                            <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                            <input type="hidden" name="action" value="remove">
                                            <input
                                                type="hidden"
                                                name="user_id"
                                                value="<?= e($member['user_id']) ?>"
                                            >
                                            <button
                                                class="btn btn-sm btn-outline-danger"
                                                type="submit"
                                                onclick="return confirm('Remove this team member?')"
                                            >
                                                Remove
                                            </button>
                                        </form>
                                    <?php endif; ?>
                                </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>
