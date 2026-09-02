<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes
        ->get('/admin/sessions', [$c['admin.sessions'], 'index'])
        ->name('admin.sessions.index')
        ->auth()
        ->permission('sessions.view')
        ->middleware('prefab.access');
};
