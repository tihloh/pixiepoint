<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    // Public PixiePoint home/login page. MikroTik still POSTs its hotspot
    // context to the same root path, so browser GET and hotspot POST coexist.
    $routes->get('/', [$c['auth'], 'home'])->name('home');
    $routes->post('/', [$c['hotspot'], 'portal'])->name('hotspot.portal');
    $routes->get('/hotspot/compat', [$c['hotspot'], 'compatibilityPortal'])->name('hotspot.compat');

    $routes->post('/hotspot/authenticate', [$c['hotspot'], 'authenticate'])->name('hotspot.authenticate');
    $routes->post('/hotspot/session', [$c['hotspot'], 'session'])->name('hotspot.session');
    $routes->post('/hotspot/disconnected', [$c['hotspot'], 'disconnected'])->name('hotspot.disconnected');

    $routes->matchMethods(['GET', 'POST'], '/setup', [$c['auth'], 'setup'])->name('setup');
    $routes->matchMethods(['GET', 'POST'], '/register', [$c['auth'], 'register'])->name('register');
    $routes->post('/login', [$c['auth'], 'login'])->name('login.submit');
    $routes->get('/login', static fn () => redirect('/'))->name('login');
    $routes->get('/logout', [$c['auth'], 'logout'])->name('logout')->auth()->middleware('prefab.access');
    $routes->get('/auth/google', [$c['auth'], 'googleStart'])->name('auth.google');
    $routes->get('/auth/google/callback', [$c['auth'], 'googleCallback'])->name('auth.google.callback');

    $routes->redirect('/admin/login', '/');
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
    $routes->get('/admin/sales', [$c['admin'], 'sales'])
        ->name('admin.sales.index')
        ->auth()->permission('sales.view')->middleware('prefab.access');
    $routes->get('/admin/logs', [$c['admin'], 'logs'])
        ->name('admin.logs.index')
        ->auth()->permission('logs.view')->middleware('prefab.access');
};
