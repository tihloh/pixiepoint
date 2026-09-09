<?php

declare(strict_types=1);

namespace PixiePoint\App\Portal;

use PixiePoint\App\Portal\Adapters\PlatformAdapter;
use PixiePoint\App\Services\PortalThemeManager;
use RuntimeException;

final class ThemeEngine
{
    public function __construct(private PortalThemeManager $themes)
    {
    }

    /** @param array<string,mixed> $data @param array<string,bool> $features */
    public function render(
        array $theme,
        string $entry,
        PlatformAdapter $adapter,
        array $data = [],
        array $features = [],
    ): string {
        $slug = (string) ($theme['slug'] ?? '');
        $path = $this->themes->filePath($slug, $entry);
        if ($path === null) {
            throw new RuntimeException('Portal theme file not found: ' . $entry);
        }

        $context = new ThemeContext($data, $features);
        $html = (string) file_get_contents($path);

        $values = [
            'theme.url' => $this->themes->assetUrl($slug),
            'theme.asset_url' => $this->themes->assetUrl($slug),
            'theme.name' => (string) ($this->themes->find($slug)['name'] ?? $slug),
        ];

        foreach ($this->flatten($context->data) as $key => $value) {
            $values[$key] = is_scalar($value) ? (string) $value : '';
        }

        $html = preg_replace_callback(
            '/\{\{\s*(html:)?([a-zA-Z0-9_.-]+)\s*\}\}/',
            static function (array $m) use ($values): string {
                $value = (string) ($values[$m[2]] ?? '');

                return $m[1] !== null && $m[1] !== '' ? $value : e($value);
            },
            $html,
        ) ?? $html;

        $html = $this->inlineLocalAssets($html, $slug);
        $html = $this->appendDebugPanel($html, $values, $features, $data['portal']['debug'] ?? null);

        return $adapter->transform($html, $context);
    }

    private function inlineLocalAssets(string $html, string $slug): string
    {
        $assetUrl = rtrim($this->themes->assetUrl($slug), '/');
        if ($assetUrl === '') {
            return $html;
        }
        $quotedAssetUrl = preg_quote($assetUrl, '/');

        $html = preg_replace_callback(
            '/<link\b(?=[^>]*\brel=["\']stylesheet["\'])([^>]*\bhref=["\'])' . $quotedAssetUrl . '\/([^"\']+)(["\'][^>]*)>/i',
            function (array $match) use ($slug): string {
                $path = trim(rawurldecode($match[2]));
                $content = $this->readAsset($slug, $path);
                if ($content === null) {
                    return $match[0];
                }

                return '<style data-pixiepoint-theme-asset="' . e($path) . '">' . $this->inlineCssUrls($content, $slug, $path) . '</style>';
            },
            $html,
        ) ?? $html;

        $html = preg_replace_callback(
            '/<script\b([^>]*)\bsrc=(["\'])' . $quotedAssetUrl . '\/([^"\']+)\2([^>]*)>\s*<\/script>/i',
            function (array $match) use ($slug): string {
                $path = trim(rawurldecode($match[3]));
                $content = $this->readAsset($slug, $path);
                if ($content === null) {
                    return $match[0];
                }

                $content = str_replace('</script', '<\\/script', $content);

                return '<script' . $match[1] . $match[4] . '>' . $content . '</script>';
            },
            $html,
        ) ?? $html;

        return preg_replace_callback(
            '/\b(src|href)=("|\')' . $quotedAssetUrl . '\/([^"\']+)(\2)/i',
            function (array $match) use ($slug): string {
                $path = trim(rawurldecode($match[3]));
                $dataUri = $this->assetDataUri($slug, $path);
                if ($dataUri === null) {
                    return $match[0];
                }

                return $match[1] . '=' . $match[2] . $dataUri . $match[4];
            },
            $html,
        ) ?? $html;
    }

    private function inlineCssUrls(string $css, string $slug, string $cssPath): string
    {
        $directory = trim(str_replace('\\', '/', dirname($cssPath)), '.');
        $directory = trim($directory, '/');
        $assetBase = $directory === '' ? '' : $directory . '/';

        return preg_replace_callback(
            '/url\((\s*["\']?)(?!data:|https?:|\/\/|#)([^"\')\s]+)(["\']?\s*)\)/i',
            function (array $match) use ($slug, $assetBase): string {
                $path = $this->normalizeAssetPath($assetBase . rawurldecode(trim($match[2])));
                $dataUri = $this->assetDataUri($slug, $path);
                if ($dataUri === null) {
                    return $match[0];
                }

                return 'url(' . $match[1] . $dataUri . $match[3] . ')';
            },
            $css,
        ) ?? $css;
    }

