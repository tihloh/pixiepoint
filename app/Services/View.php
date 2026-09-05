<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

use RuntimeException;

/**
 * Small PHP view renderer used by both the portal and management interface.
 *
 * Views remain ordinary PHP files so markup stays easy to inspect and edit.
 */
final class View
{
    public function __construct(private array $config)
    {
    }

    /**
     * Renders a view from app/Views using a slash-delimited view name.
     */
    public function render(string $view, array $data = []): string
    {
        $view = trim(str_replace('\\', '/', $view), '/');

        if ($view === '' || str_contains($view, '..')) {
            throw new RuntimeException('Invalid view path.');
        }

        return $this->renderFile(
            dirname(__DIR__) . '/Views/' . $view . '.php',
            $data,
        );
    }

    /**
     * Renders an explicit PHP view file, including feature-local admin views.
     */
    public function renderFile(string $file, array $data = []): string
    {
        if (
            !is_file($file)
            || strtolower((string) pathinfo($file, PATHINFO_EXTENSION)) !== 'php'
        ) {
            throw new RuntimeException('View not found: ' . $file);
        }

        extract($data, EXTR_SKIP);
        ob_start();
        require $file;

        return (string) ob_get_clean();
    }

    /**
     * Renders the application layout and ends the current request.
     *
     * @param array<string, bool> $access Navigation permissions for management pages.
     */
    public function page(
        string $title,
        string $content,
        bool $dashboard = false,
        array $access = [],
    ): never {
        $assets = dirname(__DIR__, 2) . '/public/assets';
        $cssFiles = [
            $assets . '/app.css',
            $assets . '/admin.css',
        ];
        $cssVersion = 1;

        foreach ($cssFiles as $cssFile) {
            if (is_file($cssFile)) {
                $cssVersion = max($cssVersion, (int) filemtime($cssFile));
            }
        }

        echo $this->render('layouts/app', [
            'title' => $title,
            'name' => e($this->config['app_name'] ?? 'PixiePoint Wi-Fi'),
            'content' => $this->bootstrapMarkup($content),
            'dashboard' => $dashboard,
            'access' => $access,
            'cssVersion' => (string) $cssVersion,
        ]);

        exit;
    }

    /**
     * Wraps public hotspot content in the shared portal card layout.
     */
    public function portalCard(string $body): string
    {
        return $this->render('partials/portal-card', [
            'body' => $this->bootstrapMarkup($body),
        ]);
    }

    /**
     * Maps PixiePoint's small semantic CSS vocabulary to Bootstrap classes.
     *
     * This keeps feature views readable without repeating long Bootstrap class
     * lists throughout every template.
     */
    private function bootstrapMarkup(string $html): string
    {
        $replacements = [
            'class="heading"' => 'class="d-flex flex-column flex-md-row align-items-md-end justify-content-between gap-3 mb-4"',
            'class="grid"' => 'class="d-grid gap-3 mb-4" style="grid-template-columns:repeat(auto-fit,minmax(160px,1fr))"',
            'class="metric"' => 'class="card card-body h-100 metric"',
            'class="panel"' => 'class="card card-body mt-4"',
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

        $html = preg_replace(
            '/<input(?![^>]*\\bclass=)([^>]*)>/i',
            '<input class="form-control"$1>',
            $html,
        ) ?? $html;

        $html = preg_replace(
            '/<select(?![^>]*\\bclass=)([^>]*)>/i',
            '<select class="form-select"$1>',
            $html,
        ) ?? $html;

        $html = preg_replace(
            '/<textarea(?![^>]*\\bclass=)([^>]*)>/i',
            '<textarea class="form-control"$1>',
            $html,
        ) ?? $html;

        $html = preg_replace_callback(
            '/<label([^>]*)>/i',
            static function (array $match): string {
                if (str_contains($match[1], 'class=')) {
                    return $match[0];
                }

                return '<label class="form-label"' . $match[1] . '>';
            },
            $html,
        ) ?? $html;

        $html = str_replace(
            '<table>',
            '<div class="table-responsive"><table class="table table-hover align-middle mb-0">',
            $html,
        );

        return str_replace('</table>', '</table></div>', $html);
    }
}
