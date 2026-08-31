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
            table: 'admins',
            id: 'id',
            name: 'name',
            email: 'email',
            active: null,
            attributes: ['password_hash' => 'password_hash'],
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
