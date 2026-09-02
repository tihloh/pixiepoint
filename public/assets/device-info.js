(function () {
  'use strict';

  const context = window.PIXIEPOINT_SESSION || window.PIXIEPOINT_CONTEXT || {};
  const hostedOrigin = window.PIXIEPOINT_HOSTED_ORIGIN || 'https://hs.portalx.win';
  const uuidKey = 'pixiepoint:device-uuid';

  function number(value) {
    value = Number(value);
    return Number.isFinite(value) && value > 0 ? Math.floor(value) : 0;
  }
  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, function (char) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[char];
    });
  }
  function storedUuid() {
    try {
      return String(localStorage.getItem(uuidKey) || '')
        .trim()
        .toLowerCase();
    } catch (_) {
      return '';
    }
  }
  function rememberUuid(uuid) {
    uuid = String(uuid || '')
      .trim()
      .toLowerCase();
    if (!uuid) return;
    try {
      localStorage.setItem(uuidKey, uuid);
    } catch (_) {}
  }

  function findCard() {
    return (
      document.getElementById('compat-app') ||
      document.querySelector('.portal .card .card-body') ||
      document.querySelector('.portal .card') ||
      document.querySelector('.portal')
    );
  }

  function row(label, value) {
    return `<div class="d-flex justify-content-between gap-3 py-1 border-bottom border-secondary-subtle small"><span class="text-body-secondary">${escapeHtml(label)}</span><strong class="text-end text-break">${escapeHtml(value || '—')}</strong></div>`;
  }
  function render(data) {
    const card = findCard();
    if (!card || document.getElementById('pp-device-info')) return;
    const device = data.device || {},
      account =
        data.account && data.account.linked
          ? data.account.name || 'Linked account'
          : 'Guest device',
      voucher = String(data.saved_voucher || '').trim(),
      points = number(data.points);
    const panel = document.createElement('details');
    panel.id = 'pp-device-info';
    panel.className = 'border rounded-3 mt-3 px-3 py-2';
    panel.open = false;
    panel.innerHTML = `<summary class="fw-bold py-1">Device details</summary><div class="pt-2"><div class="border rounded-3 p-3 mb-2 text-center bg-body-tertiary"><span class="text-body-secondary small d-block">Points</span><strong class="d-block fs-2 lh-1 mt-1">${points}</strong><span class="small text-body-secondary">reward points</span></div>${row('Device / Account', account)}${row('IP Address', device.ip || context.ip || '—')}${row('MAC Address', device.mac || context.mac || '—')}<div class="py-2 border-bottom border-secondary-subtle small"><span class="text-body-secondary d-block mb-1">Last voucher</span><span class="badge text-bg-primary">${escapeHtml(voucher || 'None')}</span></div>${row('UUID', device.uuid || '—')}</div>`;
    card.appendChild(panel);
  }
  function publish(data) {
    const uuid = data && data.device && data.device.uuid;
    if (uuid) rememberUuid(uuid);
    window.PIXIEPOINT_DEVICE_PROFILE = data;
    window.dispatchEvent(new CustomEvent('pixiepoint:device-profile', { detail: data }));
  }
  function requestProfile(uuid, allowMacFallback) {
    const query = new URLSearchParams({
      uuid: uuid || '',
      mac: uuid ? '' : context.mac || '',
      ip: context.ip || '',
      router_identity: context.routerIdentity || '',
      interface: context.interfaceName || '',
    });
    const xhr = new XMLHttpRequest();
    xhr.open('GET', `${hostedOrigin}/hotspot/device-info?${query.toString()}`, true);
    xhr.timeout = 5000;
    xhr.setRequestHeader('Accept', 'application/json');
    xhr.onload = function () {
      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          const data = JSON.parse(xhr.responseText);
          if (data && data.ok) {
            publish(data);
            render(data);
            return;
          }
        } catch (_) {}
      }
      if (uuid && allowMacFallback && context.mac) {
        try {
          localStorage.removeItem(uuidKey);
        } catch (_) {}
        requestProfile('', false);
      }
    };
    xhr.onerror = xhr.ontimeout = function () {
      if (uuid && allowMacFallback && context.mac) requestProfile('', false);
    };
    xhr.send();
  }
  function saveVoucher(voucher) {
    voucher = String(voucher || '')
      .trim()
      .toUpperCase();
    if (!voucher) return Promise.resolve(false);
    const body = new URLSearchParams({
      uuid: storedUuid(),
      voucher: voucher,
      mac: context.mac || '',
      ip: context.ip || '',
      router_identity: context.routerIdentity || '',
      interface: context.interfaceName || '',
    });
    return fetch(`${hostedOrigin}/hotspot/device-voucher`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8' },
      body: body.toString(),
      cache: 'no-store',
      keepalive: true,
    })
      .then(function (response) {
        if (!response.ok) throw new Error('Voucher was not saved.');
        return response.json();
      })
      .then(function (data) {
        if (!data || !data.ok) throw new Error('Voucher was not saved.');
        if (data.device && data.device.uuid) rememberUuid(data.device.uuid);
        if (window.PIXIEPOINT_DEVICE_PROFILE)
          window.PIXIEPOINT_DEVICE_PROFILE.saved_voucher = data.saved_voucher || voucher;
        return true;
      });
  }
  function installVoucherSave() {
    document.addEventListener(
      'submit',
      function (event) {
        const form = event.target;
        if (!form || form.id !== 'compat-voucher-form') return;
        if (form.dataset.ppVoucherSaved === '1') {
          delete form.dataset.ppVoucherSaved;
          return;
        }
        const input = document.getElementById('compat-voucher'),
          voucher = input ? input.value : '';
        if (!String(voucher || '').trim()) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        saveVoucher(voucher)
          .catch(function () {
            return false;
          })
          .then(function () {
            form.dataset.ppVoucherSaved = '1';
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
          });
      },
      true,
    );
  }
  function load() {
    const uuid = storedUuid();
    if (uuid) {
      requestProfile(uuid, true);
      return;
    }
    if (context.mac) requestProfile('', false);
  }
  installVoucherSave();
  let attempts = 0;
  const waitForPortal = setInterval(function () {
    attempts++;
    if (findCard() && window.bootstrap) {
      clearInterval(waitForPortal);
      load();
      return;
    }
    if (attempts >= 30) clearInterval(waitForPortal);
  }, 200);
})();
