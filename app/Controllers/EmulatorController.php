<?php

declare(strict_types=1);

namespace PixiePoint\App\Controllers;

use PDO;
use PixiePoint\App\Admin\Shared\RouterAccess;
use PixiePoint\App\Services\AuthContext;

final class EmulatorController
{
    public function __construct(
        private PDO $db,
        private AuthContext $auth,
    ) {
    }

    public function index(): never
    {
        $user = $this->auth->requireAccount();
        $userId = (int) $user['id'];
        $platformOwner = $this->auth->isPlatformOwner();
        new RouterAccess($this->db);

        $routers = $platformOwner
            ? $this->db->query('SELECT id,name,identity,public_host,portal_theme_id FROM routers WHERE enabled=1 ORDER BY name')->fetchAll()
            : $this->routersForUser($userId);
        $vendos = $this->vendosForUser($userId, $platformOwner);

        $data = [
            'user' => ['name' => (string) ($user['name'] ?? ''), 'email' => (string) ($user['email'] ?? '')],
            'platforms' => [['id' => 'mikrotik', 'name' => 'MikroTik']],
            'pages' => ['login.html', 'status.html', 'logout.html', 'alogin.html', 'redirect.html', 'error.html', 'flogin.html', 'rlogin.html'],
            'routers' => array_map(static fn (array $router): array => [
                'id' => (int) $router['id'],
                'name' => (string) $router['name'],
                'identity' => (string) $router['identity'],
                'publicHost' => (string) ($router['public_host'] ?? ''),
                'portalThemeId' => $router['portal_theme_id'] !== null ? (int) $router['portal_theme_id'] : null,
            ], $routers),
            'vendos' => array_map(static fn (array $vendo): array => [
                'id' => (int) $vendo['id'],
                'routerId' => (int) $vendo['router_id'],
                'name' => (string) $vendo['name'],
                'baseUrl' => rtrim((string) $vendo['base_url'], '/'),
                'serverIp' => (string) ($vendo['server_ip'] ?? ''),
                'clientSubnet' => (string) ($vendo['client_subnet'] ?? ''),
                'interfaceName' => (string) ($vendo['interface_name'] ?? ''),
                'portalThemeId' => $vendo['portal_theme_id'] !== null ? (int) $vendo['portal_theme_id'] : null,
                'passwordMode' => (string) ($vendo['password_mode'] ?: 'blank'),
                'chargingEnabled' => (bool) $vendo['charging_enabled'],
                'eloadEnabled' => (bool) $vendo['eload_enabled'],
            ], $vendos),
            'sampleRouter' => ['id' => 0, 'name' => 'Sample Router', 'identity' => 'PIXIEPOINT-DEMO', 'publicHost' => '192.168.88.1'],
            'sampleVendo' => ['id' => 0, 'routerId' => 0, 'name' => 'Sample Vendo', 'baseUrl' => 'https://example.invalid', 'serverIp' => '192.168.88.1', 'clientSubnet' => '192.168.88.0/24', 'interfaceName' => 'bridge-hotspot', 'passwordMode' => 'blank', 'chargingEnabled' => true, 'eloadEnabled' => true],
            'hotspotBaseUrl' => '/mt_hotspot/',
            'emulatorUrl' => '/emulator/',
        ];

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#111827">
    <title>MikroTik Hotspot Emulator · PixiePoint</title>
    <link rel="stylesheet" href="/mt_hotspot/emulator/emulator.css">
</head>
<body>
<header class="shell-header">
    <div class="brand">
        <strong>MikroTik Hotspot Emulator</strong>
        <span>Test the current <code>mt_hotspot/</code> files in your browser.</span>
    </div>
    <div class="header-actions">
        <span><?= e($data['user']['name']) ?></span>
        <a href="/dashboard">Dashboard</a>
    </div>
</header>

<main class="workspace">
    <section class="portal-area">
        <div class="portal-toolbar">
            <span id="load-status" role="status">Ready</span>
            <button type="button" id="reload-page">Reload</button>
        </div>
        <iframe id="portal" title="MikroTik hotspot portal"></iframe>
    </section>

    <aside class="side-panel" id="side-panel">
        <div class="panel-header">
            <div><strong>Emulator</strong><small>RouterOS environment</small></div>
            <button type="button" class="icon-button" id="collapse-panel" aria-label="Collapse emulator panel">›</button>
        </div>
        <div class="controls">
            <label>Platform<select id="platform-select"></select></label>
            <label>Router<select id="router-select"></select></label>
            <label>Vendo<select id="vendo-select"></select></label>
            <label>Page<select id="page-select"></select></label>

            <div class="section-title">Session</div>
            <div class="session-buttons">
                <button type="button" data-page="login.html">Unauthenticated</button>
                <button type="button" data-page="status.html">Authenticated</button>
            </div>

            <details class="variables" open>
                <summary>RouterOS variables</summary>
                <dl id="variables"></dl>
            </details>

            <details>
                <summary>Test pages</summary>
                <div id="page-links" class="page-links"></div>
            </details>
        </div>
    </aside>
    <button type="button" class="expand-button" id="expand-panel" aria-label="Open emulator panel">‹</button>
</main>

<script>
    window.PIXIEPOINT_EMULATOR = <?= json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
</script>
<script src="/mt_hotspot/emulator/emulator.js"></script>
</body>
</html>
<?php
        exit;
    }

    private function routersForUser(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT r.id,r.name,r.identity,r.public_host,r.portal_theme_id
             FROM routers r JOIN router_members rm ON rm.router_id=r.id
             WHERE r.enabled=1 AND rm.user_id=? ORDER BY r.name',
        );
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
