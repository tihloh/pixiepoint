<?php

declare(strict_types=1);

namespace PixiePoint\App\Controllers;

use PDO;
use PixiePoint\App\Services\AuthContext;

final class EmulatorController
{
    private const PAGES = [
        'login.html', 'status.html', 'logout.html', 'alogin.html',
        'redirect.html', 'error.html', 'flogin.html', 'rlogin.html',
    ];

    private string $hotspotRoot;

    public function __construct(private PDO $db, private AuthContext $auth)
    {
        $this->hotspotRoot = dirname(__DIR__, 2) . '/mt_hotspot';
    }

    public function index(): never
    {
        $this->auth->requireAccount();

        if (isset($_GET['asset'])) {
            $this->asset((string) $_GET['asset']);
        }
        if (isset($_GET['page'])) {
            $this->hotspotPage((string) $_GET['page']);
        }

        $user = $this->auth->requireAccount();
        $userId = (int) $user['id'];
        $platformOwner = $this->auth->isPlatformOwner();
        $routers = $platformOwner
            ? $this->db->query('SELECT id,name,identity,public_host,portal_theme_id FROM routers WHERE enabled=1 ORDER BY name')->fetchAll()
            : $this->routersForUser($userId);
        $vendos = $this->vendosForUser($userId, $platformOwner);
        $data = [
            'user' => ['name' => (string) ($user['name'] ?? ''), 'email' => (string) ($user['email'] ?? '')],
            'platforms' => [['id' => 'mikrotik', 'name' => 'MikroTik']],
            'pages' => self::PAGES,
            'routers' => array_map(static fn (array $r): array => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'identity' => (string) $r['identity'], 'publicHost' => (string) ($r['public_host'] ?? ''), 'portalThemeId' => $r['portal_theme_id'] !== null ? (int) $r['portal_theme_id'] : null], $routers),
            'vendos' => array_map(static fn (array $v): array => ['id' => (int) $v['id'], 'routerId' => (int) $v['router_id'], 'name' => (string) $v['name'], 'baseUrl' => rtrim((string) $v['base_url'], '/'), 'serverIp' => (string) ($v['server_ip'] ?? ''), 'clientSubnet' => (string) ($v['client_subnet'] ?? ''), 'interfaceName' => (string) ($v['interface_name'] ?? ''), 'portalThemeId' => $v['portal_theme_id'] !== null ? (int) $v['portal_theme_id'] : null, 'passwordMode' => (string) ($v['password_mode'] ?: 'blank'), 'chargingEnabled' => (bool) $v['charging_enabled'], 'eloadEnabled' => (bool) $v['eload_enabled']], $vendos),
            'sampleRouter' => ['id' => 0, 'name' => 'Sample Router', 'identity' => 'PIXIEPOINT-DEMO', 'publicHost' => '192.168.88.1'],
            'sampleVendo' => ['id' => 0, 'routerId' => 0, 'name' => 'Sample Vendo', 'baseUrl' => 'https://example.invalid', 'serverIp' => '192.168.88.1', 'clientSubnet' => '192.168.88.0/24', 'interfaceName' => 'bridge-hotspot', 'passwordMode' => 'blank', 'chargingEnabled' => true, 'eloadEnabled' => true],
        ];

        $shell = $this->readHotspotFile('emulator/index.html');
        $config = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
        if ($config === false) {
            http_response_code(500);
            exit('Unable to encode emulator configuration.');
        }
        $shell = str_replace('__PIXIEPOINT_EMULATOR_CONFIG__', $config, $shell);

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $shell;
        exit;
    }

    private function hotspotPage(string $page): never
    {
        if (!in_array($page, self::PAGES, true)) {
            http_response_code(404);
            exit('Hotspot page not found.');
        }
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        echo $this->readHotspotFile($page);
        exit;
    }

    private function asset(string $asset): never
    {
        $asset = ltrim($asset, '/');
        if ($asset === '' || str_contains($asset, "\0") || str_contains($asset, '..') || str_starts_with($asset, '/')) {
            http_response_code(404);
            exit('Hotspot asset not found.');
        }
        $path = $this->resolveHotspotFile($asset);
        if ($path === null) {
            http_response_code(404);
            exit('Hotspot asset not found.');
        }
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $types = [
            'css' => 'text/css; charset=utf-8',
            'html' => 'text/html; charset=utf-8',
            'js' => 'application/javascript; charset=utf-8',
            'json' => 'application/json; charset=utf-8',
            'svg' => 'image/svg+xml',
            'txt' => 'text/plain; charset=utf-8',
        ];
        header('Content-Type: ' . ($types[$extension] ?? 'application/octet-stream'));
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($path);
        exit;
    }

    private function readHotspotFile(string $relativePath): string
    {
        $path = $this->resolveHotspotFile($relativePath);
        if ($path === null) {
            http_response_code(404);
            exit('Hotspot file not found.');
        }
        $content = file_get_contents($path);
        if ($content === false) {
            http_response_code(500);
            exit('Unable to read hotspot file.');
        }
        return $content;
    }

    private function resolveHotspotFile(string $relativePath): ?string
    {
        if ($relativePath === '' || str_contains($relativePath, "\0") || str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
            return null;
        }
        $root = realpath($this->hotspotRoot);
        $path = realpath($this->hotspotRoot . '/' . $relativePath);
        if ($root === false || $path === false || !is_file($path)) {
            return null;
        }
        $prefix = rtrim($root, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        return str_starts_with($path, $prefix) ? $path : null;
    }

    private function routersForUser(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT r.id,r.name,r.identity,r.public_host,r.portal_theme_id FROM routers r JOIN router_members rm ON rm.router_id=r.id WHERE r.enabled=1 AND rm.user_id=? ORDER BY r.name');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    private function vendosForUser(int $userId, bool $platformOwner): array
    {
        $sql = $platformOwner
            ? 'SELECT v.id,v.router_id,v.name,v.base_url,v.server_ip,v.client_subnet,v.interface_name,v.portal_theme_id,v.password_mode,v.charging_enabled,v.eload_enabled FROM vendos v JOIN routers r ON r.id=v.router_id WHERE v.enabled=1 AND r.enabled=1 ORDER BY r.name,v.name'
            : 'SELECT v.id,v.router_id,v.name,v.base_url,v.server_ip,v.client_subnet,v.interface_name,v.portal_theme_id,v.password_mode,v.charging_enabled,v.eload_enabled FROM vendos v JOIN routers r ON r.id=v.router_id JOIN router_members rm ON rm.router_id=r.id WHERE v.enabled=1 AND r.enabled=1 AND rm.user_id=? ORDER BY r.name,v.name';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($platformOwner ? [] : [$userId]);
        return $stmt->fetchAll();
    }
}
