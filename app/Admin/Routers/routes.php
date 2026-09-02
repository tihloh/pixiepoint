<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes->get('/admin/routers', [$c['admin.routers'], 'index'])->name('admin.routers.index')->auth()->permission('routers.view')->middleware('prefab.access');
    $routes->post('/admin/routers', [$c['admin.routers'], 'index'])->name('admin.routers.store')->auth()->permission('routers.manage')->middleware('prefab.access');
    $routes->get('/api/router/install', [$c['router.agent'], 'install'])->name('api.router.install');
};
