(() => {
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
        'session-id': 'demo-session-001',
        'session-time-left': '00:42:30',
        'session-timeout': '01:00:00',
        'uptime': '00:17:30',
        'idle-timeout': '00:15:00',
        'bytes-in': '1048576',
        'bytes-out': '5242880',
        'bytes-in-nice': '1.0 MiB',
        'bytes-out-nice': '5.0 MiB',
        'remain-bytes-in': '9.0 MiB',
        'remain-bytes-out': '45.0 MiB',
        'refresh-timeout': '00:01:00',
        'refresh-timeout-secs': '60',
        'error': 'Demo error message',
        'popup': 'true',
        'plain-pass': 'demo-password'
    };

    function expand(text) {
        return text.replace(/\$\(([^)]+)\)/g, (match, key) => {
            return Object.prototype.hasOwnProperty.call(vars, key) ? vars[key] : match;
        });
    }

    function processDocument() {
        const walker = document.createTreeWalker(document.body, NodeFilter.SHOW_TEXT);
        const nodes = [];
        while (walker.nextNode()) nodes.push(walker.currentNode);
        nodes.forEach(node => {
            node.nodeValue = expand(node.nodeValue);
        });

        document.querySelectorAll('input, textarea, option').forEach(element => {
            for (const attribute of ['value', 'placeholder', 'label', 'title']) {
                if (element.hasAttribute(attribute)) {
                    element.setAttribute(attribute, expand(element.getAttribute(attribute)));
                }
            }
        });

        document.querySelectorAll('a[href], form[action]').forEach(element => {
            const attribute = element.tagName === 'A' ? 'href' : 'action';
            element.setAttribute(attribute, expand(element.getAttribute(attribute)));
        });
    }

    function installNavigation() {
        document.querySelectorAll('[data-mt-page]').forEach(link => {
            link.addEventListener('click', event => {
                event.preventDefault();
                const page = link.dataset.mtPage;
                window.location.href = page;
            });
        });
    }

    window.MikroTik = {
        variables: Object.freeze({ ...vars }),
        expand,
        navigate(page) { window.location.href = page; }
    };

    document.addEventListener('DOMContentLoaded', () => {
        processDocument();
        installNavigation();
    });
})();
