<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Permissions;

use PixiePoint\App\Admin\Shared\FeatureController;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
use Tihloh\Prefab\Users\Services\UserManager;

final class Controller extends FeatureController
{
    public function __construct(
        \PDO $db,
        \PixiePoint\App\Services\AuthContext $auth,
        \PixiePoint\App\Services\View $view,
        \Tihloh\Prefab\Logs\Services\LogManager $logs,
        private PermissionManager $permissions,
        private UserManager $users,
    ) {
        parent::__construct($db, $auth, $view, $logs);
    }

    public function index(string $id): never
    {
        $this->auth->requireAccount();
        if (!$this->auth->can('permissions.manage')) {
            http_response_code(403);
            exit('Access denied.');
        }

        $userId = max(0, (int) $id);
        $target = $this->users->find($userId);
        if (!$target) {
            http_response_code(404);
            exit('User not found.');
        }

        $user = $target->toArray();
        $isPlatformOwner = ($user['platform_role'] ?? '') === 'platform_owner';
        $canGroups = $this->auth->can('groups.manage');
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        if ($this->isPost()) {
            require_csrf();

            try {
                if ($canGroups) {
                    $this->users->groups()->syncUserGroups(
                        $userId,
                        array_map('intval', (array) ($_POST['groups'] ?? [])),
                    );
                }

                if (!$isPlatformOwner) {
                    $submitted = (array) ($_POST['permissions'] ?? []);
                    foreach ($this->permissions->definitions() as $permission => $_definition) {
                        $value = (string) ($submitted[$permission] ?? 'inherit');

                        if ($value === 'allow') {
                            $this->permissions->set('user', $userId, $permission, true, $this->context());
                        } elseif ($value === 'deny') {
                            $this->permissions->set('user', $userId, $permission, false, $this->context());
                        } else {
                            $this->permissions->clear('user', $userId, $permission, $this->context());
                        }
                    }
                }

                $_SESSION['admin_flash'] = '<div class="alert ok">Permissions saved.</div>';
            } catch (\Throwable $e) {
                $_SESSION['admin_flash'] = '<div class="alert">' . e($e->getMessage()) . '</div>';
            }

            redirect('/admin/users/' . $userId . '/permissions');
        }

        $groups = $this->users->groups()->all();
        $groupIds = array_map('intval', $this->users->groups()->groupIdsForUser($userId));
        $overrides = $this->permissions->overridesFor('user', $userId);

        $this->page('User permissions', __DIR__ . '/views/index.php', [
            'user' => $user,
            'groups' => $groups,
            'groupIds' => $groupIds,
            'definitions' => $this->permissions->definitions(),
            'resolved' => $this->permissions->resolvedFor($userId, $groupIds),
            'overrides' => $overrides,
            'inheritedFrom' => $this->inheritedSources($groups, $groupIds, $overrides),
            'canGroups' => $canGroups,
            'isPlatformOwner' => $isPlatformOwner,
            'message' => $message,
            'csrf' => csrf_token(),
        ]);
    }

    private function inheritedSources(array $groups, array $groupIds, array $userOverrides): array
    {
        $names = [];
        $byId = [];

        foreach ($groups as $group) {
            $byId[(int) $group->id] = $group->name;
        }

        foreach ($this->permissions->definitions() as $permission => $_definition) {
            if (array_key_exists($permission, $userOverrides)) {
                $names[$permission] = 'User';
                continue;
            }

            $sources = [];
            foreach ($groupIds as $groupId) {
                $rules = $this->permissions->overridesFor('group', $groupId);
                if (array_key_exists($permission, $rules)) {
                    $sources[] = $byId[$groupId] ?? ('Group ' . $groupId);
                }
            }

            $names[$permission] = $sources ? implode(', ', $sources) : 'Default';
        }

        return $names;
    }

    private function context(): array
    {
        return [
            'actor_id' => $this->auth->auth()->id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ];
    }
}
