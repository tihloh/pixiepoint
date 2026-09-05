<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    // -------------------------------------------------------------------------
    // Router administration
    // -------------------------------------------------------------------------

    $routes
        ->get('/admin/routers', [$c['admin.routers'], 'index'])
        ->name('admin.routers.index')
        ->auth()
        ->permission('routers.view')
        ->middleware('prefab.access');

    $routes
        ->post('/admin/routers', [$c['admin.routers'], 'index'])
        ->name('admin.routers.store')
        ->auth()
        ->permission('routers.manage')
        ->middleware('prefab.access');

    $routes
        ->get('/admin/routers/{id}/settings', [$c['admin.routers'], 'settings'])
        ->name('admin.routers.settings')
        ->auth()
        ->permission('routers.manage')
        ->middleware('prefab.access');

    $routes
        ->post('/admin/routers/{id}/settings', [$c['admin.routers'], 'settings'])
        ->name('admin.routers.settings.save')
        ->auth()
        ->permission('routers.manage')
        ->middleware('prefab.access');

    $routes
        ->get('/admin/routers/{id}', [$c['admin.routers'], 'dashboard'])
        ->name('admin.routers.dashboard')
        ->auth()
        ->permission('routers.view')
        ->middleware('prefab.access');

    $routes
        ->get('/admin/routers/{id}/team', [$c['admin.router-team'], 'index'])
        ->name('admin.routers.team')
        ->auth()
        ->permission('routers.view')
        ->middleware('prefab.access');

    $routes
        ->post('/admin/routers/{id}/team', [$c['admin.router-team'], 'index'])
        ->name('admin.routers.team.save')
        ->auth()
        ->permission('routers.manage')
        ->middleware('prefab.access');

    $routes
        ->post('/admin/routers/{id}/test', [$c['router.agent'], 'test'])
        ->name('admin.routers.test')
        ->auth()
        ->permission('routers.manage')
        ->middleware('prefab.access');

    // -------------------------------------------------------------------------
    // MikroTik registration and Router Agent API
    // -------------------------------------------------------------------------

    // Called directly by the RouterOS terminal command. The account API key is
    // a path parameter so RouterOS never needs a query-string URL.
    $routes
        ->get('/api/router/register/{key}', [$c['router.registration'], 'register'])
        ->name('api.router.register');

    $routes
        ->get('/api/router/install/{token}', [$c['router.agent'], 'install'])
        ->name('api.router.install');

    $routes
        ->get('/api/router/poll/{token}', [$c['router.agent'], 'poll'])
        ->name('api.router.poll.path');

    $routes
        ->get('/api/router/ack/{token}/{id}/{status}', [$c['router.agent'], 'ack'])
        ->name('api.router.ack.path');
};
