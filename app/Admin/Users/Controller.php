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
        private AvatarService $avatars,
    ) {
        parent::__construct($db, $auth, $view, $logs);
    }

    public function index(): never
    {
        $this->auth->requireAccount();
        $canManage = $this->auth->can('users.manage');
        $message = $this->flash();

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
            'canManagePermissions' => $this->auth->can('permissions.manage'),
            'canManageGroups' => $this->auth->can('groups.manage'),
            'message' => $message,
            'csrf' => csrf_token(),
        ]);
    }

    public function edit(string $id): never
    {
        $this->auth->requireAccount();
        if (!$this->auth->can('users.manage')) {
            http_response_code(403);
            exit('Access denied.');
        }

        $userId = max(0, (int) $id);
        $target = $this->users->find($userId);
        if (!$target) {
            http_response_code(404);
            exit('User not found.');
        }

        $message = $this->flash();

        if ($this->isPost()) {
            require_csrf();

            try {
                $action = (string) ($_POST['action'] ?? 'save');
                if ($action === 'avatar') {
                    $avatarUrl = $this->avatars->store($userId, (string) ($_POST['avatar_data'] ?? ''));
                    $this->users->update($userId, ['avatar_url' => $avatarUrl], $this->context());
                } else {
                    $current = $target->toArray();
                    $name = trim((string) ($_POST['name'] ?? ''));
                    $email = strtolower(trim((string) ($_POST['email'] ?? '')));

                    if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        throw new RuntimeException('Enter a name and valid email address.');
                    }

                    $existing = $this->users->findByEmail($email);
                    if ($existing && (int) $existing->id !== $userId) {
                        throw new RuntimeException('That email address is already in use.');
                    }

                    $data = [
                        'name' => $name,
                        'email' => $email,
                        'active' => isset($_POST['active']),
                    ];

                    if (($current['platform_role'] ?? '') !== 'platform_owner') {
                        $role = (string) ($_POST['platform_role'] ?? 'member');
                        if (!in_array($role, ['member', 'pisowifi_owner'], true)) {
                            throw new RuntimeException('Invalid platform role.');
                        }
                        $data['platform_role'] = $role;
                    }

                    $this->users->update($userId, $data, $this->context());
                }

                $_SESSION['admin_flash'] = '<div class="alert ok">User updated.</div>';
            } catch (\Throwable $e) {
                $_SESSION['admin_flash'] = '<div class="alert">' . e($e->getMessage()) . '</div>';
            }

            redirect('/admin/users/' . $userId . '/edit');
        }

        $this->page('Edit user', __DIR__ . '/views/edit.php', [
            'user' => $this->users->find($userId)?->toArray() ?? [],
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
