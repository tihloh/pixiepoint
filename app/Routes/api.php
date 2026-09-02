<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    // Lightweight service health endpoint used by the hotspot integration.
    $routes
        ->get('/hotspot/health', [$c['api'], 'health'])
        ->name('api.health');

    // Legacy query-style Router Agent routes are kept for compatibility.
    $routes
        ->get('/api/router/install', [$c['router.agent'], 'install'])
        ->name('api.router.install');

    $routes
        ->get('/api/router/poll', [$c['router.agent'], 'poll'])
        ->name('api.router.poll');

    $routes
        ->get('/api/router/ack', [$c['router.agent'], 'ack'])
        ->name('api.router.ack');

    // MikroTik accounting and login-event ingestion.
    $routes
        ->post('/api/accounting', [$c['api'], 'accounting'])
        ->name('api.accounting');

    $routes
        ->post('/api/router/login-event', [$c['api'], 'loginEvent'])
        ->name('api.router.login-event');
};
