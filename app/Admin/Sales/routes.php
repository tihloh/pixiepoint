<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes
        ->get('/admin/sales', [$c['admin.sales'], 'index'])
        ->name('admin.sales.index')
        ->auth()
        ->permission('sales.view')
        ->middleware('prefab.access');
};
