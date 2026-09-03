<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes
        ->matchMethods(['GET', 'POST'], '/profile', [$c['profile'], 'index'])
        ->name('profile')
        ->auth()
        ->middleware('prefab.access');
};
