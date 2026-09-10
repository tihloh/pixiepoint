(() => {
    const pages = [
        'login.html',
        'status.html',
        'logout.html',
        'alogin.html',
        'redirect.html',
        'error.html',
        'flogin.html',
        'rlogin.html',
    ];

    // RouterOS hotspot variables used by the current mt_hotspot files.
    // Unknown variables are intentionally left untouched so missing emulator
    // coverage is immediately visible while developing a hotspot page.
    const vars = {
        'mac': 'AA:BB:CC:DD:EE:FF',
        'mac-esc': 'AA%3ABB%3ACC%3ADD%3AEE%3AFF',
        'ip': '192.168.88.100',
        'hostname': 'android-client',
        'username': 'demo-user',
        'domain': 'hotspot.local',
        'identity': 'PIXIEPOINT-DEMO',
        'interface-name': 'bridge-hotspot',
        'server-name': 'hotspot1',
        'server-address': '192.168.88.1',
        'link-login': 'http://192.168.88.1/login',
        'link-login-only': 'http://192.168.88.1/login',
        'link-logout': 'http://192.168.88.1/logout',
        'link-status': 'http://192.168.88.1/status',
        'link-orig': 'http://example.com/',
        'link-orig-esc': 'http%3A%2F%2Fexample.com%2F',
        'link-redirect': 'http://example.com/',
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
        'chap-challenge': '0123456789abcdef0123456789abcdef'
    };

    const iframe = document.getElementById('portal');
    const select = document.getElementById('page-select');
    const nav = document.getElementById('page-nav');
    const status = document.getElementById('load-status');
    const variables = document.getElementById('variables');

    function expand(text) {
        return text.replace(/\$\(([^)]+)\)/g, (match, key) => {
            return Object.prototype.hasOwnProperty.call(vars, key) ? vars[key] : match;
        });
    }

    function addBase(html) {
        if (/<base\b/i.test(html)) return html;
        return html.replace(/<head(\s[^>]*)?>/i, match => `${match}\n    <base href="../">`);
    }

    function rewriteRouterLinks(doc) {
        // The real RouterOS values point at the router. In a browser emulator,
        // keep navigation inside the emulator instead of leaving the browser.
        doc.querySelectorAll('a[href], form[action]').forEach(element => {
            const attribute = element.matches('a[href]') ? 'href' : 'action';
            const value = element.getAttribute(attribute) || '';

            if (value === vars['link-login'] || value === vars['link-login-only']) {
                element.setAttribute(attribute, '../emulator/?page=login.html');
            } else if (value === vars['link-status']) {
                element.setAttribute(attribute, '../emulator/?page=status.html');
            } else if (value === vars['link-logout']) {
                element.setAttribute(attribute, '../emulator/?page=logout.html');
            }
        });
    }

    function installFormSimulation(doc) {
        doc.querySelectorAll('form').forEach(form => {
            form.addEventListener('submit', event => {
                event.preventDefault();
                if (form.action.includes('/login')) {
                    window.history.replaceState({}, '', '?page=status.html');
                    loadPage('status.html');
                }
            });
        });
    }

    function loadPage(page) {
        if (!pages.includes(page)) page = 'login.html';
        status.textContent = `Loading ${page}…`;
        select.value = page;

        fetch(`../${page}?emulator=${Date.now()}`, { cache: 'no-store' })
            .then(response => {
                if (!response.ok) throw new Error(`${response.status} ${response.statusText}`);
                return response.text();
            })
            .then(source => {
                const html = addBase(expand(source));
                iframe.srcdoc = html;

                iframe.onload = () => {
                    try {
                        rewriteRouterLinks(iframe.contentDocument);
                        installFormSimulation(iframe.contentDocument);
                        status.textContent = `${page} loaded from ../${page}`;
                    } catch (error) {
                        status.textContent = `${page} loaded; browser restrictions prevented page hooks`;
                    }
                };
            })
            .catch(error => {
                status.textContent = `Failed to load ${page}: ${error.message}`;
                iframe.srcdoc = `<pre style="padding:20px">${error.message}</pre>`;
            });
    }

    pages.forEach(page => {
        const option = document.createElement('option');
        option.value = page;
        option.textContent = page;
        select.appendChild(option);

        const link = document.createElement('a');
        link.href = `?page=${encodeURIComponent(page)}`;
        link.textContent = page;
        link.dataset.page = page;
        link.addEventListener('click', event => {
            event.preventDefault();
            window.history.pushState({}, '', `?page=${encodeURIComponent(page)}`);
            loadPage(page);
        });
        nav.appendChild(link);
    });

    select.addEventListener('change', () => {
        window.history.pushState({}, '', `?page=${encodeURIComponent(select.value)}`);
        loadPage(select.value);
    });

    document.getElementById('reload-page').addEventListener('click', () => {
        loadPage(select.value);
    });

    variables.textContent = JSON.stringify(vars, null, 2);

    window.MikroTik = {
        variables: Object.freeze({ ...vars }),
        pages: Object.freeze([...pages]),
        expand,
        loadPage,
    };

    const requestedPage = new URLSearchParams(window.location.search).get('page') || 'login.html';
    loadPage(requestedPage);
})();
