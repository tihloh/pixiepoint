(function () {
  'use strict';
  const hostedOrigin = window.PIXIEPOINT_HOSTED_ORIGIN || 'https://hs.portalx.win',
    bootstrapVersion = '5.3.8',
    version = Date.now();
  const isLogin = !!window.PIXIEPOINT_CONTEXT,
    isStatus = !!window.PIXIEPOINT_SESSION;
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
        x.status >= 200 && x.status < 300
          ? resolve(x.responseText)
          : reject(new Error('HTTP ' + x.status));
      };
      x.onerror = x.ontimeout = function () {
        reject(new Error('Request failed'));
      };
      x.send();
    });
  }
  function randomVoucher() {
    const a = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789',
      b = new Uint8Array(6);
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
    voucher = String(voucher || '')
      .trim()
      .toUpperCase();
    if (!voucher || (!force && input.value.trim() !== '')) return;
    input.value = voucher;
    voucherResolved = true;
  }
  function applyDeviceProfile(p) {
    if (!isLogin || !p || !p.ok) return;
    const v = String(p.saved_voucher || '').trim();
    if (v) {
      setVoucher(v, true);
      return;
    }
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
        v: String(version),
      });
    root.innerHTML = await request(`${hostedOrigin}/hotspot/compat?${q.toString()}`, 'text/html');
    window.PIXIEPOINT_VENDOS = Array.from(root.querySelectorAll('#compat-vendo option')).map(
      function (o) {
        return {
          id: o.value,
          name: o.textContent.trim(),
          baseUrl: o.dataset.baseUrl || '',
          passwordMode: o.dataset.passwordMode || 'blank',
          chargingEnabled: o.dataset.charging === '1',
          eloadEnabled: o.dataset.eload === '1',
        };
      },
    );
  }
  async function loadPortal() {
    if (started) return;
    started = true;
    status('Hosted portal found · loading…');
    try {
      await Promise.all([
        loadStyle(
          `https://cdn.jsdelivr.net/npm/bootstrap@${bootstrapVersion}/dist/css/bootstrap.min.css`,
          'pixiepoint-bootstrap-css',
        ),
        loadStyle(`${hostedOrigin}/assets/app.css?v=${version}`, 'pixiepoint-css'),
      ]);
      await loadScript(
        `https://cdn.jsdelivr.net/npm/bootstrap@${bootstrapVersion}/dist/js/bootstrap.bundle.min.js`,
        'pixiepoint-bootstrap-js',
      );
      if (isLogin) {
        await loadLoginMarkup();
        await loadScript(`${hostedOrigin}/assets/juanfi-compat.js?v=${version}`, 'pixiepoint-app');
      } else if (isStatus)
        await loadScript(
          `${hostedOrigin}/assets/session-portal.js?v=${version}`,
          'pixiepoint-session',
        );
      await loadScript(
        `${hostedOrigin}/assets/device-info.js?v=${version}`,
        'pixiepoint-device-info',
      );
      if (isLogin) {
        if (window.PIXIEPOINT_DEVICE_PROFILE) applyDeviceProfile(window.PIXIEPOINT_DEVICE_PROFILE);
        setTimeout(ensureVoucherFallback, 1500);
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
      try {
        h = JSON.parse(x.responseText);
      } catch (_) {}
      if (x.status >= 200 && x.status < 300 && h && h.ready === true) {
        loadPortal();
        return;
      }
      status('Hosted portal unavailable · retrying…');
      clearTimeout(retryTimer);
      retryTimer = setTimeout(check, 4000);
    };
    x.onerror = x.ontimeout = function () {
      status(
        navigator.onLine === false
          ? 'No network connection · retrying…'
          : 'Hosted portal unavailable · retrying…',
      );
      clearTimeout(retryTimer);
      retryTimer = setTimeout(check, 4000);
    };
    x.send();
  }
  window.addEventListener('pixiepoint:device-profile', function (e) {
    applyDeviceProfile(e.detail || {});
  });
  window.addEventListener('online', check);
  check();
})();
