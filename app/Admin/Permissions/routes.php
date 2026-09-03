<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes
        ->matchMethods(
            ['GET', 'POST'],
            '/admin/users/{id}/permissions',
            [$c['admin.permissions'], 'index'],
        )
        ->name('admin.users.permissions')
        ->auth()
        ->permission('permissions.manage')
        ->middleware('prefab.access');
};
