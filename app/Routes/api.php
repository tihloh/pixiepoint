<?php

declare(strict_types=1);

use PixiePoint\App\Http\Router;

return static function (Router $router, array $c): void {
    $router->add('GET', '/hotspot/health', [$c['api'], 'health']);
    $router->add('POST', '/api/accounting', [$c['api'], 'accounting']);
};
