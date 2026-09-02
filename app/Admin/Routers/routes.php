<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    // -------------------------------------------------------------------------
    // Router administration
    // -------------------------------------------------------------------------
    // These routes are used by authenticated administrators to view, register,
    // update, and test MikroTik routers from the PixiePoint admin interface.

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
        ->post('/admin/routers/{id}/test', [$c['router.agent'], 'test'])
        ->name('admin.routers.test')
        ->auth()
        ->permission('routers.manage')
        ->middleware('prefab.access');

    // -------------------------------------------------------------------------
    // MikroTik Router Agent API
    // -------------------------------------------------------------------------
    // The router authenticates with its agent token embedded in the URL.
    // install: returns the RouterOS agent script.
    // poll:    returns the next queued command, if one is waiting.
    // ack:     reports whether a delivered command completed or failed.

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
