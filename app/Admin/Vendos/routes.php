<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    // Admin routes for viewing and maintaining vendo/controller mappings.
    $routes
        ->get('/admin/vendos', [$c['admin.vendos'], 'index'])
        ->name('admin.vendos.index')
        ->auth()
        ->permission('vendos.view')
        ->middleware('prefab.access');

    $routes
        ->post('/admin/vendos', [$c['admin.vendos'], 'index'])
        ->name('admin.vendos.store')
        ->auth()
        ->permission('vendos.manage')
        ->middleware('prefab.access');

    // Public feature-owned endpoint used by the hosted hotspot portal.
    $routes
        ->get('/hotspot/vendos', [$c['vendos.hotspot'], 'index'])
        ->name('hotspot.vendos');
};
