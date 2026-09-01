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

            $nav = '<nav class="navbar navbar-expand-lg border-bottom sticky-top shadow-sm">'
                . '<div class="container-xl py-1">'
                . '<a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/dashboard">'
                . '<span class="logo logo-sm">P</span><span>' . $name . '</span></a>'
                . '<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#pixiepoint-nav" aria-controls="pixiepoint-nav" aria-expanded="false" aria-label="Toggle navigation"><span class="navbar-toggler-icon"></span></button>'
                . '<div class="collapse navbar-collapse" id="pixiepoint-nav">'
                . '<div class="navbar-nav ms-auto align-items-lg-center gap-lg-1 py-2 py-lg-0">'
                . '<a class="nav-link" href="/dashboard">Dashboard</a>'
                . $manage
                . '<div class="vr d-none d-lg-block mx-2"></div>'
                . '<a class="nav-link" href="/logout">Log out</a>'
                . '</div></div></div></nav>';
        }

        $content = $this->bootstrapMarkup($content);
        $main = $dashboard
            ? '<main class="container-xl py-4 py-lg-5">'
            : '<main class="portal container-fluid">';

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
        return '<section class="card portal-card border-0 shadow-lg">'
            . '<div class="card-body p-3 p-sm-4">'
            . '<div class="brand">'
            . '<div class="logo">P</div>'
            . '<div><strong>PixiePoint Wi-Fi</strong><div class="text-body-secondary small">MikroTik hotspot access</div></div>'
            . '</div>'
            . $this->bootstrapMarkup($body)
            . '</div>'
            . '</section>';
    }

    private function bootstrapMarkup(string $html): string
    {
        $replacements = [
            'class="heading"' => 'class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4"',
            'class="grid"' => 'class="dashboard-grid mb-4"',
            'class="metric"' => 'class="card card-body h-100 metric"',
            'class="panel"' => 'class="card card-body mt-4"',
            'class="form-grid"' => 'class="form-grid"',
            'class="actions"' => 'class="d-flex flex-wrap gap-2"',
            'class="muted"' => 'class="text-body-secondary"',
            'class="button secondary full"' => 'class="btn btn-outline-secondary w-100"',
            'class="button full"' => 'class="btn btn-primary w-100"',
            'class="button secondary"' => 'class="btn btn-outline-secondary"',
            'class="button ghost"' => 'class="btn btn-outline-secondary"',
            'class="button"' => 'class="btn btn-primary"',
            'class="alert ok"' => 'class="alert alert-success"',
            'class="alert"' => 'class="alert alert-danger"',
            'class="notice"' => 'class="alert alert-success"',
            'class="badge off"' => 'class="badge rounded-pill text-bg-secondary"',
            'class="badge"' => 'class="badge rounded-pill text-bg-success"',
            'class="code"' => 'class="font-monospace"',
            'class="empty"' => 'class="text-center text-body-secondary py-4"',
        ];

        $html = strtr($html, $replacements);

        $html = preg_replace('/<input(?![^>]*\bclass=)([^>]*)>/i', '<input class="form-control"$1>', $html) ?? $html;
        $html = preg_replace('/<select(?![^>]*\bclass=)([^>]*)>/i', '<select class="form-select"$1>', $html) ?? $html;
        $html = preg_replace('/<textarea(?![^>]*\bclass=)([^>]*)>/i', '<textarea class="form-control"$1>', $html) ?? $html;

        $html = preg_replace_callback('/<label([^>]*)>/i', static function (array $match): string {
            if (str_contains($match[1], 'class=')) return $match[0];
            return '<label class="form-label"' . $match[1] . '>';
        }, $html) ?? $html;

        $html = str_replace('<table>', '<div class="table-responsive"><table class="table table-hover align-middle mb-0">', $html);
        $html = str_replace('</table>', '</table></div>', $html);

        return $html;
    }
}
