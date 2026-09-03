<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes
        ->matchMethods(['GET', 'POST'], '/profile', [$c['admin.permissions'], 'profile'])
        ->name('profile')
        ->auth()
        ->middleware('prefab.access');

    $routes
        ->matchMethods(['GET', 'POST'], '/admin/users/{id}', [$c['admin.permissions'], 'index'])
        ->name('admin.users.manage')
        ->auth()
        ->middleware('prefab.access');
};
