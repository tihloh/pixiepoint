<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes
        ->get('/admin/logs', [$c['admin.logs'], 'index'])
        ->name('admin.logs.index')
        ->auth()
        ->permission('logs.view')
        ->middleware('prefab.access');
};
