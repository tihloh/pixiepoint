<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes
        ->matchMethods(['GET', 'POST'], '/admin/users', [$c['admin.users'], 'index'])
        ->name('admin.users.index')
        ->auth()
        ->permission('users.view')
        ->middleware('prefab.access');
};
