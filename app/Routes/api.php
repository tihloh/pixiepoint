<?php

declare(strict_types=1);

use Tihloh\Prefab\Routes\RouteManager;

return static function (RouteManager $routes, array $c): void {
    // Native Vendo Gateway protocol endpoints.
    $routes->get('/vendo/v1/discover', [$c['vendo.gateway'], 'discover'])->name('vendo.discover');
    $routes->post('/vendo/v1/pairings', [$c['vendo.gateway'], 'pair'])->name('vendo.enroll');
    $routes->post('/vendo/v1/pairings/{id}/ack', [$c['vendo.gateway'], 'pairingAck'])->name('vendo.enrollment.ack');
    $routes->post('/vendo/v1/heartbeat', [$c['vendo.gateway'], 'heartbeat'])->name('vendo.heartbeat');
    $routes->post('/vendo/v1/sync', [$c['vendo.gateway'], 'sync'])->name('vendo.sync');
    $routes->post('/vendo/v1/events', [$c['vendo.gateway'], 'events'])->name('vendo.events');
    $routes->get('/vendo/v1/commands', [$c['vendo.gateway'], 'commands'])->name('vendo.commands');
    $routes->post('/vendo/v1/commands/{id}/ack', [$c['vendo.gateway'], 'commandAck'])->name('vendo.commands.ack');
    $routes->get('/vendo/v1/config', [$c['vendo.gateway'], 'config'])->name('vendo.config');
    $routes->post('/vendo/v1/state', [$c['vendo.gateway'], 'state'])->name('vendo.state');
    $routes->post('/vendo/v1/firmware/check', [$c['vendo.gateway'], 'firmware'])->name('vendo.firmware');
    $routes->get('/vendo/v1/firmware/download/{version}/{target}', [$c['vendo.gateway'], 'firmwareDownload'])->name('vendo.firmware.download');

    $routes->get('/hotspot/health', [$c['api'], 'health'])->name('api.health');
    $routes->get('/api/router/install', [$c['router.agent'], 'install'])->name('api.router.install');
    $routes->get('/api/router/poll', [$c['router.agent'], 'poll'])->name('api.router.poll');
    $routes->get('/api/router/ack', [$c['router.agent'], 'ack'])->name('api.router.ack');
    $routes->post('/api/accounting', [$c['api'], 'accounting'])->name('api.accounting');
    $routes->post('/api/router/login-event', [$c['api'], 'loginEvent'])->name('api.router.login-event');
};