    private function readAsset(string $slug, string $path): ?string
    {
        $file = $this->themes->filePath($slug, $path);
        if ($file === null) {
            return null;
        }

        $content = file_get_contents($file);

        return $content === false ? null : $content;
    }

    private function assetDataUri(string $slug, string $path): ?string
    {
        $path = $this->normalizeAssetPath($path);
        $file = $this->themes->filePath($slug, $path);
        if ($file === null) {
            return null;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $mime = function_exists('mime_content_type') ? mime_content_type($file) : null;
        if (!is_string($mime) || $mime === '') {
            $mime = $this->mimeType($path);
        }

        return 'data:' . $mime . ';base64,' . base64_encode($content);
    }

    private function normalizeAssetPath(string $path): string
    {
        $segments = [];
        foreach (explode('/', str_replace('\\', '/', $path)) as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }
            if ($segment === '..') {
                array_pop($segments);
                continue;
            }
            $segments[] = $segment;
        }

        return implode('/', $segments);
    }

    private function mimeType(string $path): string
    {
        return match (strtolower((string) pathinfo($path, PATHINFO_EXTENSION))) {
            'css' => 'text/css',
            'js', 'mjs' => 'text/javascript',
            'html', 'htm' => 'text/html',
            'svg' => 'image/svg+xml',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'avif' => 'image/avif',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'otf' => 'font/otf',
            default => 'application/octet-stream',
        };
    }

    /** @param array<string,mixed> $values @param array<string,bool> $features */
    private function appendDebugPanel(string $html, array $values, array $features, mixed $debug): string
    {
        if (!is_array($debug) || $debug === []) {
            return $html;
        }

        $placeholderRows = '';
        foreach ($values as $key => $value) {
            $display = trim((string) $value);
            if ($display === '') {
                $display = '∅';
            }
            $placeholderRows .= '<tr><th scope="row">{{ ' . e($key) . ' }}</th><td><pre>' . e($display) . '</pre></td></tr>';
        }

        $featureRows = '';
        foreach ($features as $key => $enabled) {
            $featureRows .= '<tr><th scope="row">' . e((string) $key) . '</th><td>' . ($enabled ? 'true' : 'false') . '</td></tr>';
        }

        $debugJson = json_encode($debug, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($debugJson)) {
            $debugJson = '{}';
        }

        return $html
            . '<section id="pp-debug" class="pp-debug" style="margin:24px auto;padding:16px;max-width:1000px;border:1px solid #888;border-radius:8px;background:rgba(0,0,0,.04);font:14px/1.45 system-ui,sans-serif;color:inherit;overflow:auto">'
            . '<h2 style="margin:0 0 12px;font-size:18px">PixiePoint Debug</h2>'
            . '<p style="margin:0 0 16px"><strong>Debug mode enabled.</strong> These are the values currently available to theme placeholders.</p>'
            . '<h3 style="margin:16px 0 8px;font-size:15px">Available placeholders</h3>'
            . '<table style="width:100%;border-collapse:collapse"><tbody>' . $placeholderRows . '</tbody></table>'
            . '<h3 style="margin:16px 0 8px;font-size:15px">Features</h3>'
            . '<table style="width:100%;border-collapse:collapse"><tbody>' . $featureRows . '</tbody></table>'
            . '<h3 style="margin:16px 0 8px;font-size:15px">Debug details</h3>'
            . '<details><summary style="cursor:pointer">Show raw debug payload</summary><pre style="white-space:pre-wrap;margin-top:8px">' . e($debugJson) . '</pre></details>'
            . '</section>';
    }

    /** @return array<string,mixed> */
    private function flatten(array $data, string $prefix = ''): array
    {
        $result = [];
        foreach ($data as $key => $value) {
            $name = $prefix === '' ? (string) $key : $prefix . '.' . $key;
            if (is_array($value)) {
                $result += $this->flatten($value, $name);
                continue;
            }
            $result[$name] = $value;
        }

        return $result;
    }
}
