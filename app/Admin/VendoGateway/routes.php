<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function(RouteManager $routes,array $c): void {
    $routes->matchMethods(['GET','POST'],'/admin/vendo-gateway',[$c['admin.vendo-gateway'],'index'])
        ->name('admin.vendo_gateway.index')
        ->auth()
        ->permission('vendos.manage')
        ->middleware('prefab.access');
};
