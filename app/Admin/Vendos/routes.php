<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes
    ->get('/admin/stations', [$c['admin.vendos'], 'index'])
    ->name('admin.stations.index')
    ->auth()
    ->permission('vendos.view')
    ->middleware('prefab.access');

    $routes
    ->post('/admin/stations', [$c['admin.vendos'], 'index'])
    ->name('admin.stations.store')
    ->auth()
    ->permission('vendos.manage')
    ->middleware('prefab.access');

    // Backward-compatible URLs while the internal feature/module name remains Vendos.
    $routes->redirect('/admin/vendos', '/admin/stations');

    // Public feature-owned endpoint used by the hosted hotspot portal.
    $routes
    ->get('/hotspot/vendos', [$c['vendos.hotspot'], 'index'])
    ->name('hotspot.vendos');

    $routes->post('/hotspot/coin-session/start', [$c['vendos.hotspot'], 'coinSessionStart'])->name('hotspot.coin_session.start');
    $routes->get('/hotspot/coin-session/status', [$c['vendos.hotspot'], 'coinSessionStatus'])->name('hotspot.coin_session.status');
    $routes->post('/hotspot/coin-session/finish', [$c['vendos.hotspot'], 'coinSessionFinish'])->name('hotspot.coin_session.finish');
};
