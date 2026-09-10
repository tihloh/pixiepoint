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

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Unable to read portal theme file: ' . $entry);
        }

        $context = new ThemeContext($data, $features);
        $html = $this->sanitizeThemeContent($content);
        $themeRecord = $this->themes->find($slug);

        $values = [
            'theme.name' => (string) ($themeRecord['name'] ?? $slug),
        ];

        foreach ($this->flatten($context->data) as $key => $value) {
            $values[$key] = $value;
        }
        foreach ($features as $key => $enabled) {
            $values['feature.' . $key] = (bool) $enabled;
        }

        $html = $this->renderConditionals($html, $values, $features);
        $html = $this->renderVariables($html, $values);
        $html = $this->injectThemeAssets($html, $slug);
        $html = $this->appendDebugPanel($html, $values, $features, $data['portal']['debug'] ?? null);

        return $adapter->transform($html, $context);
    }

    /** @param array<string,mixed> $values */
    private function renderVariables(string $html, array $values): string
    {
        return preg_replace_callback(
            '/\{\{\s*(html:)?([a-zA-Z0-9_.-]+)\s*\}\}/',
            static function (array $m) use ($values): string {
                $value = $values[$m[2]] ?? '';
                if (is_bool($value)) {
                    $value = $value ? 'true' : 'false';
                } elseif (!is_scalar($value)) {
                    $value = '';
                }
                $value = (string) $value;

                return $m[1] !== null && $m[1] !== '' ? $value : e($value);
            },
            $html,
        ) ?? $html;
    }

    /**
     * Supported blocks:
     * {{#if portal.state == "login"}}...{{else}}...{{/if}}
     * {{#unless client.authenticated}}...{{/unless}}
     * {{#feature coin_slot}}...{{else}}...{{/feature}}
     * Blocks may be nested.
     *
     * @param array<string,mixed> $values
     * @param array<string,bool> $features
     */
    private function renderConditionals(string $html, array $values, array $features): string
    {
        $pattern = '/\{\{\s*#(if|unless|feature)\s+([^{}]+?)\s*\}\}((?:(?!\{\{\s*#(?:if|unless|feature)\b|\{\{\s*\/(?:if|unless|feature)\s*\}\}).)*)\{\{\s*\/\1\s*\}\}/s';
        $guard = 0;

        while ($guard++ < 100 && preg_match($pattern, $html)) {
            $html = preg_replace_callback($pattern, function (array $m) use ($values, $features): string {
                $type = $m[1];
                $expression = trim($m[2]);
                $body = $m[3];
                $parts = preg_split('/\{\{\s*else\s*\}\}/', $body, 2) ?: [$body];
                $truthy = $type === 'feature'
                    ? (bool) ($features[$expression] ?? false)
                    : $this->evaluateCondition($expression, $values);

                if ($type === 'unless') {
                    $truthy = !$truthy;
                }

                return $truthy ? ($parts[0] ?? '') : ($parts[1] ?? '');
            }, $html, 1) ?? $html;
        }

        return $html;
    }

    /** @param array<string,mixed> $values */
    private function evaluateCondition(string $expression, array $values): bool
    {
        foreach ($this->splitExpression($expression, '||') as $orPart) {
            $andResult = true;
            foreach ($this->splitExpression($orPart, '&&') as $andPart) {
                if (!$this->evaluateAtomicCondition(trim($andPart), $values)) {
                    $andResult = false;
                    break;
                }
            }
            if ($andResult) {
                return true;
            }
        }

        return false;
    }

    /** @return array<int,string> */
    private function splitExpression(string $expression, string $operator): array
    {
        $parts = [];
        $buffer = '';
        $quote = null;
        $length = strlen($expression);

        for ($i = 0; $i < $length; $i++) {
            $char = $expression[$i];
            if (($char === '"' || $char === "'") && ($i === 0 || $expression[$i - 1] !== '\\')) {
                $quote = $quote === null ? $char : ($quote === $char ? null : $quote);
            }
            if ($quote === null && substr($expression, $i, strlen($operator)) === $operator) {
                $parts[] = trim($buffer);
                $buffer = '';
                $i += strlen($operator) - 1;
                continue;
            }
            $buffer .= $char;
        }

        $parts[] = trim($buffer);
        return $parts;
    }

    /** @param array<string,mixed> $values */
    private function evaluateAtomicCondition(string $expression, array $values): bool
    {
        if ($expression === '') {
            return false;
        }

        if ($expression[0] === '!' && !str_starts_with($expression, '!=')) {
            return !$this->truthy($this->resolveOperand(substr($expression, 1), $values));
        }

        if (preg_match('/^(.+?)\s*(===|!==|==|!=|>=|<=|>|<)\s*(.+)$/', $expression, $m)) {
            $left = $this->resolveOperand(trim($m[1]), $values);
            $right = $this->resolveOperand(trim($m[3]), $values);

            return match ($m[2]) {
                '===', '==' => $left == $right,
                '!==', '!=' => $left != $right,
                '>' => $left > $right,
                '>=' => $left >= $right,
                '<' => $left < $right,
                '<=' => $left <= $right,
                default => false,
            };
        }

        return $this->truthy($this->resolveOperand($expression, $values));
    }

    /** @param array<string,mixed> $values */
    private function resolveOperand(string $operand, array $values): mixed
    {
        $operand = trim($operand);
        $length = strlen($operand);
        if ($length >= 2 && (($operand[0] === '"' && $operand[$length - 1] === '"') || ($operand[0] === "'" && $operand[$length - 1] === "'"))) {
            return stripcslashes(substr($operand, 1, -1));
        }

        $lower = strtolower($operand);
        if ($lower === 'true') return true;
        if ($lower === 'false') return false;
        if ($lower === 'null') return null;
        if (is_numeric($operand)) return str_contains($operand, '.') ? (float) $operand : (int) $operand;

        return $values[$operand] ?? null;
    }

    private function truthy(mixed $value): bool
    {
        if (is_bool($value)) return $value;
        if ($value === null) return false;
        if (is_int($value) || is_float($value)) return $value != 0;
        if (is_array($value)) return $value !== [];

        $value = strtolower(trim((string) $value));
        return $value !== '' && !in_array($value, ['0', 'false', 'null', 'no', 'off'], true);
    }

    /** @param array<string,mixed> $values @param array<string,bool> $features */
    private function appendDebugPanel(string $html, array $values, array $features, mixed $debug): string
    {
        if (!is_array($debug) || $debug === []) {
            return $html;
        }

        $placeholderRows = '';
        foreach ($values as $key => $value) {
            $display = is_scalar($value) || $value === null ? trim((string) $value) : json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if ($display === '' || $display === false) {
                $display = '∅';
            }
            $placeholderRows .= '<tr><th scope="row">{{ ' . e($key) . ' }}</th><td><pre>' . e((string) $display) . '</pre></td></tr>';
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

    private function injectThemeAssets(string $html, string $slug): string
    {
        $css = $this->readAsset($slug, 'theme.css');
        if ($css === null) {
            throw new RuntimeException('Portal theme stylesheet not found: theme.css');
        }

        $css = $this->inlineCssUrls($css, $slug, 'theme.css');
        $style = '<style data-pixiepoint-theme-asset="theme.css">' . $css . '</style>';

        if (preg_match('/<\/head\s*>/i', $html)) {
            $html = preg_replace('/<\/head\s*>/i', $style . "\n</head>", $html, 1) ?? $html;
        } else {
            $html = $this->injectFragmentStyle($html, $style);
        }

        $js = $this->readAsset($slug, 'theme.js');
        if ($js === null) {
            return $html;
        }

        $js = str_replace('</script', '<\\/script', $js);
        $script = '<script data-pixiepoint-theme-asset="theme.js">' . $js . '</script>';

        if (preg_match('/<\/body\s*>/i', $html)) {
            $html = preg_replace('/<\/body\s*>/i', $script . "\n</body>", $html, 1) ?? $html;
        } else {
            $html .= "\n" . $script;
        }

        return $html;
    }

    private function injectFragmentStyle(string $html, string $style): string
    {
        if (!preg_match_all('/<link\b[^>]*\brel\s*=\s*(["\'])stylesheet\1[^>]*>/i', $html, $matches, PREG_OFFSET_CAPTURE)) {
            return $style . "\n" . $html;
        }

        $last = end($matches[0]);
        if (!is_array($last)) {
            return $style . "\n" . $html;
        }

        $tag = (string) $last[0];
        $offset = (int) $last[1] + strlen($tag);

        return substr($html, 0, $offset) . "\n" . $style . substr($html, $offset);
    }

    private function sanitizeThemeContent(string $content): string
    {
        $content = preg_replace('/<\?(?:php|=)?/i', '&lt;?', $content) ?? $content;
        $content = preg_replace('/\?>/i', '?&gt;', $content) ?? $content;

        return $content;
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

        return $content === false ? null : $this->sanitizeThemeContent($content);
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
