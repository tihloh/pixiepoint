<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes
        ->get('/admin/permissions', [$c['admin.permissions'], 'users'])
        ->name('admin.permissions.index')
        ->auth()
        ->permission('permissions.manage')
        ->middleware('prefab.access');

    $routes
        ->matchMethods(['GET', 'POST'], '/admin/permissions/{id}', [$c['admin.permissions'], 'index'])
        ->name('admin.permissions.user')
        ->auth()
        ->permission('permissions.manage')
        ->middleware('prefab.access');
};
