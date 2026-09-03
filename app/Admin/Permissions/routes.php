<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes
        ->matchMethods(['GET', 'POST'], '/admin/permissions/{id}', [$c['admin.permissions'], 'index'])
        ->name('admin.permissions.user')
        ->auth()
        ->permission('permissions.manage')
        ->middleware('prefab.access');
};
