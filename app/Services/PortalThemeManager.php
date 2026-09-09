<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

use PDO;
use RuntimeException;

final class PortalThemeManager
{
    private string $root;
    private string $baseUrl;

    public function __construct(
        private PDO $db,
        string $projectRoot,
        string $baseUrl = '',
    ) {
        $this->root = rtrim($projectRoot, '/\\') . '/app/Portal/Themes';
        $this->baseUrl = rtrim(trim($baseUrl), '/');
        if ($this->baseUrl === '') {
            $this->baseUrl = $this->requestBaseUrl();
        }

        if (!is_dir($this->root)) {
            @mkdir($this->root, 0775, true);
        }
        $this->sync();
    }

    /** @return array<int,array<string,mixed>> */
    public function all(): array
    {
        return $this->db->query(
            'SELECT id,name,slug,version,description,enabled FROM portal_themes ORDER BY name',
        )->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function find(string $slug): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id,name,slug,version,description,enabled FROM portal_themes WHERE slug=? LIMIT 1',
        );
        $stmt->execute([$slug]);
        $theme = $stmt->fetch();

        return $theme ?: null;
    }

    public function filePath(string $slug, string $relativePath): ?string
    {
        $slug = $this->safeSlug($slug);
        $relativePath = ltrim(str_replace('\\', '/', $relativePath), '/');
        if ($relativePath === '' || str_contains($relativePath, '..') || !preg_match('/^[a-zA-Z0-9._\/-]+$/', $relativePath)) {
            return null;
        }

        $themeRoot = $this->root . '/' . $slug;
        $path = realpath($themeRoot . '/' . $relativePath);
        $realRoot = realpath($themeRoot);
        if ($path === false || $realRoot === false || !str_starts_with($path, $realRoot . DIRECTORY_SEPARATOR) || !is_file($path)) {
            return null;
        }

        return $path;
    }

    public function assetUrl(string $slug): string
    {
        return $this->baseUrl . '/portal-themes/' . $this->safeSlug($slug);
    }

    /** @return array<int,string> */
    public function componentRequirements(string $slug, string $component): array
    {
        $theme = $this->find($slug);
        $components = $theme ? $this->manifest($slug)['components'] ?? [] : [];
        $definition = is_array($components) ? ($components[$component] ?? []) : [];
        if (!is_array($definition)) {
            return [];
        }

        $requires = $definition['requires'] ?? [];
        if (is_string($requires)) {
            return [$requires];
        }

        if (!is_array($requires)) {
            return [];
        }

        return array_values(array_filter(array_map('strval', $requires), static fn (string $value): bool => $value !== ''));
    }

    public function resolve(int $routerId, ?int $vendoId = null): array
    {
        if ($vendoId !== null && $vendoId > 0) {
            $stmt = $this->db->prepare(
                'SELECT pt.id,pt.name,pt.slug,pt.version,pt.description
                 FROM vendos v
                 JOIN portal_themes pt ON pt.id=v.portal_theme_id AND pt.enabled=1
                 WHERE v.id=? AND v.router_id=?
                 LIMIT 1',
            );
            $stmt->execute([$vendoId, $routerId]);
            $theme = $stmt->fetch();
            if ($theme) {
                return $theme;
            }
        }

        $stmt = $this->db->prepare(
            'SELECT pt.id,pt.name,pt.slug,pt.version,pt.description
             FROM routers r
             JOIN portal_themes pt ON pt.id=r.portal_theme_id AND pt.enabled=1
             WHERE r.id=?
             LIMIT 1',
        );
        $stmt->execute([$routerId]);
        $theme = $stmt->fetch();
        if ($theme) {
            return $theme;
        }

        return $this->defaultTheme();
    }

    public function resolveByRouterIdentity(string $routerIdentity, ?int $vendoId = null): array
    {
        $stmt = $this->db->prepare('SELECT id FROM routers WHERE identity=? LIMIT 1');
        $stmt->execute([$routerIdentity]);
        $routerId = (int) $stmt->fetchColumn();
        if ($routerId < 1) {
            return $this->defaultTheme();
        }

        return $this->resolve($routerId, $vendoId);
    }

    private function sync(): void
    {
        $dirs = glob($this->root . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $slug = basename($dir);
            if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug)) {
                continue;
            }

            $manifest = $this->manifest($slug);
            $name = trim((string) ($manifest['name'] ?? $slug));
            $version = trim((string) ($manifest['version'] ?? '1.0.0'));
            $description = trim((string) ($manifest['description'] ?? ''));
            $stmt = $this->db->prepare(
                'INSERT INTO portal_themes(name,slug,version,description,enabled)
                 VALUES(?,?,?,?,1)
                 ON DUPLICATE KEY UPDATE name=VALUES(name),version=VALUES(version),description=VALUES(description)',
            );
            $stmt->execute([$name, $slug, $version, $description ?: null]);
        }
    }

    /** @return array<string,mixed> */
    private function manifest(string $slug): array
    {
        $slug = $this->safeSlug($slug);
        $file = $this->root . '/' . $slug . '/theme.json';
        if (!is_file($file)) {
            return [];
        }

        $decoded = json_decode((string) file_get_contents($file), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function defaultTheme(): array
    {
        $stmt = $this->db->prepare(
            'SELECT id,name,slug,version,description FROM portal_themes WHERE enabled=1 AND slug=? LIMIT 1',
        );
        $stmt->execute(['default']);
        $theme = $stmt->fetch();
        if ($theme) {
            return $theme;
        }

        return ['id' => 0,'name' => 'Default','slug' => 'default','version' => '1.0.0','description' => 'Built-in PixiePoint portal theme'];
    }

    private function safeSlug(string $slug): string
    {
        if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug)) {
            throw new RuntimeException('Invalid portal theme.');
        }

        return $slug;
    }

    private function requestBaseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));

        return $host !== '' ? $scheme . '://' . $host : '';
    }
}
