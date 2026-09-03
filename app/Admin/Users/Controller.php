<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Users;

use PixiePoint\App\Admin\Shared\FeatureController;
use RuntimeException;
use Tihloh\Prefab\Users\Services\UserManager;

final class Controller extends FeatureController
{
    public function __construct(
        \PDO $db,
        \PixiePoint\App\Services\AuthContext $auth,
        \PixiePoint\App\Services\View $view,
        \Tihloh\Prefab\Logs\Services\LogManager $logs,
        private UserManager $users,
    ) {
        parent::__construct($db, $auth, $view, $logs);
    }

    public function index(): never
    {
        $this->auth->requireAccount();
        $canManage = $this->auth->can('users.manage');
        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        if ($this->isPost()) {
            require_csrf();
            if (!$canManage) {
                throw new RuntimeException('You cannot create users.');
            }

            try {
                $name = trim((string) ($_POST['name'] ?? ''));
                $email = strtolower(trim((string) ($_POST['email'] ?? '')));
                if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new RuntimeException('Enter a name and valid email address.');
                }
                if ($this->users->findByEmail($email)) {
                    throw new RuntimeException('That email address is already in use.');
                }

                $this->users->create([
                    'name' => $name,
                    'email' => $email,
                    'active' => true,
                    'platform_role' => 'member',
                    'account_api_key' => strtolower(bin2hex(random_bytes(24))),
                ], $this->context());
                $_SESSION['admin_flash'] = '<div class="alert ok">User added.</div>';
            } catch (\Throwable $e) {
                $_SESSION['admin_flash'] = '<div class="alert">' . e($e->getMessage()) . '</div>';
            }

            redirect('/admin/users');
        }

        $this->page('Users', __DIR__ . '/views/index.php', [
            'users' => array_map(static fn ($user) => $user->toArray(), $this->users->all(500)),
            'canManage' => $canManage,
            'canManageGroups' => $this->auth->can('groups.manage'),
            'message' => $message,
            'csrf' => csrf_token(),
        ]);
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
