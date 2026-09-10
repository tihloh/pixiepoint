(() => {
    const config = window.PIXIEPOINT_EMULATOR || {};
    const pages = config.pages || [
        'login.html', 'status.html', 'logout.html', 'alogin.html',
        'redirect.html', 'error.html', 'flogin.html', 'rlogin.html',
    ];

    const sampleRouter = config.sampleRouter || {
        id: 0,
        name: 'Sample Router',
        identity: 'PIXIEPOINT-DEMO',
        publicHost: '192.168.88.1',
    };
    const sampleVendo = config.sampleVendo || {
        id: 0,
        routerId: 0,
        name: 'Sample Vendo',
        baseUrl: 'https://example.invalid',
        serverIp: '192.168.88.1',
        clientSubnet: '192.168.88.0/24',
        interfaceName: 'bridge-hotspot',
        passwordMode: 'blank',
        chargingEnabled: true,
        eloadEnabled: true,
    };

    const routers = [sampleRouter, ...(config.routers || [])];
    const vendos = [sampleVendo, ...(config.vendos || [])];

    const iframe = document.getElementById('portal');
    const platformSelect = document.getElementById('platform-select');
    const routerSelect = document.getElementById('router-select');
    const vendoSelect = document.getElementById('vendo-select');
    const pageSelect = document.getElementById('page-select');
    const status = document.getElementById('load-status');
    const variables = document.getElementById('variables');
    const pageLinks = document.getElementById('page-links');
    const sidePanel = document.getElementById('side-panel');

    const state = {
        platform: 'mikrotik',
        routerId: 0,
        vendoId: 0,
        page: new URLSearchParams(window.location.search).get('page') || 'login.html',
    };

    const routerById = id => routers.find(router => Number(router.id) === Number(id)) || sampleRouter;
    const vendoById = id => vendos.find(vendo => Number(vendo.id) === Number(id)) || sampleVendo;

    function addOption(select, value, label, selected = false) {
        const option = document.createElement('option');
        option.value = String(value);
        option.textContent = label;
        option.selected = selected;
        select.appendChild(option);
    }

    function populateControls() {
        (config.platforms || [{ id: 'mikrotik', name: 'MikroTik' }]).forEach(platform => {
            addOption(platformSelect, platform.id, platform.name, platform.id === state.platform);
        });

        routers.forEach(router => {
            addOption(routerSelect, router.id, router.id === 0 ? router.name : `${router.name} · ${router.identity}`, Number(router.id) === Number(state.routerId));
        });

        pages.forEach(page => addOption(pageSelect, page, page, page === state.page));
        pages.forEach(page => {
            const link = document.createElement('button');
            link.type = 'button';
            link.textContent = page;
            link.dataset.page = page;
            pageLinks.appendChild(link);
            link.addEventListener('click', () => setPage(page));
        });

        refreshVendos();
    }

    function refreshVendos() {
        const current = vendoById(state.vendoId);
        vendoSelect.replaceChildren();
        const available = vendos.filter(vendo => Number(vendo.routerId) === Number(state.routerId));
        const options = state.routerId === 0 ? [sampleVendo] : available;

        options.forEach(vendo => {
            addOption(vendoSelect, vendo.id, vendo.id === 0 ? vendo.name : vendo.name, Number(vendo.id) === Number(current.id));
        });

        if (!options.some(vendo => Number(vendo.id) === Number(state.vendoId))) {
            state.vendoId = Number(options[0]?.id ?? 0);
        }
        vendoSelect.value = String(state.vendoId);
    }

    function buildVariables() {
        const router = routerById(state.routerId);
        const vendo = vendoById(state.vendoId);
        const serverAddress = vendo.serverIp || router.publicHost || '192.168.88.1';
        const mac = 'AA:BB:CC:DD:EE:FF';
        const macEsc = encodeURIComponent(mac);

        return {
            'mac': mac,
            'mac-esc': macEsc,
            'ip': '192.168.88.100',
            'hostname': 'browser-emulator',
            'username': state.page === 'status.html' ? 'demo-user' : '',
            'domain': 'hotspot.local',
            'identity': router.identity,
            'interface-name': vendo.interfaceName || 'bridge-hotspot',
            'server-name': 'hotspot1',
            'server-address': serverAddress,
            'link-login': `${window.location.origin}/emulator/?page=login.html`,
            'link-login-only': `${window.location.origin}/emulator/?page=login.html`,
            'link-logout': `${window.location.origin}/emulator/?page=logout.html`,
            'link-status': `${window.location.origin}/emulator/?page=status.html`,
            'link-orig': 'https://example.com/',
            'link-orig-esc': encodeURIComponent('https://example.com/'),
            'link-redirect': `${window.location.origin}/emulator/?page=status.html`,
            'session-id': 'demo-session-001',
            'session-time-left': '00:42:30',
            'session-time-left-secs': '2550',
            'session-timeout': '01:00:00',
            'uptime': '00:17:30',
            'uptime-secs': '1050',
            'idle-timeout': '00:15:00',
            'bytes-in': '1048576',
            'bytes-out': '5242880',
            'bytes-in-nice': '1.0 MiB',
            'bytes-out-nice': '5.0 MiB',
            'remain-bytes-in': '9.0 MiB',
            'remain-bytes-out': '45.0 MiB',
            'remain-bytes-total': '54.0 MiB',
            'refresh-timeout': '00:01:00',
            'refresh-timeout-secs': '60',
            'error': 'Demo error message',
            'error-orig': 'Demo error message',
            'error-orig-esc': 'Demo%20error%20message',
            'popup': 'true',
            'plain-pass': 'demo-password',
            'chap-id': '1',
            'chap-challenge': '0123456789abcdef0123456789abcdef',
        };
    }

    function expand(text, vars) {
        return text.replace(/\$\(([^)]+)\)/g, (match, key) => (
            Object.prototype.hasOwnProperty.call(vars, key) ? vars[key] : match
        ));
    }

    function addBase(html) {
        if (/<base\b/i.test(html)) return html;
        return html.replace(/<head(\s[^>]*)?>/i, match => `${match}\n    <base href="${config.hotspotBaseUrl || '/mt_hotspot/'}">`);
    }

    function emulatorUrl(page) {
        return `${config.emulatorUrl || '/emulator/'}?page=${encodeURIComponent(page)}`;
    }

    function rewriteRouterLinks(doc) {
        doc.querySelectorAll('a[href], form[action]').forEach(element => {
            const attribute = element.matches('a[href]') ? 'href' : 'action';
            const value = element.getAttribute(attribute) || '';
            const target = [
                ['login.html', 'login'],
                ['status.html', 'status'],
                ['logout.html', 'logout'],
            ];

            if (value === window.location.origin + '/emulator/?page=login.html' || /\/login(?:\.html)?$/i.test(value)) {
                element.setAttribute(attribute, emulatorUrl('login.html'));
            } else if (value === window.location.origin + '/emulator/?page=status.html' || /\/status(?:\.html)?$/i.test(value)) {
                element.setAttribute(attribute, emulatorUrl('status.html'));
            } else if (value === window.location.origin + '/emulator/?page=logout.html' || /\/logout(?:\.html)?$/i.test(value)) {
                element.setAttribute(attribute, emulatorUrl('logout.html'));
            }
        });
    }

    function installFormSimulation(doc) {
        doc.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', event => {
                event.preventDefault();
                state.page = form.action.includes('logout') ? 'logout.html' : 'status.html';
                syncUrl();
                loadPage(state.page);
            });
        });
    }

    function renderVariables(vars) {
        variables.replaceChildren();
        Object.entries(vars).forEach(([key, value]) => {
            const term = document.createElement('dt');
            const definition = document.createElement('dd');
            term.textContent = `$(${key})`;
            definition.textContent = value;
            variables.append(term, definition);
        });
    }

    function syncUrl() {
        const query = new URLSearchParams({ page: state.page });
        window.history.replaceState({}, '', `${config.emulatorUrl || '/emulator/'}?${query}`);
    }

    function loadPage(page) {
        if (!pages.includes(page)) page = 'login.html';
        state.page = page;
        pageSelect.value = page;
        const vars = buildVariables();
        renderVariables(vars);
        syncUrl();
        status.textContent = `Loading ${page}…`;

        fetch(`${config.hotspotBaseUrl || '/mt_hotspot/'}${page}?emulator=${Date.now()}`, { cache: 'no-store' })
            .then(response => {
                if (!response.ok) throw new Error(`${response.status} ${response.statusText}`);
                return response.text();
            })
            .then(source => {
                iframe.srcdoc = addBase(expand(source, vars));
                iframe.onload = () => {
                    try {
                        rewriteRouterLinks(iframe.contentDocument);
                        installFormSimulation(iframe.contentDocument);
                        status.textContent = `${page} · ${routerById(state.routerId).name} · ${vendoById(state.vendoId).name}`;
                    } catch (_) {
                        status.textContent = `${page} loaded`;
                    }
                };
            })
            .catch(error => {
                status.textContent = `Failed to load ${page}: ${error.message}`;
                iframe.srcdoc = `<pre style="padding:20px">${error.message}</pre>`;
            });
    }

    function setPage(page) {
        loadPage(page);
    }

    function setRouter(id) {
        state.routerId = Number(id);
        const available = vendos.filter(vendo => Number(vendo.routerId) === state.routerId);
        state.vendoId = Number((state.routerId === 0 ? sampleVendo : available[0])?.id ?? 0);
        refreshVendos();
        loadPage(state.page);
    }

    platformSelect.addEventListener('change', () => {
        state.platform = platformSelect.value;
        loadPage(state.page);
    });
    routerSelect.addEventListener('change', () => setRouter(routerSelect.value));
    vendoSelect.addEventListener('change', () => {
        state.vendoId = Number(vendoSelect.value);
        loadPage(state.page);
    });
    pageSelect.addEventListener('change', () => setPage(pageSelect.value));
    document.getElementById('reload-page').addEventListener('click', () => loadPage(state.page));

    document.querySelectorAll('.session-buttons [data-page]').forEach(button => {
        button.addEventListener('click', () => setPage(button.dataset.page));
    });

    document.getElementById('collapse-panel').addEventListener('click', () => {
        sidePanel.classList.add('collapsed');
        document.body.classList.add('emulator-panel-collapsed');
    });
    document.getElementById('expand-panel').addEventListener('click', () => {
        sidePanel.classList.remove('collapsed');
        document.body.classList.remove('emulator-panel-collapsed');
    });

    populateControls();
    refreshVendos();
    loadPage(state.page);

    window.MikroTik = {
        get state() { return { ...state }; },
        variables: buildVariables,
        pages: Object.freeze([...pages]),
        loadPage,
    };
})();
