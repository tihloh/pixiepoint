(function () {
  'use strict';

  const context = window.PIXIEPOINT_CONTEXT || {};
  const hostedOrigin = window.PIXIEPOINT_HOSTED_ORIGIN || 'https://hs.portalx.win';

  function storedUuid() {
    try {
      return String(localStorage.getItem('pixiepoint:device-uuid') || '')
        .trim()
        .toLowerCase();
    } catch (_) {
      return '';
    }
  }

  function rememberUuid(uuid) {
    uuid = String(uuid || '').trim().toLowerCase();
    if (!uuid) return;
    try {
      localStorage.setItem('pixiepoint:device-uuid', uuid);
    } catch (_) {}
  }

  function saveVoucher(voucher) {
    voucher = String(voucher || '').trim().toUpperCase();
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
        return true;
      });
  }

  document.addEventListener(
    'submit',
    function (event) {
      const form = event.target;
      if (!form || form.id !== 'compat-voucher-form') return;
      if (form.dataset.ppVoucherSaved === '1') {
        delete form.dataset.ppVoucherSaved;
        return;
      }

      const input = document.getElementById('compat-voucher');
      const voucher = input ? input.value : '';
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
})();
