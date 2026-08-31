<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes->matchMethods(['GET', 'POST'], '/', [$c['hotspot'], 'portal'])->name('hotspot.portal');
    $routes->post('/hotspot/authenticate', [$c['hotspot'], 'authenticate'])->name('hotspot.authenticate');
    $routes->post('/hotspot/session', [$c['hotspot'], 'session'])->name('hotspot.session');
    $routes->post('/hotspot/disconnected', [$c['hotspot'], 'disconnected'])->name('hotspot.disconnected');

    $routes->matchMethods(['GET', 'POST'], '/setup', [$c['auth'], 'setup'])->name('setup');
    $routes->matchMethods(['GET', 'POST'], '/register', [$c['auth'], 'register'])->name('register');
    $routes->matchMethods(['GET', 'POST'], '/login', [$c['auth'], 'login'])->name('login');
    $routes->get('/logout', [$c['auth'], 'logout'])->name('logout')->auth()->middleware('prefab.access');
    $routes->get('/auth/google', [$c['auth'], 'googleStart'])->name('auth.google');
    $routes->get('/auth/google/callback', [$c['auth'], 'googleCallback'])->name('auth.google.callback');

    $routes->redirect('/admin/login', '/login');
    $routes->redirect('/admin/logout', '/logout');
    $routes->redirect('/admin', '/dashboard');

    $routes->get('/dashboard', [$c['dashboard'], 'index'])
        ->name('dashboard')
        ->auth()
        ->middleware('prefab.access');

    $routes->get('/admin/routers', [$c['admin'], 'routers'])
        ->name('admin.routers.index')
        ->auth()->permission('routers.view')->middleware('prefab.access');
    $routes->post('/admin/routers', [$c['admin'], 'routers'])
        ->name('admin.routers.store')
        ->auth()->permission('routers.manage')->middleware('prefab.access');

    $routes->get('/admin/vouchers', [$c['admin'], 'vouchers'])
        ->name('admin.vouchers.index')
        ->auth()->permission('vouchers.view')->middleware('prefab.access');
    $routes->post('/admin/vouchers', [$c['admin'], 'vouchers'])
        ->name('admin.vouchers.store')
        ->auth()->permission('vouchers.manage')->middleware('prefab.access');

    $routes->get('/admin/devices', [$c['admin'], 'devices'])
        ->name('admin.devices.index')
        ->auth()->permission('devices.view')->middleware('prefab.access');
    $routes->get('/admin/sessions', [$c['admin'], 'sessions'])
        ->name('admin.sessions.index')
        ->auth()->permission('sessions.view')->middleware('prefab.access');
    $routes->get('/admin/logs', [$c['admin'], 'logs'])
        ->name('admin.logs.index')
        ->auth()->permission('logs.view')->middleware('prefab.access');
};
