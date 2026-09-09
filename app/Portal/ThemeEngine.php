<?php

namespace App\Portal;

use RuntimeException;

class ThemeEngine
{
    private string $themesDirectory;

    public function __construct(?string $themesDirectory = null)
    {
        $this->themesDirectory = $themesDirectory
            ?? dirname(__DIR__) . '/Portal/Themes';
    }

    public function render(string $theme, string $page, array $data = []): string
    {
        $themeDirectory = $this->themeDirectory($theme);
        $pagePath = $this->safePath($themeDirectory, $page . '.html');

        if (!is_file($pagePath)) {
            throw new RuntimeException('Portal theme page not found: ' . $theme . '/' . $page);
        }

        $html = file_get_contents($pagePath);
        if ($html === false) {
            throw new RuntimeException('Unable to read portal theme page: ' . $pagePath);
        }

        $data['theme'] = array_merge($data['theme'] ?? [], [
            'name' => $theme,
            'url' => '',
            'asset_url' => '',
        ]);

        $html = $this->replacePlaceholders($html, $data);
        return $this->injectThemeAssets($html, $themeDirectory);
    }

    private function themeDirectory(string $theme): string
    {
        $theme = trim($theme);
        if ($theme === '' || !preg_match('/^[A-Za-z0-9_-]+$/', $theme)) {
            throw new RuntimeException('Invalid portal theme name.');
        }

        $base = realpath($this->themesDirectory);
        if ($base === false) {
            throw new RuntimeException('Portal themes directory not found.');
        }

        $directory = realpath($base . DIRECTORY_SEPARATOR . $theme);
        if ($directory === false || !is_dir($directory) || !str_starts_with($directory . DIRECTORY_SEPARATOR, $base . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Portal theme not found: ' . $theme);
        }

        return $directory;
    }

    private function safePath(string $base, string $relative): string
    {
        $path = $base . DIRECTORY_SEPARATOR . ltrim($relative, DIRECTORY_SEPARATOR);
        $real = realpath($path);

        if ($real === false || !str_starts_with($real, rtrim($base, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Invalid portal theme path.');
        }

        return $real;
    }

    private function replacePlaceholders(string $html, array $data): string
    {
        return preg_replace_callback('/\{\{\s*([a-zA-Z0-9_.:-]+)\s*\}\}/', function (array $matches) use ($data): string {
            $value = $this->getValue($data, $matches[1]);
            return $this->escape((string) ($value ?? ''));
        }, $html) ?? $html;
    }

    private function getValue(array $data, string $key): mixed
    {
        $value = $data;
        foreach (explode('.', $key) as $part) {
            if (!is_array($value) || !array_key_exists($part, $value)) {
                return null;
            }
            $value = $value[$part];
        }
        return $value;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function injectThemeAssets(string $html, string $themeDirectory): string
    {
        $css = $this->readOptionalAsset($themeDirectory . DIRECTORY_SEPARATOR . 'theme.css');
        $js = $this->readOptionalAsset($themeDirectory . DIRECTORY_SEPARATOR . 'theme.js');

        if ($css !== '') {
            $style = "<style data-pixiepoint-theme-css>\n" . $css . "\n</style>";
            if (preg_match('/<\/head>/i', $html)) {
                $html = preg_replace('/<\/head>/i', $style . "\n</head>", $html, 1) ?? $html;
            } else {
                $html = $style . "\n" . $html;
            }
        }

        if ($js !== '') {
            $script = "<script data-pixiepoint-theme-js>\n" . $js . "\n</script>";
            if (preg_match('/<\/body>/i', $html)) {
                $html = preg_replace('/<\/body>/i', $script . "\n</body>", $html, 1) ?? $html;
            } else {
                $html .= "\n" . $script;
            }
        }

        return $html;
    }

    private function readOptionalAsset(string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Unable to read portal theme asset: ' . $path);
        }

        return $content;
    }
}
