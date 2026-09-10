<?php

declare(strict_types=1);

namespace PixiePoint\App\Controllers;

use PDO;
use PixiePoint\App\Services\AuthContext;

final class EmulatorController
{
    private const PAGES = [
        'login.html',
        'status.html',
        'logout.html',
        'alogin.html',
        'redirect.html',
        'error.html',
        'flogin.html',
        'rlogin.html',
    ];

    public function __construct(private PDO $db, private AuthContext $auth) {}

    public function index(): never
    {
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
            'hotspotBaseUrl' => '/emulator/',
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
    <style>
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body { font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: #f3f4f6; color: #111827; overflow: hidden; }
        .shell-header { height: 58px; display: flex; align-items: center; justify-content: space-between; gap: 16px; padding: 0 18px; background: #111827; color: #fff; }
        .brand { display: flex; align-items: baseline; gap: 12px; min-width: 0; }
        .brand strong { white-space: nowrap; }
        .brand span { color: #9ca3af; font-size: 13px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .header-actions { display: flex; gap: 14px; align-items: center; font-size: 13px; white-space: nowrap; }
        .header-actions a { color: #d1d5db; text-decoration: none; }
        .workspace { height: calc(100vh - 58px); display: flex; position: relative; }
        .portal-area { flex: 1; min-width: 0; display: flex; flex-direction: column; background: #fff; }
        .portal-toolbar { height: 42px; display: flex; align-items: center; justify-content: space-between; padding: 0 12px; border-bottom: 1px solid #e5e7eb; font-size: 12px; color: #6b7280; }
        .portal-toolbar button, .controls button { border: 1px solid #d1d5db; background: #fff; color: #111827; border-radius: 6px; padding: 6px 10px; cursor: pointer; }
        .portal-toolbar button:hover, .controls button:hover { background: #f9fafb; }
        #portal { flex: 1; width: 100%; border: 0; background: #fff; }
        .side-panel { width: 310px; flex: 0 0 310px; background: #fff; border-left: 1px solid #d1d5db; overflow-y: auto; transition: width .2s ease, flex-basis .2s ease; }
        .side-panel.collapsed { width: 0; flex-basis: 0; overflow: hidden; border-left: 0; }
        .panel-header { min-height: 64px; display: flex; align-items: center; justify-content: space-between; padding: 12px 14px; border-bottom: 1px solid #e5e7eb; }
        .panel-header strong, .panel-header small { display: block; }
        .panel-header small { margin-top: 2px; color: #6b7280; font-size: 12px; }
        .icon-button, .expand-button { width: 32px; height: 32px; padding: 0 !important; font-size: 20px; line-height: 1; }
        .controls { padding: 14px; }
        .controls label { display: block; margin-bottom: 13px; font-size: 12px; font-weight: 600; color: #374151; }
        .controls select { display: block; width: 100%; margin-top: 5px; padding: 8px 9px; border: 1px solid #d1d5db; border-radius: 6px; background: #fff; color: #111827; }
        .section-title { margin: 18px 0 8px; padding-top: 14px; border-top: 1px solid #e5e7eb; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; }
        .session-buttons { display: flex; gap: 7px; }
        .session-buttons button { flex: 1; }
        details { margin-top: 14px; border-top: 1px solid #e5e7eb; padding-top: 12px; }
        summary { cursor: pointer; font-size: 12px; font-weight: 700; color: #374151; }
        #variables { margin: 10px 0 0; }
        #variables div { display: flex; justify-content: space-between; gap: 10px; padding: 5px 0; border-bottom: 1px solid #f3f4f6; font-size: 11px; }
        #variables dt { color: #6b7280; }
        #variables dd { margin: 0; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; }
        .page-links { display: grid; grid-template-columns: 1fr 1fr; gap: 6px; margin-top: 10px; }
        .page-links button { font-size: 11px; }
        .expand-button { display: none; position: absolute; right: 8px; top: 8px; z-index: 5; border: 1px solid #d1d5db; background: #fff; border-radius: 6px; box-shadow: 0 2px 8px rgba(0,0,0,.12); }
        @media (max-width: 760px) {
            .brand span, .header-actions span { display: none; }
            .side-panel { position: absolute; right: 0; top: 0; bottom: 0; z-index: 10; box-shadow: -4px 0 16px rgba(0,0,0,.12); }
            .side-panel.collapsed { width: 0; }
            .expand-button { display: block; }
        }
    </style>
</head>
<body>
<header class="shell-header">
    <div class="brand"><strong>MikroTik Hotspot Emulator</strong><span>Test the current <code>mt_hotspot/</code> files in your browser.</span></div>
    <div class="header-actions"><span><?= e($data['user']['name']) ?></span><a href="/dashboard">Dashboard</a></div>
</header>
<main class="workspace">
    <section class="portal-area">
        <div class="portal-toolbar"><span id="load-status" role="status">Ready</span><button type="button" id="reload-page">Reload</button></div>
        <iframe id="portal" title="MikroTik hotspot portal"></iframe>
    </section>
    <aside class="side-panel" id="side-panel">
        <div class="panel-header"><div><strong>Emulator</strong><small>RouterOS environment</small></div><button type="button" class="icon-button" id="collapse-panel" aria-label="Collapse emulator panel">›</button></div>
        <div class="controls">
            <label>Platform<select id="platform-select"></select></label>
            <label>Router<select id="router-select"></select></label>
            <label>Vendo<select id="vendo-select"></select></label>
            <label>Page<select id="page-select"></select></label>
            <div class="section-title">Session</div>
            <div class="session-buttons"><button type="button" data-page="login.html">Unauthenticated</button><button type="button" data-page="status.html">Authenticated</button></div>
            <details class="variables" open><summary>RouterOS variables</summary><dl id="variables"></dl></details>
            <details><summary>Test pages</summary><div id="page-links" class="page-links"></div></details>
        </div>
    </aside>
    <button type="button" class="expand-button" id="expand-panel" aria-label="Open emulator panel">‹</button>
</main>
<script>window.PIXIEPOINT_EMULATOR = <?= json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;</script>
<script>
(() => {
    'use strict';
    const config = window.PIXIEPOINT_EMULATOR || {};
    const portal = document.getElementById('portal');
    const platform = document.getElementById('platform-select');
    const router = document.getElementById('router-select');
    const vendo = document.getElementById('vendo-select');
    const page = document.getElementById('page-select');
    const variables = document.getElementById('variables');
    const pageLinks = document.getElementById('page-links');
    const status = document.getElementById('load-status');
    const panel = document.getElementById('side-panel');
    const routers = [config.sampleRouter || { id: 0, name: 'Sample Router', identity: 'PIXIEPOINT-DEMO', publicHost: '192.168.88.1' }].concat(config.routers || []);
    const vendos = [config.sampleVendo || { id: 0, routerId: 0, name: 'Sample Vendo' }].concat(config.vendos || []);
    const pages = config.pages || [];

    function addOptions(select, items, label) {
        select.innerHTML = '';
        items.forEach((item) => {
            const option = document.createElement('option');
            option.value = String(item.id ?? item);
            option.textContent = item.name || item;
            select.appendChild(option);
        });
        if (!items.length) {
            const option = document.createElement('option');
            option.textContent = `No ${label}`;
            select.appendChild(option);
        }
    }

    addOptions(platform, config.platforms || [{ id: 'mikrotik', name: 'MikroTik' }], 'platforms');
    addOptions(router, routers, 'routers');
    addOptions(vendo, vendos, 'Vendos');
    addOptions(page, pages.map((name) => ({ id: name, name })), 'pages');

    pages.forEach((name) => {
        const button = document.createElement('button');
        button.type = 'button';
        button.textContent = name;
        button.addEventListener('click', () => { page.value = name; loadPage(); });
        pageLinks.appendChild(button);
    });

    function selected(list, select) {
        return list.find((item) => String(item.id) === String(select.value)) || list[0];
    }

    function context() {
        const r = selected(routers, router);
        const v = vendos.find((item) => String(item.id) === String(vendo.value)) || vendos.find((item) => String(item.routerId) === String(r?.id)) || vendos[0];
        const authenticated = page.value === 'status.html' || page.value === 'logout.html';
        const loginUrl = `${location.origin}/emulator/?page=login.html`;
        const statusUrl = `${location.origin}/emulator/?page=status.html`;
        return {
            mac: 'AA:BB:CC:DD:EE:FF',
            'mac-esc': encodeURIComponent('AA:BB:CC:DD:EE:FF'),
            ip: '192.168.88.100',
            username: authenticated ? 'demo-user' : '',
            identity: r?.identity || 'PIXIEPOINT-DEMO',
            'interface-name': v?.interfaceName || 'bridge-hotspot',
            'server-address': r?.publicHost || v?.serverIp || '192.168.88.1',
            'session-time-left-secs': authenticated ? '3600' : '0',
            'uptime-secs': authenticated ? '600' : '0',
            'bytes-in': authenticated ? '1048576' : '0',
            'bytes-out': authenticated ? '524288' : '0',
            'remain-bytes-total': authenticated ? '10485760' : '0',
            'link-login': loginUrl,
            'link-login-only': loginUrl,
            'link-status': statusUrl,
            'link-logout': loginUrl,
            'link-orig': 'https://example.com/',
            'link-orig-esc': encodeURIComponent('https://example.com/'),
            'link-redirect': statusUrl,
            'chap-id': '00',
            'chap-challenge': 'emulator-challenge',
            error: '',
            'error-orig-esc': ''
        };
    }

    function showVariables(values) {
        variables.innerHTML = '';
        Object.entries(values).forEach(([key, value]) => {
            const row = document.createElement('div');
            const dt = document.createElement('dt');
            const dd = document.createElement('dd');
            dt.textContent = `$(${key})`;
            dd.textContent = value;
            row.append(dt, dd);
            variables.appendChild(row);
        });
    }

    function expandRouterOS(source, values) {
        return source.replace(/\$\(([^)]+)\)/g, (match, key) => Object.prototype.hasOwnProperty.call(values, key) ? String(values[key]) : match);
    }

    function rewriteRelativeAssets(source) {
        const assetUrl = (name) => `${location.origin}/emulator/?asset=${encodeURIComponent(name)}`;
        return source.replace(/((?:src|href)=["'])(?!https?:|\/|data:|#)([^"']+)(["'])/gi, (_, prefix, name, suffix) => `${prefix}${assetUrl(name)}${suffix}`);
    }

    async function loadPage() {
        const values = context();
        showVariables(values);
        status.textContent = `Loading ${page.value}…`;
        try {
            const response = await fetch(`${location.origin}/emulator/?page=${encodeURIComponent(page.value)}&emulator=${Date.now()}`, { cache: 'no-store' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            let source = expandRouterOS(await response.text(), values);
            source = rewriteRelativeAssets(source);
            portal.srcdoc = source;
            portal.onload = () => { status.textContent = `${page.value} · ready`; };
        } catch (error) {
            status.textContent = `Failed to load ${page.value}`;
            portal.srcdoc = `<body style="font-family:system-ui;padding:2rem"><h2>Emulator error</h2><p>${error.message}</p></body>`;
        }
    }

    [platform, router, vendo, page].forEach((select) => select.addEventListener('change', loadPage));
    document.getElementById('reload-page').addEventListener('click', loadPage);
    document.querySelectorAll('[data-page]').forEach((button) => button.addEventListener('click', () => { page.value = button.dataset.page; loadPage(); }));
    document.getElementById('collapse-panel').addEventListener('click', () => panel.classList.add('collapsed'));
    document.getElementById('expand-panel').addEventListener('click', () => panel.classList.remove('collapsed'));
    loadPage();
})();
</script>
</body>
</html>
<?php exit;
    }

    private function hotspotPage(string $page): never
    {
        if (!in_array($page, self::PAGES, true)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Hotspot page not found';
            exit;
        }

        $path = dirname(__DIR__, 2) . '/mt_hotspot/' . $page;
        if (!is_file($path)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Hotspot page not found';
            exit;
        }

        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($path);
        exit;
    }

    private function asset(string $asset): never
    {
        $asset = ltrim(str_replace('\\', '/', $asset), '/');
        if ($asset !== basename($asset) || $asset !== 'md5.js') {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Hotspot asset not found';
            exit;
        }

        $path = dirname(__DIR__, 2) . '/mt_hotspot/' . $asset;
        if (!is_file($path)) {
            http_response_code(404);
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Hotspot asset not found';
            exit;
        }

        header('Content-Type: application/javascript; charset=utf-8');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        readfile($path);
        exit;
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
