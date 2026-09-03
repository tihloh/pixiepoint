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
        <h1>Permissions</h1>
        <p class="muted"><?= e($user['name']) ?> · <?= e($user['email']) ?></p>
    </div>
    <a class="btn btn-outline-secondary" href="/admin/users">Back to users</a>
</div>

<?= $message ?>

<?php if ($isPlatformOwner): ?>
    <div class="alert alert-success">Platform owner has full platform access.</div>
<?php endif; ?>

<section class="panel">
    <div class="table-responsive">
        <table class="table align-middle mb-0">
            <thead>
                <tr>
                    <th>Permission</th>
                    <th>Current</th>
                    <th>Source</th>
                    <th class="text-end">Override</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($definitions as $permission => $definition): ?>
                    <?php
                    $result = $resolved[$permission] ?? null;
                    $allowed = $isPlatformOwner || ($result?->allowed ?? false);
                    $source = $isPlatformOwner ? 'platform owner' : ($result?->source ?? 'default');
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
                            <span class="badge <?= $allowed ? '' : 'off' ?>">
                                <?= $allowed ? 'Allowed' : 'Denied' ?>
                            </span>
                        </td>
                        <td><?= e($source) ?></td>
                        <td class="text-end">
                            <?php if ($isPlatformOwner): ?>
                                <span class="text-body-secondary">Fixed</span>
                            <?php else: ?>
                                <form method="post" class="d-inline-flex gap-2 align-items-center">
                                    <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                                    <input type="hidden" name="permission" value="<?= e($permission) ?>">
                                    <select class="form-select form-select-sm" name="value">
                                        <option value="inherit" <?= $override === 'inherit' ? 'selected' : '' ?>>Inherit</option>
                                        <option value="allow" <?= $override === 'allow' ? 'selected' : '' ?>>Allow</option>
                                        <option value="deny" <?= $override === 'deny' ? 'selected' : '' ?>>Deny</option>
                                    </select>
                                    <button class="btn btn-sm btn-primary" type="submit">Save</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
