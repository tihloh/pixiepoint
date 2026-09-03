<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Permissions;

use PixiePoint\App\Admin\Shared\FeatureController;
use RuntimeException;
use Tihloh\Prefab\Permissions\Services\PermissionManager;

final class Controller extends FeatureController
{
    public function __construct(
        \PDO $db,
        \PixiePoint\App\Services\AuthContext $auth,
        \PixiePoint\App\Services\View $view,
        \Tihloh\Prefab\Logs\Services\LogManager $logs,
        private PermissionManager $permissions,
    ) {
        parent::__construct($db, $auth, $view, $logs);
    }

    public function users(): never
    {
        $this->auth->requireAccount();

        $users = $this->db
            ->query(
                'SELECT id,name,email,active,platform_role '
                . 'FROM users ORDER BY name,email,id',
            )
            ->fetchAll();

        $this->page('Permissions', __DIR__ . '/views/users.php', [
            'users' => $users,
        ]);
    }

    public function index(string $id): never
    {
        $this->auth->requireAccount();
        $userId = max(0, (int) $id);
        $user = $this->findUser($userId);

        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        if ($this->isPost()) {
            require_csrf();

            $permission = trim((string) ($_POST['permission'] ?? ''));
            $value = (string) ($_POST['value'] ?? 'inherit');

            try {
                if (($user['platform_role'] ?? '') === 'platform_owner') {
                    throw new RuntimeException('Platform owner always has full access.');
                }

                if (!$this->permissions->defined($permission)) {
                    throw new RuntimeException('Unknown permission.');
                }

                $context = [
                    'actor_id' => $this->auth->auth()->id(),
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ];

                if ($value === 'allow') {
                    $this->permissions->set('user', $userId, $permission, true, $context);
                } elseif ($value === 'deny') {
                    $this->permissions->set('user', $userId, $permission, false, $context);
                } elseif ($value === 'inherit') {
                    $this->permissions->clear('user', $userId, $permission, $context);
                } else {
                    throw new RuntimeException('Invalid permission value.');
                }

                $_SESSION['admin_flash'] = '<div class="alert ok">Permission updated.</div>';
            } catch (\Throwable $e) {
                $_SESSION['admin_flash'] = '<div class="alert">' . e($e->getMessage()) . '</div>';
            }

            redirect('/admin/permissions/' . $userId);
        }

        $groupIds = $this->usersGroupIds($userId);
        $resolved = $this->permissions->resolvedFor($userId, $groupIds);
        $overrides = $this->permissions->overridesFor('user', $userId);

        $this->page('Permissions', __DIR__ . '/views/index.php', [
            'user' => $user,
            'definitions' => $this->permissions->definitions(),
            'resolved' => $resolved,
            'overrides' => $overrides,
            'message' => $message,
            'csrf' => csrf_token(),
        ]);
    }

    private function findUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id,name,email,active,platform_role FROM users WHERE id=? LIMIT 1',
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch();

        if (!$user) {
            http_response_code(404);
            $this->page('User not found', __DIR__ . '/views/not-found.php');
        }

        return $user;
    }

    private function usersGroupIds(int $userId): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT group_id FROM prefab_user_groups WHERE user_id=? ORDER BY group_id',
            );
            $stmt->execute([$userId]);

            return array_map('intval', $stmt->fetchAll(\PDO::FETCH_COLUMN));
        } catch (\Throwable) {
            return [];
        }
    }
}
