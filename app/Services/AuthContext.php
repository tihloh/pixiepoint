<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

use PixiePoint\App\Models\PermissionUser;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
use Tihloh\Prefab\Users\Services\UserManager;

final class AuthContext
{
    public function __construct(
        private UserManager $users,
        private AuthManager $auth,
        private PermissionManager $permissions,
    ) {}

    public function auth(): AuthManager
    {
        return $this->auth;
    }

    public function user(): ?array
    {
        $id = $this->auth->id();
        if ($id === null) return null;
        $user = $this->users->find($id);
        return $user && $user->active ? $user->toArray() : null;
    }

    public function requireAccount(): array
    {
        if (!$this->auth->check()) redirect('/login');
        $user = $this->user();
        if (!$user) redirect('/logout');
        return $user;
    }

    public function isPlatformOwner(): bool
    {
        return ($this->user()['platform_role'] ?? '') === 'platform_owner';
    }

    public function can(string $permission): bool
    {
        $user = $this->user();
        if (!$user) return false;

        // The owner bypass is intentional: platform ownership is a separate
        // security boundary, while all delegated access uses Prefab Permissions.
        if (($user['platform_role'] ?? '') === 'platform_owner') return true;

        $groupIds = $this->users->groups()->groupIdsForUser($user['id']);
        return $this->permissions->can(new PermissionUser($user['id'], $groupIds), $permission);
    }

    public function requirePermission(string $permission, View $view): array
    {
        $user = $this->requireAccount();
        if (!$this->can($permission)) {
            http_response_code(403);
            $view->page(
                'Access denied',
                $view->portalCard('<h1>Access denied</h1><p class="muted">Your account does not have <span class="code">' . e($permission) . '</span>.</p><a class="button full" href="/dashboard">Back to dashboard</a>'),
            );
        }
        return $user;
    }

    /** @return array<string,bool> */
    public function navigation(): array
    {
        return [
            'routers' => $this->can('routers.view') || $this->can('routers.manage'),
            'vouchers' => $this->can('vouchers.view') || $this->can('vouchers.manage'),
            'devices' => $this->can('devices.view'),
            'sessions' => $this->can('sessions.view'),
            'logs' => $this->can('logs.view'),
        ];
    }
}
