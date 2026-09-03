<?php
/** @var array $user */
/** @var array $groups */
/** @var array $groupIds */
/** @var array $definitions */
/** @var array $resolved */
/** @var array $overrides */
/** @var array $inheritedFrom */
/** @var bool $canGroups */
/** @var bool $isPlatformOwner */
/** @var string $message */
/** @var string $csrf */
?>

<div class="row justify-content-center">
    <div class="col-xl-9 col-lg-10">
        <section class="panel p-0 overflow-hidden">
            <div class="d-flex justify-content-between align-items-start px-4 py-3 border-bottom">
                <div>
                    <h1 class="h4 mb-1"><?= e($user['name']) ?></h1>
                    <div class="small text-body-secondary"><?= e($user['email']) ?></div>
                </div>
                <a class="btn btn-sm btn-outline-secondary" href="/admin/users">Back</a>
            </div>

            <form method="post" class="p-4">
                <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">

                <?= $message ?>

                <section class="mb-4">
                    <h2 class="h6 mb-3">Groups</h2>
                    <div class="row g-2">
                        <?php if (!$groups): ?>
                            <div class="text-body-secondary small">No groups available.</div>
                        <?php endif; ?>

                        <?php foreach ($groups as $group): ?>
                            <div class="col-md-4 col-sm-6">
                                <label class="border rounded p-3 d-flex align-items-center gap-2 w-100">
                                    <input
                                        class="form-check-input mt-0"
                                        type="checkbox"
                                        name="groups[]"
                                        value="<?= e($group->id) ?>"
                                        <?= in_array((int) $group->id, $groupIds, true) ? 'checked' : '' ?>
                                        <?= $canGroups ? '' : 'disabled' ?>
                                    >
                                    <span><?= e($group->name) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>

                <hr class="my-4">

                <section>
                    <div class="mb-3">
                        <h2 class="h6 mb-1">Permissions</h2>
                        <?php if ($isPlatformOwner): ?>
                            <div class="small text-body-secondary">Platform Owner always has full access.</div>
                        <?php else: ?>
                            <div class="small text-body-secondary">User settings override inherited group permissions.</div>
                        <?php endif; ?>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Permission</th>
                                    <th style="width:180px">User Setting</th>
                                    <th style="width:180px">Inherited From</th>
                                    <th style="width:110px">Effective</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($definitions as $permission => $definition): ?>
                                    <?php
                                    $result = $resolved[$permission] ?? null;
                                    $override = array_key_exists($permission, $overrides)
                                        ? ($overrides[$permission] ? 'allow' : 'deny')
                                        : 'inherit';
                                    $allowed = $isPlatformOwner || ($result?->allowed ?? false);
                                    ?>
                                    <tr>
                                        <td>
                                            <strong><?= e($definition['name'] ?? $permission) ?></strong>
                                            <?php if (!empty($definition['description'])): ?>
                                                <div class="small text-body-secondary"><?= e($definition['description']) ?></div>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($isPlatformOwner): ?>
                                                <span class="text-body-secondary">Fixed</span>
                                            <?php else: ?>
                                                <select class="form-select form-select-sm" name="permissions[<?= e($permission) ?>]">
                                                    <option value="inherit" <?= $override === 'inherit' ? 'selected' : '' ?>>Inherit</option>
                                                    <option value="allow" <?= $override === 'allow' ? 'selected' : '' ?>>Allow</option>
                                                    <option value="deny" <?= $override === 'deny' ? 'selected' : '' ?>>Deny</option>
                                                </select>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="small text-body-secondary">
                                                <?= e($isPlatformOwner ? 'Platform Owner' : ($inheritedFrom[$permission] ?? 'Default')) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge <?= $allowed ? '' : 'off' ?>">
                                                <?= $allowed ? 'Allow' : 'Deny' ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </section>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a class="btn btn-outline-secondary" href="/admin/users">Cancel</a>
                    <button class="button" type="submit">Save Permissions</button>
                </div>
            </form>
        </section>
    </div>
</div>
