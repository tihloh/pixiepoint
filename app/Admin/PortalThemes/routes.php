<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes
        ->get('/admin/portal-themes', [$c['admin.portal-themes'], 'index'])
        ->name('admin.portal-themes.index')
        ->auth()
        ->permission('routers.view')
        ->middleware('prefab.access');
};
