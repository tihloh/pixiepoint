<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

use PDO;
use PixiePoint\AdminUserFactory;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Logs\Services\LogManager;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\Routes\RouteManager;
use Tihloh\Prefab\Users\Mapping\UserMap;
use Tihloh\Prefab\Users\Services\UserManager;

final class PrefabKernel
{
    /**
     * @return array{
     *   users: UserManager,
     *   auth: AuthManager,
     *   permissions: PermissionManager,
     *   logs: LogManager,
     *   routes: RouteManager
     * }
     */
    public static function boot(PDO $db, string $root): array
    {
        PrefabConfig::set([
            'database' => $db,
            'modules' => [
                'permissions' => [
                    'definitions' => $root . '/config/permissions.php',
                    'table' => 'prefab_subject_permissions',
                ],
                'logs' => [
                    'table' => 'prefab_logs',
                ],
            ],
        ]);

        // Logs first so Users/Auth/Permissions can auto-discover audit logging.
        $logs = new LogManager(['database' => $db]);
        $logs->prefabConfigure();

        $map = new UserMap(
            table: 'users',
            id: 'id',
            name: 'name',
            email: 'email',
            active: 'active',
            attributes: [
                'password_hash' => 'password_hash',
                'platform_role' => 'platform_role',
                'points' => 'points',
                'google_sub' => 'google_sub',
                'avatar_url' => 'avatar_url',
            ],
            allowCreate: true,
            allowUpdate: true,
            allowDelete: false,
        );

        // Let Prefab Users own its database-backed provider configuration so its
        // native prefab_groups/prefab_user_groups provider is available too.
        $users = new UserManager([
            'database' => $db,
            'map' => $map,
            'factory' => new AdminUserFactory(),
        ]);
        $users->prefabConfigure();

        $auth = new AuthManager();
        $auth->prefabConfigure();

        $permissions = new PermissionManager([
            'database' => $db,
            'definitions' => $root . '/config/permissions.php',
            'table' => 'prefab_subject_permissions',
        ]);
        $permissions->prefabConfigure();

        $routes = new RouteManager();

        return compact('users', 'auth', 'permissions', 'logs', 'routes');
    }
}
