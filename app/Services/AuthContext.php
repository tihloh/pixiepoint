<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

use PDO;
use PixiePoint\App\Models\PermissionUser;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
use Tihloh\Prefab\Users\Services\UserManager;

final class AuthContext
{
    private const ROUTER_ROLE_PERMISSIONS = [
        'owner' => ['routers.view','routers.manage','vendos.view','vendos.manage','vouchers.view','vouchers.manage','devices.view','sessions.view','sales.view','logs.view'],
        'manager' => ['routers.view','routers.manage','vendos.view','vendos.manage','vouchers.view','vouchers.manage','devices.view','sessions.view','sales.view','logs.view'],
        'operator' => ['routers.view','routers.manage','vendos.view','vendos.manage','vouchers.view','vouchers.manage','devices.view','sessions.view','sales.view','logs.view'],
        'viewer' => ['routers.view','vendos.view','vouchers.view','devices.view','sessions.view','sales.view','logs.view'],
    ];

    public function __construct(
        private UserManager $users,
        private AuthManager $auth,
        private PermissionManager $permissions,
        private PDO $db,
    ) {
    }

    public function auth(): AuthManager { return $this->auth; }

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
        if (($user['platform_role'] ?? '') === 'platform_owner') return true;
        $userId = (int) $user['id'];
        if (in_array($permission, ['routers.view','routers.manage'], true)) {
            return $this->routerTeamCan($userId, $permission);
        }
        $groupIds = $this->users->groups()->groupIdsForUser($userId);
        if ($this->permissions->can(new PermissionUser($userId, $groupIds), $permission)) return true;
        return $this->routerTeamCan($userId, $permission);
    }

    public function requirePermission(string $permission, View $view): array
    {
        $user = $this->requireAccount();
        if (!$this->can($permission)) {
            http_response_code(403);
            $view->page('Access denied', $view->portalCard(
                '<h1>Access denied</h1>'
                . '<p class="muted">Your account does not have <span class="code">'
                . e($permission) . '</span>.</p>'
                . '<a class="button full" href="/dashboard">Back to dashboard</a>',
            ));
        }
        return $user;
    }

    /** @return array<string,bool> */
    public function navigation(): array
    {
        $user = $this->user();
        $GLOBALS['pixiepoint_sidebar_user'] = $user ?? [];

        $routers = [];
        if ($user) {
            $userId = (int) ($user['id'] ?? 0);
            if (($user['platform_role'] ?? '') === 'platform_owner') {
                $routers = $this->db->query(
                    'SELECT id,name,identity FROM routers WHERE enabled=1 ORDER BY name'
                )->fetchAll();
            } else {
                $stmt = $this->db->prepare(
                    'SELECT r.id,r.name,r.identity
                     FROM routers r
                     JOIN router_members rm ON rm.router_id=r.id
                     WHERE r.enabled=1 AND rm.user_id=?
                     ORDER BY r.name'
                );
                $stmt->execute([$userId]);
                $routers = $stmt->fetchAll();
            }
        }

        $selectedId = max(0, (int) ($_SESSION['pixiepoint_selected_router_id'] ?? 0));
        $selectedRouter = null;
        foreach ($routers as $router) {
            if ((int) $router['id'] === $selectedId) {
                $selectedRouter = $router;
                break;
            }
        }
        if (!$selectedRouter) unset($_SESSION['pixiepoint_selected_router_id']);

        $GLOBALS['pixiepoint_sidebar_routers'] = $routers;
        $GLOBALS['pixiepoint_selected_router'] = $selectedRouter;

        return [
            'users' => $this->can('users.view'),
            'groups' => $this->can('groups.manage'),
            'permissions' => $this->can('permissions.manage'),
            'routers' => $this->can('routers.view') || $this->can('routers.manage'),
            'vendos' => $this->can('vendos.view') || $this->can('vendos.manage'),
            'vouchers' => $this->can('vouchers.view') || $this->can('vouchers.manage'),
            'devices' => $this->can('devices.view'),
            'sessions' => $this->can('sessions.view'),
            'sales' => $this->can('sales.view'),
            'logs' => $this->can('logs.view'),
        ];
    }

    private function routerTeamCan(int $userId, string $permission): bool
    {
        try {
            $stmt = $this->db->prepare('SELECT DISTINCT role FROM router_members WHERE user_id=?');
            $stmt->execute([$userId]);
            $roles = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (\Throwable) {
            return false;
        }
        foreach ($roles as $role) {
            if (in_array($permission, self::ROUTER_ROLE_PERMISSIONS[(string) $role] ?? [], true)) return true;
        }
        return false;
    }
}
