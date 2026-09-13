(function () {
  'use strict';
  const hostedOrigin = window.PIXIEPOINT_HOSTED_ORIGIN || 'https://hs.portalx.win',
    version = Date.now();
  const isLogin = !!window.PIXIEPOINT_CONTEXT,
    isStatus = !!window.PIXIEPOINT_SESSION,
    serverRendered = !!window.PIXIEPOINT_SERVER_RENDERED;
  let started = false,
    retryTimer = 0,
    voucherResolved = false;

  function status(message) {
    const e = document.getElementById('boot-status');
    if (e) e.textContent = message;
  }

  function request(url, type) {
    return new Promise(function (resolve, reject) {
      const x = new XMLHttpRequest();
      x.open('GET', url, true);
      x.timeout = 7000;
      if (type) x.setRequestHeader('Accept', type);
      x.onload = function () {
        x.status >= 200 && x.status < 300 ? resolve(x.responseText) : reject(new Error('HTTP ' + x.status));
      };
      x.onerror = x.ontimeout = function () { reject(new Error('Request failed')); };
      x.send();
    });
  }

  function resolvedMac(context) {
    const c = context || {};
    const raw = String(c.mac || '').trim();
    if (raw && !raw.includes('$(')) return raw;

    const escaped = String(c.macEsc || '').trim();
    if (!escaped || escaped.includes('$(')) return '';
    try { return decodeURIComponent(escaped); } catch (_) { return escaped; }
  }

  function randomVoucher() {
    const a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789', b = new Uint8Array(6);
    if (window.crypto && crypto.getRandomValues) crypto.getRandomValues(b);
    else for (let i = 0; i < b.length; i++) b[i] = Math.floor(Math.random() * 256);
    let v = 'PP';
    for (let i = 0; i < b.length; i++) v += a[b[i] % a.length];
    return v;
  }

  function setVoucher(voucher, force) {
    if (!isLogin) return;
    const input = document.getElementById('compat-voucher');
    if (!input) return;
    voucher = String(voucher || '').trim().toUpperCase();
    if (!voucher || (!force && input.value.trim() !== '')) return;
    input.value = voucher;
    voucherResolved = true;
  }

  function applyDeviceProfile(p) {
    if (!isLogin || !p || !p.ok) return;
    const v = String(p.saved_voucher || '').trim();
    if (v) { setVoucher(v, true); return; }
    if (!voucherResolved) setVoucher(randomVoucher(), false);
  }

  function ensureVoucherFallback() {
    if (isLogin && !voucherResolved) setVoucher(randomVoucher(), false);
  }

  function loadStyle(href, id) {
    return new Promise(function (resolve, reject) {
      if (id && document.getElementById(id)) return resolve();
      const l = document.createElement('link');
      if (id) l.id = id;
      l.rel = 'stylesheet';
      l.href = href;
      l.onload = resolve;
      l.onerror = reject;
      document.head.appendChild(l);
    });
  }

  function loadScript(src, id) {
    return new Promise(function (resolve, reject) {
      if (id && document.getElementById(id)) return resolve();
      const s = document.createElement('script');
      if (id) s.id = id;
      s.src = src;
      s.onload = resolve;
      s.onerror = reject;
      document.head.appendChild(s);
    });
  }

  function copyAttributes(from, to) {
    Array.from(from.attributes || []).forEach(function (attr) {
      to.setAttribute(attr.name, attr.value);
    });
  }

  function clearFragmentAssets() {
    document.querySelectorAll('[data-pixiepoint-fragment-asset="1"]').forEach(function (node) {
      node.remove();
    });
  }

  function appendFragmentStyle(node) {
    return new Promise(function (resolve) {
      const clone = document.createElement(node.tagName.toLowerCase());
      copyAttributes(node, clone);
      clone.setAttribute('data-pixiepoint-fragment-asset', '1');

      if (node.tagName.toLowerCase() === 'style') {
        clone.textContent = node.textContent || '';
        document.head.appendChild(clone);
        resolve();
        return;
      }

      clone.addEventListener('load', resolve, { once: true });
      clone.addEventListener('error', resolve, { once: true });
      document.head.appendChild(clone);
    });
  }

  function appendFragmentScript(node) {
    return new Promise(function (resolve) {
      const script = document.createElement('script');
      copyAttributes(node, script);
      script.setAttribute('data-pixiepoint-fragment-asset', '1');

      if (node.src) {
        script.addEventListener('load', resolve, { once: true });
        script.addEventListener('error', resolve, { once: true });
        document.head.appendChild(script);
        return;
      }

      script.textContent = node.textContent || '';
      document.head.appendChild(script);
      resolve();
    });
  }

  async function mountFragment(root, html) {
    const template = document.createElement('template');
    template.innerHTML = html;

    const styles = Array.from(template.content.querySelectorAll('link[rel~="stylesheet"], style'));
    const scripts = Array.from(template.content.querySelectorAll('script'));

    styles.forEach(function (node) { node.remove(); });
    scripts.forEach(function (node) { node.remove(); });

    clearFragmentAssets();

    for (const style of styles) await appendFragmentStyle(style);

    root.innerHTML = '';
    root.appendChild(template.content.cloneNode(true));

    for (const script of scripts) await appendFragmentScript(script);
  }

  async function loadLoginMarkup() {
    const root = document.getElementById('pixiepoint-root');
    if (!root) throw new Error('Portal root missing');
    const c = window.PIXIEPOINT_CONTEXT || {},
      q = new URLSearchParams({
        fragment: '1',
        router_identity: c.routerIdentity || '',
        server_address: c.serverAddress || '',
        client_ip: c.ip || '',
        interface: c.interfaceName || '',
        mac: resolvedMac(c),
        v: String(version),
      });

    await mountFragment(root, await request(`${hostedOrigin}/hotspot/compat?${q.toString()}`, 'text/html'));

    ['chap-login', 'pap-login'].forEach(function (id) {
      const form = document.getElementById(id);
      if (!form) return;
      form.action = c.loginUrl || '';
      const dst = form.elements.namedItem('dst');
      if (dst) dst.value = c.originalUrl || '';
    });

    window.PIXIEPOINT_VENDOS = Array.from(root.querySelectorAll('#compat-vendo option')).map(function (o) {
      return {
        id: o.value,
        name: o.textContent.trim(),
        baseUrl: o.dataset.baseUrl || '',
        passwordMode: o.dataset.passwordMode || 'blank',
        chargingEnabled: o.dataset.charging === '1',
        eloadEnabled: o.dataset.eload === '1',
      };
    });
  }

  function readServerRenderedVendos(root) {
    window.PIXIEPOINT_VENDOS = Array.from(root.querySelectorAll('#compat-vendo option, #pp-vendo option')).map(function (o) {
      return {
        id: o.value,
        name: o.textContent.trim(),
        baseUrl: o.dataset.baseUrl || '',
        passwordMode: o.dataset.passwordMode || 'blank',
        chargingEnabled: o.dataset.charging === '1',
        eloadEnabled: o.dataset.eload === '1',
      };
    });
  }

  async function loadStatusMarkup() {
    const root = document.getElementById('pixiepoint-root');
    if (!root) throw new Error('Portal root missing');
    const c = window.PIXIEPOINT_SESSION || {},
      q = new URLSearchParams({
        fragment: '1',
        router_identity: c.routerIdentity || '',
        server_address: c.serverAddress || '',
        client_ip: c.ip || '',
        interface: c.interfaceName || '',
        mac: resolvedMac(c),
        v: String(version),
      });

    await mountFragment(root, await request(`${hostedOrigin}/hotspot/status?${q.toString()}`, 'text/html'));
    readServerRenderedVendos(root);
  }

  async function loadPortal() {
    if (started) return;
    started = true;
    status('Hosted portal found · loading…');
    try {
      await loadStyle(`${hostedOrigin}/assets/hotspot.css?v=${version}`, 'pixiepoint-hotspot-css');

      const root = document.querySelector('#pp-page') || document.getElementById('pixiepoint-root') || document.body;
      if (serverRendered) {
        readServerRenderedVendos(root);
        if (isLogin) {
          ['chap-login', 'pap-login'].forEach(function (id) {
            const form = document.getElementById(id), c = window.PIXIEPOINT_CONTEXT || {};
            if (!form) return;
            form.action = c.loginUrl || '';
            const dst = form.elements.namedItem('dst');
            if (dst) dst.value = c.originalUrl || '';
          });
        }
      } else if (isLogin) {
        await loadLoginMarkup();
      } else if (isStatus) {
        await loadStatusMarkup();
      }

      if (isLogin) {
        await loadScript(`${hostedOrigin}/assets/juanfi-compat.js?v=${version}`, 'pixiepoint-app');
        await loadScript(`${hostedOrigin}/assets/device-info.js?v=${version}`, 'pixiepoint-device-info');
        if (window.PIXIEPOINT_DEVICE_PROFILE) applyDeviceProfile(window.PIXIEPOINT_DEVICE_PROFILE);
        setTimeout(ensureVoucherFallback, 1500);
      } else if (isStatus) {
        await loadScript(`${hostedOrigin}/assets/session-portal.js?v=${version}`, 'pixiepoint-session');
      }
    } catch (_) {
      started = false;
      status('Hosted portal assets unavailable · retrying…');
      clearTimeout(retryTimer);
      retryTimer = setTimeout(check, 4000);
    }
  }

  function check() {
    if (started) return;
    const x = new XMLHttpRequest();
    x.open('GET', `${hostedOrigin}/hotspot/health?t=${Date.now()}`, true);
    x.timeout = 5000;
    x.setRequestHeader('Accept', 'application/json');
    x.onload = function () {
      let h = null;
      try { h = JSON.parse(x.responseText); } catch (_) {}
      if (x.status >= 200 && x.status < 300 && h && h.ready === true) { loadPortal(); return; }
      status('Hosted portal unavailable · retrying…');
      clearTimeout(retryTimer);
      retryTimer = setTimeout(check, 4000);
    };
    x.onerror = x.ontimeout = function () {
      status(navigator.onLine === false ? 'No network connection · retrying…' : 'Hosted portal unavailable · retrying…');
      clearTimeout(retryTimer);
      retryTimer = setTimeout(check, 4000);
    };
    x.send();
  }

  window.addEventListener('pixiepoint:device-profile', function (e) { applyDeviceProfile(e.detail || {}); });
  window.addEventListener('online', check);
  check();
})();
