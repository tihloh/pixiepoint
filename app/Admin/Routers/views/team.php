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

    <a class="btn btn-outline-secondary" href="/admin/routers">Back to routers</a>
</div>

<?= $message ?>

<?php if ($router): ?>
    <?php if ($canManageTeam): ?>
        <section class="panel mb-4">
            <h2>Add or update team member</h2>

            <form method="post" class="form-grid">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                <input type="hidden" name="action" value="save">

                <div class="field">
                    <label for="team-email">Account email</label>
                    <input
                        id="team-email"
                        type="email"
                        name="email"
                        autocomplete="off"
                        required
                    >
                    <small class="text-body-secondary">
                        The person must already have a PixiePoint account.
                    </small>
                </div>

                <div class="field">
                    <label for="team-role">Role</label>
                    <select id="team-role" name="role" required>
                        <?php if (($isPlatformOwner ?? false) || $currentRole === 'owner'): ?>
                            <option value="owner">Owner</option>
                        <?php endif; ?>
                        <option value="manager">Manager</option>
                        <option value="operator" selected>Operator</option>
                        <option value="viewer">Viewer</option>
                    </select>
                    <small class="text-body-secondary">
                        Managers manage staff, operators handle daily operations, viewers are read-only.
                    </small>
                </div>

                <div class="field align-self-end">
                    <button class="button" type="submit">Save member</button>
                </div>
            </form>
        </section>
    <?php endif; ?>

    <section class="panel">
        <h2>Team members</h2>

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
