<?php
/** @var array $user */
/** @var array $definitions */
/** @var array $resolved */
/** @var array $overrides */
/** @var string $message */
/** @var string $csrf */

$isPlatformOwner = ($user['platform_role'] ?? '') === 'platform_owner';
?>

<div class="heading">
    <div>
        <h1><?= e($user['name']) ?></h1>
        <p class="muted"><?= e($user['email']) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="/admin/users">Back</a>
</div>

<?= $message ?>

<section class="panel mb-4">
    <div class="row g-3">
        <div class="col-md-6">
            <div class="small text-body-secondary">Role</div>
            <strong><?= e(ucwords(str_replace('_', ' ', (string) $user['platform_role']))) ?></strong>
        </div>
        <div class="col-md-6">
            <div class="small text-body-secondary">Status</div>
            <span class="badge <?= $user['active'] ? '' : 'off' ?>">
                <?= $user['active'] ? 'Active' : 'Disabled' ?>
            </span>
        </div>
    </div>
</section>

<section class="panel">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
        <h2 class="mb-0">Permissions</h2>
        <?php if ($isPlatformOwner): ?>
            <span class="badge">Full access</span>
        <?php endif; ?>
    </div>

    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Permission</th>
                    <th>Access</th>
                    <th>From</th>
                    <?php if (!$isPlatformOwner): ?>
                        <th class="text-end">Override</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($definitions as $permission => $definition): ?>
                    <?php
                    $result = $resolved[$permission] ?? null;
                    $override = array_key_exists($permission, $overrides)
                        ? ($overrides[$permission] ? 'allow' : 'deny')
                        : 'inherit';
                    ?>
                    <tr>
                        <td>
                            <strong><?= e($definition['name'] ?? $permission) ?></strong>
                            <div class="small text-body-secondary font-monospace">
                                <?= e($permission) ?>
                            </div>
                        </td>
                        <td>
                            <?php if ($isPlatformOwner): ?>
                                <span class="badge">Allowed</span>
                            <?php else: ?>
                                <span class="badge <?= ($result?->allowed ?? false) ? '' : 'off' ?>">
                                    <?= ($result?->allowed ?? false) ? 'Allowed' : 'Denied' ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td><?= $isPlatformOwner ? 'Platform owner' : e($result?->source ?? 'default') ?></td>

                        <?php if (!$isPlatformOwner): ?>
                            <td class="text-end">
                                <form method="post" class="d-inline-flex gap-2 align-items-center">
                                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="permission" value="<?= e($permission) ?>">
                                    <select
                                        class="form-select form-select-sm"
                                        name="value"
                                        aria-label="Permission override"
                                    >
                                        <option value="inherit" <?= $override === 'inherit' ? 'selected' : '' ?>>Inherit</option>
                                        <option value="allow" <?= $override === 'allow' ? 'selected' : '' ?>>Allow</option>
                                        <option value="deny" <?= $override === 'deny' ? 'selected' : '' ?>>Deny</option>
                                    </select>
                                    <button class="btn btn-sm btn-primary" type="submit">Save</button>
                                </form>
                            </td>
                        <?php endif; ?>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
