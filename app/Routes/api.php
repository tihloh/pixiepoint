<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes->get('/hotspot/health', [$c['api'], 'health'])->name('api.health');
    $routes->get('/api/router/install', [$c['router.agent'], 'install'])->name('api.router.install');
    $routes->get('/api/router/poll', [$c['router.agent'], 'poll'])->name('api.router.poll');
    $routes->get('/api/router/ack', [$c['router.agent'], 'ack'])->name('api.router.ack');
    $routes->post('/api/accounting', [$c['api'], 'accounting'])->name('api.accounting');
    $routes->post('/api/router/login-event', [$c['api'], 'loginEvent'])->name('api.router.login-event');
};
