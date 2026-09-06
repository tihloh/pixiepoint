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
        $context = new ThemeContext($data, $features);
        $html = $this->renderFile($slug, $entry, $context, []);

        $html = $this->inlineLocalAssets($html, $slug);

        return $adapter->transform($html, $context);
    }

    /** @param array<string,bool> $features */
    private function renderFile(string $slug, string $relativePath, ThemeContext $context, array $stack): string
    {
        $path = $this->themes->filePath($slug, $relativePath);
        if ($path === null) {
            throw new RuntimeException('Portal theme file not found: ' . $relativePath);
        }

        $key = $slug . ':' . $relativePath;
        if (in_array($key, $stack, true)) {
            throw new RuntimeException('Circular portal theme component reference: ' . $relativePath);
        }
        $stack[] = $key;

        $html = (string) file_get_contents($path);

        $html = preg_replace_callback(
            '/\{\{>\s*([^\s}]+)\s*\}\}/',
            function (array $match) use ($slug, $context, $stack): string {
                $component = trim($match[1]);
                $requirements = $this->themes->componentRequirements($slug, $component);
                foreach ($requirements as $feature) {
                    if (!(bool) ($context->features[$feature] ?? false)) {
                        return '';
                    }
                }

                return $this->renderFile($slug, 'components/' . $component . '.html', $context, $stack);
            },
            $html,
        ) ?? $html;

        $values = [
            'theme.url' => $this->themes->assetUrl($slug),
            'theme.asset_url' => $this->themes->assetUrl($slug),
            'theme.name' => (string) ($this->themes->find($slug)['name'] ?? $slug),
        ];

        $merged = $this->flatten($context->data);
        foreach ($merged as $key => $value) {
            $values[$key] = is_scalar($value) ? (string) $value : '';
        }

        $html = preg_replace_callback(
            '/\{\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}\}/',
            static fn (array $m): string => (string) ($values[$m[1]] ?? ''),
            $html,
        ) ?? $html;

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/',
            static fn (array $m): string => e($values[$m[1]] ?? ''),
            $html,
        ) ?? $html;
    }

    private function inlineLocalAssets(string $html, string $slug): string
    {
        $assetUrl = rtrim($this->themes->assetUrl($slug), '/');
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

        $html = preg_replace_callback(
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

        return $html;
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
