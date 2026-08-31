<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes->get('/hotspot/health', [$c['api'], 'health'])->name('api.health');
    $routes->post('/api/accounting', [$c['api'], 'accounting'])->name('api.accounting');
    $routes->post('/api/router/login-event', [$c['api'], 'loginEvent'])->name('api.router.login-event');
};
