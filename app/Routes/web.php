<?php

declare(strict_types=1);

use PixiePoint\App\Http\Router;

return static function (Router $router, array $c): void {
    $router->any('/', [$c['hotspot'], 'portal']);
    $router->add('POST', '/hotspot/authenticate', [$c['hotspot'], 'authenticate']);
    $router->add('POST', '/hotspot/session', [$c['hotspot'], 'session']);
    $router->add('POST', '/hotspot/disconnected', [$c['hotspot'], 'disconnected']);

    $router->any('/setup', [$c['auth'], 'setup']);
    $router->any('/register', [$c['auth'], 'register']);
    $router->any('/login', [$c['auth'], 'login']);
    $router->add('GET', '/logout', [$c['auth'], 'logout']);
    $router->add('GET', '/auth/google', [$c['auth'], 'googleStart']);
    $router->add('GET', '/auth/google/callback', [$c['auth'], 'googleCallback']);

    $router->add('GET', '/admin/login', static fn () => redirect('/login'));
    $router->add('GET', '/admin/logout', static fn () => redirect('/logout'));
    $router->add('GET', '/admin', static fn () => redirect('/dashboard'));

    $router->add('GET', '/dashboard', [$c['dashboard'], 'index']);
    $router->any('/admin/routers', [$c['admin'], 'routers']);
    $router->any('/admin/vouchers', [$c['admin'], 'vouchers']);
    $router->add('GET', '/admin/devices', [$c['admin'], 'devices']);
    $router->add('GET', '/admin/sessions', [$c['admin'], 'sessions']);
};
