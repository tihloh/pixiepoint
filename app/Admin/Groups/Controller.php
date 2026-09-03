<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Groups;

use PixiePoint\App\Admin\Shared\FeatureController;
use RuntimeException;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
use Tihloh\Prefab\Users\Services\UserManager;

final class Controller extends FeatureController
{
    public function __construct(
        \PDO $db,
        \PixiePoint\App\Services\AuthContext $auth,
        \PixiePoint\App\Services\View $view,
        \Tihloh\Prefab\Logs\Services\LogManager $logs,
        private UserManager $users,
        private PermissionManager $permissions,
    ) {
        parent::__construct($db, $auth, $view, $logs);
    }

    public function index(): never
    {
        $this->auth->requireAccount();
        $groups = $this->users->groups();
        $message = $this->flash();

        if ($this->isPost()) {
            require_csrf();

            try {
                $action = (string) ($_POST['action'] ?? '');

                if ($action === 'create') {
                    $name = trim((string) ($_POST['name'] ?? ''));
                    if ($name === '') {
                        throw new RuntimeException('Enter a group name.');
                    }

                    $groups->create(
                        $name,
                        trim((string) ($_POST['description'] ?? '')) ?: null,
                    );
                    $_SESSION['admin_flash'] = '<div class="alert ok">Group added.</div>';
                } elseif ($action === 'delete') {
                    $groupId = max(0, (int) ($_POST['id'] ?? 0));
                    if (!$groups->find($groupId)) {
                        throw new RuntimeException('Group not found.');
                    }
                    $this->permissions->clearAll('group', $groupId);
                    $groups->delete($groupId);
                    $_SESSION['admin_flash'] = '<div class="alert ok">Group deleted.</div>';
                }
            } catch (\Throwable $e) {
                $_SESSION['admin_flash'] = '<div class="alert">' . e($e->getMessage()) . '</div>';
            }

            redirect('/admin/groups');
        }

        $this->page('Groups', __DIR__ . '/views/index.php', [
            'groups' => $groups->all(),
            'message' => $message,
            'csrf' => csrf_token(),
        ]);
    }

    public function edit(string $id): never
    {
        $this->auth->requireAccount();
        $groups = $this->users->groups();
        $groupId = max(0, (int) $id);
        $group = $groups->find($groupId);

        if (!$group) {
            http_response_code(404);
            exit('Group not found.');
        }

        $message = $this->flash();

        if ($this->isPost()) {
            require_csrf();

            try {
                $name = trim((string) ($_POST['name'] ?? ''));
                if ($name === '') {
                    throw new RuntimeException('Enter a group name.');
                }

                $groups->update($groupId, [
                    'name' => $name,
                    'description' => trim((string) ($_POST['description'] ?? '')) ?: null,
                ]);

                $submitted = (array) ($_POST['permissions'] ?? []);
                foreach ($this->permissions->definitions() as $permission => $_definition) {
                    $value = (string) ($submitted[$permission] ?? 'inherit');

                    if ($value === 'allow') {
                        $this->permissions->set('group', $groupId, $permission, true, $this->context());
                    } elseif ($value === 'deny') {
                        $this->permissions->set('group', $groupId, $permission, false, $this->context());
                    } else {
                        $this->permissions->clear('group', $groupId, $permission, $this->context());
                    }
                }

                $_SESSION['admin_flash'] = '<div class="alert ok">Group saved.</div>';
            } catch (\Throwable $e) {
                $_SESSION['admin_flash'] = '<div class="alert">' . e($e->getMessage()) . '</div>';
            }

            redirect('/admin/groups/' . $groupId . '/edit');
        }

        $this->page('Edit group', __DIR__ . '/views/edit.php', [
            'group' => $group,
            'definitions' => $this->permissions->definitions(),
            'overrides' => $this->permissions->overridesFor('group', $groupId),
            'message' => $message,
            'csrf' => csrf_token(),
        ]);
    }

    private function flash(): string
    {
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);
        return $message;
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
