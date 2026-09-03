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
    ) { parent::__construct($db, $auth, $view, $logs); }

    public function index(?string $id = null): never
    {
        $this->auth->requireAccount();
        $groups = $this->users->groups();
        $selected = $id !== null ? $groups->find((int) $id) : null;
        $message = (string) ($_SESSION['admin_flash'] ?? ''); unset($_SESSION['admin_flash']);

        if ($this->isPost()) {
            require_csrf();
            try {
                $action = (string) ($_POST['action'] ?? '');
                if ($action === 'create') {
                    $name = trim((string) ($_POST['name'] ?? ''));
                    if ($name === '') throw new RuntimeException('Enter a group name.');
                    $group = $groups->create($name, trim((string) ($_POST['description'] ?? '')) ?: null);
                    $_SESSION['admin_flash'] = '<div class="alert ok">Group added.</div>';
                    redirect('/admin/groups/' . $group->id);
                }
                if (!$selected) throw new RuntimeException('Group not found.');
                if ($action === 'save') {
                    $name = trim((string) ($_POST['name'] ?? ''));
                    if ($name === '') throw new RuntimeException('Enter a group name.');
                    $groups->update($selected->id, ['name' => $name, 'description' => trim((string) ($_POST['description'] ?? '')) ?: null]);
                    $submitted = (array) ($_POST['permissions'] ?? []);
                    foreach ($this->permissions->definitions() as $permission => $_definition) {
                        $value = (string) ($submitted[$permission] ?? 'inherit');
                        if ($value === 'allow') $this->permissions->set('group', $selected->id, $permission, true, $this->context());
                        elseif ($value === 'deny') $this->permissions->set('group', $selected->id, $permission, false, $this->context());
                        else $this->permissions->clear('group', $selected->id, $permission, $this->context());
                    }
                    $_SESSION['admin_flash'] = '<div class="alert ok">Group saved.</div>';
                }
            } catch (\Throwable $e) { $_SESSION['admin_flash'] = '<div class="alert">' . e($e->getMessage()) . '</div>'; }
            redirect($selected ? '/admin/groups/' . $selected->id : '/admin/groups');
        }

        $this->page('Groups', __DIR__ . '/views/index.php', [
            'groups' => $groups->all(), 'selected' => $selected,
            'definitions' => $this->permissions->definitions(),
            'overrides' => $selected ? $this->permissions->overridesFor('group', $selected->id) : [],
            'message' => $message, 'csrf' => csrf_token(),
        ]);
    }

    private function context(): array { return ['actor_id' => $this->auth->auth()->id(), 'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null, 'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null]; }
}
