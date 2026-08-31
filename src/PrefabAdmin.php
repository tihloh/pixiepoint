<?php

declare(strict_types=1);

namespace PixiePoint;

use PDO;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Users\Mapping\UserMap;
use Tihloh\Prefab\Users\Repositories\PdoUserProvider;
use Tihloh\Prefab\Users\Services\UserManager;

final class PrefabAdmin
{
    /** @return array{users: UserManager, auth: AuthManager} */
    public static function boot(PDO $db): array
    {
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
            ],
            allowCreate: false,
            allowUpdate: false,
            allowDelete: false,
        );

        $provider = new PdoUserProvider($db, $map, new AdminUserFactory());
        $users = new UserManager($provider);
        $users->prefabConfigure();

        $auth = new AuthManager();
        $auth->prefabConfigure();

        return ['users' => $users, 'auth' => $auth];
    }
}
