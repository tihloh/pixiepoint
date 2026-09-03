<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $nativeUrl = static function (mixed $value): ?string {
        $url = trim((string) $value);
        $parts = $url !== '' ? parse_url($url) : false;
        if (!$parts) return null;
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = trim((string) ($parts['host'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') return null;
        return $url;
    };

    $routes->get('/', [$c['auth'], 'home'])->name('home');
    $routes->post('/', [$c['hotspot'], 'portal'])->name('hotspot.portal');
    $routes->get('/hotspot/compat', [$c['vendos.hotspot'], 'portal'])->name('hotspot.compat');
    $routes->get('/hotspot/device-info', [$c['device_info'], 'show'])->name('hotspot.device_info');
    $routes->post('/hotspot/device-voucher', [$c['device_info'], 'saveVoucher'])->name('hotspot.device_voucher');
    $routes->post('/hotspot/authenticate', [$c['hotspot'], 'authenticate'])->name('hotspot.authenticate');
    $routes->post('/hotspot/session', static function () use ($c, $nativeUrl): never { $url = $nativeUrl($_POST['refresh_url'] ?? null); if ($url !== null) { header('Location: ' . $url, true, 303); exit; } $c['hotspot']->session(); })->name('hotspot.session');
    $routes->post('/hotspot/disconnected', static function () use ($c, $nativeUrl): never { $url = $nativeUrl($_POST['login_url'] ?? null); if ($url !== null) { header('Location: ' . $url, true, 303); exit; } $c['hotspot']->disconnected(); })->name('hotspot.disconnected');

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
    $routes->get('/dashboard', [$c['dashboard'], 'index'])->name('dashboard')->auth()->middleware('prefab.access');
    $routes->post('/devices/claim', [$c['dashboard'], 'claimDevice'])->name('devices.claim')->auth()->middleware('prefab.access');

    $adminRoot = dirname(__DIR__) . '/Admin';
    foreach (['Users', 'Permissions', 'Groups', 'Routers', 'Vendos', 'Vouchers', 'Devices', 'Sessions', 'Sales', 'Logs'] as $feature) {
        (require $adminRoot . '/' . $feature . '/routes.php')($routes, $c);
    }
};
