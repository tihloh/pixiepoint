<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

use PDO;
use RuntimeException;

final class PortalThemeManager
{
    private string $root;

    public function __construct(
        private PDO $db,
        string $projectRoot,
    ) {
        $this->root = rtrim($projectRoot, '/\\') . '/public/portal-themes';
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

    public function render(array $theme, string $content, array $data = []): string
    {
        $slug = $this->safeSlug((string) ($theme['slug'] ?? ''));
        $file = $this->root . '/' . $slug . '/index.html';
        if (!is_file($file)) {
            $fallback = $this->defaultTheme();
            $slug = $this->safeSlug($fallback['slug']);
            $file = $this->root . '/' . $slug . '/index.html';
        }
        if (!is_file($file)) {
            throw new RuntimeException('Portal theme entry file is missing.');
        }

        $html = (string) file_get_contents($file);
        $values = [
            'content' => $content,
            'theme.url' => '/portal-themes/' . $slug,
            'theme.name' => (string) ($theme['name'] ?? $slug),
            'theme.slug' => $slug,
        ];
        foreach ($data as $key => $value) {
            $values[(string) $key] = is_scalar($value) ? (string) $value : '';
        }

        $html = preg_replace_callback(
            '/\{\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}\}/',
            static fn (array $m): string => $values[$m[1]] ?? '',
            $html,
        ) ?? $html;

        return preg_replace_callback(
            '/\{\{\s*([a-zA-Z0-9_.-]+)\s*\}\}/',
            static fn (array $m): string => e($values[$m[1]] ?? ''),
            $html,
        ) ?? $html;
    }

    private function sync(): void
    {
        $dirs = glob($this->root . '/*', GLOB_ONLYDIR) ?: [];
        foreach ($dirs as $dir) {
            $slug = basename($dir);
            if (!preg_match('/^[a-z0-9][a-z0-9_-]*$/', $slug)) {
                continue;
            }

            $manifest = [];
            $file = $dir . '/theme.json';
            if (is_file($file)) {
                $decoded = json_decode((string) file_get_contents($file), true);
                if (is_array($decoded)) {
                    $manifest = $decoded;
                }
            }

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
}
