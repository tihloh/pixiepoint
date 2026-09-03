<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes
        ->matchMethods(['GET', 'POST'], '/admin/groups', [$c['admin.groups'], 'index'])
        ->name('admin.groups.index')
        ->auth()
        ->permission('groups.manage')
        ->middleware('prefab.access');

    $routes
        ->matchMethods(['GET', 'POST'], '/admin/groups/{id}/edit', [$c['admin.groups'], 'edit'])
        ->name('admin.groups.edit')
        ->auth()
        ->permission('groups.manage')
        ->middleware('prefab.access');
};
