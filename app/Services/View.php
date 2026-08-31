<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

final class View
{
    public function __construct(private array $config) {}

    public function page(string $title, string $content, bool $dashboard = false, bool $platformOwner = false): never
    {
        $name = e($this->config['app_name'] ?? 'PixiePoint Wi-Fi');
        $nav = '';
        if ($dashboard) {
            $manage = $platformOwner
                ? '<a href="/admin/routers">Routers</a><a href="/admin/vouchers">Vouchers</a><a href="/admin/devices">Devices</a><a href="/admin/sessions">Sessions</a>'
                : '';
            $nav = '<nav><div class="wrap"><strong>' . $name . '</strong><div class="navlinks"><a href="/dashboard">Dashboard</a>' . $manage . '<a href="/logout">Log out</a></div></div></nav>';
        }

        $main = $dashboard ? '<main class="wrap main">' : '<main class="portal">';
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#07111f"><title>' . e($title) . ' · ' . $name . '</title><link rel="stylesheet" href="/assets/app.css"></head><body>' . $nav . $main . $content . '</main></body></html>';
        exit;
    }

    public function portalCard(string $body): string
    {
        return '<section class="card"><div class="brand"><div class="logo">P</div><div><strong>PixiePoint Wi-Fi</strong><div class="muted">MikroTik hotspot access</div></div></div>' . $body . '</section>';
    }
}
