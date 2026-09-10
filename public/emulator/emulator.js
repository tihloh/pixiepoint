(() => {
    'use strict';

    const config = window.PIXIEPOINT_EMULATOR || {};
    const portal = document.getElementById('portal');
    const platform = document.getElementById('platform-select');
    const router = document.getElementById('router-select');
    const vendo = document.getElementById('vendo-select');
    const page = document.getElementById('page-select');
    const variables = document.getElementById('variables');
    const status = document.getElementById('load-status');
    const panel = document.getElementById('side-panel');

    const routers = [config.sampleRouter || { id: 0, name: 'Sample Router', identity: 'PIXIEPOINT-DEMO', serverAddress: '192.168.88.1' }].concat(config.routers || []);
    const vendos = [config.sampleVendo || { id: 0, routerId: 0, name: 'Sample Vendo' }].concat(config.vendos || []);
    const pages = config.pages || ['login.html', 'status.html', 'logout.html', 'alogin.html', 'redirect.html', 'error.html', 'flogin.html', 'rlogin.html'];

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

    function selected(list, select) {
        return list.find((item) => String(item.id) === String(select.value)) || list[0];
    }

    function context() {
        const r = selected(routers, router);
        const v = vendos.find((item) => String(item.id) === String(vendo.value)) || vendos.find((item) => String(item.routerId) === String(r?.id)) || vendos[0];
        const authenticated = page.value === 'status.html' || page.value === 'logout.html';
        const username = authenticated ? 'demo-user' : '';
        const mac = 'AA:BB:CC:DD:EE:FF';
        const ip = '192.168.88.100';
        const loginUrl = `${location.origin}/emulator/?page=login.html`;
        const statusUrl = `${location.origin}/emulator/?page=status.html`;
        return {
            mac,
            'mac-esc': encodeURIComponent(mac),
            ip,
            username,
            identity: r?.identity || 'PIXIEPOINT-DEMO',
            'interface-name': v?.interfaceName || 'bridge-hotspot',
            'server-address': r?.serverAddress || v?.serverIp || '192.168.88.1',
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

    async function loadPage() {
        const values = context();
        showVariables(values);
        status.textContent = `Loading ${page.value}…`;
        try {
            const response = await fetch(`${config.hotspotBaseUrl || '/mt_hotspot/'}${page.value}?emulator=${Date.now()}`, { cache: 'no-store' });
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const source = expandRouterOS(await response.text(), values);
            const doc = portal.contentDocument;
            portal.srcdoc = source.replace('<head>', `<head><base href="${new URL(config.hotspotBaseUrl || '/mt_hotspot/', location.origin).href}">`);
            portal.onload = () => {
                status.textContent = `${page.value} · ready`;
            };
        } catch (error) {
            status.textContent = `Failed to load ${page.value}`;
            portal.srcdoc = `<body style="font-family:system-ui;padding:2rem"><h2>Emulator error</h2><p>${error.message}</p></body>`;
        }
    }

    [platform, router, vendo, page].forEach((select) => select.addEventListener('change', loadPage));
    document.getElementById('reload-page').addEventListener('click', loadPage);
    document.querySelectorAll('[data-page]').forEach((button) => button.addEventListener('click', () => {
        page.value = button.dataset.page;
        loadPage();
    }));
    document.getElementById('collapse-panel').addEventListener('click', () => panel.classList.add('collapsed'));
    document.getElementById('expand-panel').addEventListener('click', () => panel.classList.remove('collapsed'));

    loadPage();
})();
