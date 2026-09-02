<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes
        ->get('/admin/devices', [$c['admin.devices'], 'index'])
        ->name('admin.devices.index')
        ->auth()
        ->permission('devices.view')
        ->middleware('prefab.access');
};
