<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    $routes
        ->get('/admin/vouchers', [$c['admin.vouchers'], 'index'])
        ->name('admin.vouchers.index')
        ->auth()
        ->permission('vouchers.view')
        ->middleware('prefab.access');

    $routes
        ->post('/admin/vouchers', [$c['admin.vouchers'], 'index'])
        ->name('admin.vouchers.store')
        ->auth()
        ->permission('vouchers.manage')
        ->middleware('prefab.access');
};
