<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

final class View
{
    private const BOOTSTRAP_VERSION = '5.3.8';

    public function __construct(private array $config) {}

    /** @param array<string,bool> $access */
    public function page(string $title, string $content, bool $dashboard = false, array $access = []): never
    {
        $name = e($this->config['app_name'] ?? 'PixiePoint Wi-Fi');
        $nav = '';

        if ($dashboard) {
            $manage = '';
            if ($access['routers'] ?? false) $manage .= '<a class="nav-link" href="/admin/routers">Routers</a>';
            if ($access['vouchers'] ?? false) $manage .= '<a class="nav-link" href="/admin/vouchers">Vouchers</a>';
            if ($access['devices'] ?? false) $manage .= '<a class="nav-link" href="/admin/devices">Devices</a>';
            if ($access['sessions'] ?? false) $manage .= '<a class="nav-link" href="/admin/sessions">Sessions</a>';
            if ($access['sales'] ?? false) $manage .= '<a class="nav-link" href="/admin/sales">Sales</a>';
            if ($access['logs'] ?? false) $manage .= '<a class="nav-link" href="/admin/logs">Logs</a>';

            $nav = '<nav class="navbar navbar-expand-lg border-bottom sticky-top">'
                . '<div class="container-xl">'
                . '<a class="navbar-brand fw-bold" href="/dashboard">' . $name . '</a>'
                . '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#pixiepoint-nav" aria-controls="pixiepoint-nav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>'
                . '<div class="collapse navbar-collapse" id="pixiepoint-nav">'
                . '<div class="navbar-nav ms-auto align-items-lg-center">'
                . '<a class="nav-link" href="/dashboard">Dashboard</a>'
                . $manage
                . '<a class="nav-link" href="/logout">Log out</a>'
                . '</div></div></div></nav>';
        }

        $main = $dashboard ? '<main class="container-xl py-4">' : '<main class="portal container-fluid">';
        $cssFile = dirname(__DIR__, 2) . '/public/assets/app.css';
        $cssVersion = is_file($cssFile) ? (string) filemtime($cssFile) : '1';
        $bootstrap = self::BOOTSTRAP_VERSION;

        echo '<!doctype html><html lang="en" data-bs-theme="dark"><head>'
            . '<meta charset="utf-8">'
            . '<meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="theme-color" content="#07111f">'
            . '<title>' . e($title) . ' · ' . $name . '</title>'
            . '<link href="https://cdn.jsdelivr.net/npm/bootstrap@' . $bootstrap . '/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">'
            . '<link rel="stylesheet" href="/assets/app.css?v=' . e($cssVersion) . '">'
            . '</head><body>'
            . $nav
            . $main
            . $content
            . '</main>'
            . '<script src="https://cdn.jsdelivr.net/npm/bootstrap@' . $bootstrap . '/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>'
            . '</body></html>';
        exit;
    }

    public function portalCard(string $body): string
    {
        return '<section class="card border-0 shadow-lg">'
            . '<div class="card-body">'
            . '<div class="brand d-flex align-items-center gap-2">'
            . '<div class="logo">P</div>'
            . '<div><strong>PixiePoint Wi-Fi</strong><div class="text-body-secondary small">MikroTik hotspot access</div></div>'
            . '</div>'
            . $body
            . '</div>'
            . '</section>';
    }
}
