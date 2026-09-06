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
