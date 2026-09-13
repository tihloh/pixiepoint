(function () {
  'use strict';

  var parentOrigin = '*', context = {}, vendos = [], selected = null;
  var pending = Object.create(null), sequence = 0, pollTimer = null, activeVoucher = '', transactionMode = 'internet';
  var totalCoinReceived = 0, finalizingTopup = false, startingTopup = false;
  var $ = function (id) { return document.getElementById(id); };

  function localRequest(path, method, data) {
    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      var query = method === 'GET' && data && Object.keys(data).length ? '?' + new URLSearchParams(data).toString() : '';
      if (!selected || !selected.baseUrl) return reject(new Error('No local vendo is selected.'));
      xhr.open(method || 'GET', selected.baseUrl + path + query, true);
      xhr.timeout = 7000;
      xhr.onload = function () {
        var body = xhr.responseText;
        try { body = JSON.parse(body); } catch (_) {}
        resolve({ ok: xhr.status >= 200 && xhr.status < 300, status: xhr.status, body: body });
      };
      xhr.onerror = function () { reject(new Error('The local vendo is unreachable.')); };
      xhr.ontimeout = function () { reject(new Error('The local vendo timed out.')); };
      if (method === 'POST') {
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send(new URLSearchParams(data || {}).toString());
      } else xhr.send();
    });
  }

  function alertMessage(message) {
    var el = $('compat-alert');
    if (!el) return;
    el.textContent = message || '';
    el.hidden = !message;
  }

  function rpc(path, method, data) {
    if (window.PIXIEPOINT_BOOTSTRAP) return localRequest(path, method || 'GET', data || {});
    return new Promise(function (resolve, reject) {
      var id = 'rpc-' + (++sequence) + '-' + Date.now();
      var timeout = setTimeout(function () {
        delete pending[id];
        reject(new Error('The local vendo did not respond.'));
      }, 9000);
      pending[id] = { resolve: resolve, reject: reject, timeout: timeout };
      window.parent.postMessage({ type: 'pixiepoint:request', id: id, vendoId: selected && selected.id, path: path, method: method || 'GET', data: data || {} }, parentOrigin);
    });
  }

  function responseData(result) {
    var body = result && result.body;
    if (body && typeof body === 'object') return body;
    if (typeof body !== 'string') return {};
    try { return JSON.parse(body); } catch (_) { return { raw: body }; }
  }

  function isTrue(value) { return value === true || value === 'true' || value === 1 || value === '1'; }
  function number(value) { value = Number(value); return Number.isFinite(value) && value > 0 ? value : 0; }
  function currentVoucher() { var input = $('compat-voucher'); return input ? input.value.trim().toUpperCase() : ''; }
  function setCurrentVoucher(voucher) {
    voucher = String(voucher || '').trim().toUpperCase();
    var input = $('compat-voucher');
    if (input && voucher) input.value = voucher;
    return voucher;
  }
  function setTopupActive(active) {
    if ($('compat-topup')) $('compat-topup').hidden = active;
    if ($('compat-connect')) $('compat-connect').hidden = active;
    if ($('compat-transaction')) $('compat-transaction').hidden = !active;
  }
  function setReady(ready, message) {
    if ($('compat-topup')) $('compat-topup').disabled = !ready;
    if ($('compat-rates')) $('compat-rates').disabled = !ready;
    if ($('compat-health')) {
      $('compat-health').textContent = message;
      $('compat-health').className = 'compat-status ' + (ready ? 'online' : 'offline');
    }
  }

  function health() {
    if (!selected) return;
    rpc('/health').then(function (result) {
      if (!result.ok) throw new Error('HTTP ' + result.status);
      setReady(true, selected.name + ' is ready');
      alertMessage('');
    }).catch(function () {
      setReady(false, selected.name + ' is unavailable');
      alertMessage('PixiePoint is online, but this browser cannot reach the local coin slot. Check its power, Wi-Fi connection, and address.');
    });
  }

  function selectVendo() {
    selected = vendos.filter(function (v) { return String(v.id) === String($('compat-vendo').value); })[0] || vendos[0] || null;
    setReady(false, 'Checking the local vendo…');
    health();
  }

  function init(data) {
    context = data.context || {};
    vendos = data.vendos || [];
    var select = $('compat-vendo');
    if (!select) return;
    select.textContent = '';
    vendos.forEach(function (vendo) {
      var option = document.createElement('option');
      option.value = vendo.id;
      option.textContent = vendo.name;
      if (vendo.interfaceName && vendo.interfaceName === context.interfaceName) option.selected = true;
      select.appendChild(option);
    });
    if (!vendos.length) {
      setReady(false, 'No local vendo configured');
      alertMessage('The operator has not configured a coin slot for this hotspot.');
      return;
    }
    if ($('compat-charging')) $('compat-charging').hidden = !vendos.some(function (v) { return v.chargingEnabled; });
    if ($('compat-eload')) $('compat-eload').hidden = !vendos.some(function (v) { return v.eloadEnabled; });
    selectVendo();
  }

  function generatedVoucher(data) { return String(data.voucher || data.voucherCode || data.code || '').trim().toUpperCase(); }
  function transactionAmount(data) {
    if (data.totalCoin !== undefined) return number(data.totalCoin);
    if (data.amount !== undefined) return number(data.amount);
    if (data.coin !== undefined) return number(data.coin);
    return null;
  }

  function displayTransaction(data) {
    activeVoucher = generatedVoucher(data) || activeVoucher;
    if (transactionMode === 'internet') setCurrentVoucher(activeVoucher);
    var amount = transactionAmount(data);
    if (amount !== null) totalCoinReceived = amount;
    if ($('compat-code')) $('compat-code').textContent = activeVoucher || 'Preparing…';
    if ($('compat-amount')) $('compat-amount').textContent = '₱' + totalCoinReceived;
    var seconds = number(data.timeAdded || 0), time = data.time || data.minutes || data.duration;
    if (!time && seconds) time = Math.floor(seconds / 3600) + 'h ' + Math.floor((seconds % 3600) / 60) + 'm';
    if ($('compat-time')) $('compat-time').textContent = time || '—';
    setTopupActive(true);
    if ($('compat-finish')) $('compat-finish').disabled = totalCoinReceived <= 0;
    if ($('compat-cancel')) $('compat-cancel').disabled = totalCoinReceived > 0;
  }

  function updateCountdown(data) {
    var remainMs = number(data.remainTime), waitMs = number(data.waitTime);
    var percent = waitMs > 0 ? Math.max(0, Math.min(100, (remainMs / waitMs) * 100)) : 100;
    var seconds = Math.max(0, Math.ceil(remainMs / 1000));
    if ($('compat-progress-bar')) $('compat-progress-bar').style.width = percent + '%';
    if ($('compat-countdown')) $('compat-countdown').textContent = seconds + 's';
    return seconds;
  }

  function resetTransaction(message) {
    clearTimeout(pollTimer); pollTimer = null;
    activeVoucher = ''; totalCoinReceived = 0; finalizingTopup = false; startingTopup = false;
    setTopupActive(false);
    if ($('compat-progress-bar')) $('compat-progress-bar').style.width = '100%';
    if ($('compat-countdown')) $('compat-countdown').textContent = 'Waiting…';
    if ($('compat-finish')) $('compat-finish').disabled = true;
    if ($('compat-cancel')) $('compat-cancel').disabled = false;
    if ($('compat-topup')) $('compat-topup').disabled = false;
    if (message) alertMessage(message);
  }

  function login(voucher) {
    voucher = String(voucher || '').trim().toUpperCase();
    if (!voucher) return;
    if (window.PIXIEPOINT_BOOTSTRAP) {
      var password = selected && selected.passwordMode === 'voucher' ? voucher : '';
      var chap = window.PIXIEPOINT_CHAP || {};
      var form = chap.id ? $('chap-login') : $('pap-login');
      if (!form || !form.elements.namedItem('username') || !form.elements.namedItem('password')) {
        alertMessage('The hotspot login form is unavailable. Please reload the Wi-Fi portal.');
        return;
      }
      form.elements.namedItem('username').value = voucher;
      form.elements.namedItem('password').value = chap.id ? hexMD5(chap.id + password + chap.challenge) : password;
      form.submit();
      return;
    }
    window.parent.postMessage({ type: 'pixiepoint:login', voucher: voucher, vendoId: selected && selected.id }, parentOrigin);
  }

  function finishTopup(autoLogin) {
    if (finalizingTopup || !activeVoucher || totalCoinReceived <= 0) return;
    finalizingTopup = true;
    clearTimeout(pollTimer); pollTimer = null;
    if ($('compat-finish')) $('compat-finish').disabled = true;
    if ($('compat-cancel')) $('compat-cancel').disabled = true;
    if ($('compat-progress')) $('compat-progress').textContent = autoLogin ? 'Connecting…' : 'Saving credit…';
    if (autoLogin) {
      if (transactionMode === 'internet') { setCurrentVoucher(activeVoucher); login(activeVoucher); }
      else resetTransaction('Charging time was added successfully.');
      return;
    }
    rpc('/useVoucher', 'POST', { voucher: activeVoucher }).then(function (result) {
      var data = responseData(result);
      if (!result.ok || (!isTrue(data.status) && !isTrue(data.success))) throw new Error(data.message || data.errorCode || 'The voucher could not be activated.');
      if (transactionMode === 'internet') { setCurrentVoucher(activeVoucher); login(activeVoucher); }
      else resetTransaction('Charging time was added successfully.');
    }).catch(function (error) {
      finalizingTopup = false;
      if ($('compat-finish')) $('compat-finish').disabled = totalCoinReceived <= 0;
      if ($('compat-progress')) $('compat-progress').textContent = error.message;
    });
  }

  function cancelTopup() {
    if (!activeVoucher) { resetTransaction(); return; }
    if (totalCoinReceived > 0) return;
    clearTimeout(pollTimer); pollTimer = null;
    if ($('compat-cancel')) $('compat-cancel').disabled = true;
    rpc('/cancelTopUp', 'POST', { voucher: activeVoucher, mac: context.mac || '' }).catch(function () {}).then(function () {
      resetTransaction('Coin insertion cancelled.');
    });
  }

  function handleCoinTimeout() {
    if (finalizingTopup) return;
    clearTimeout(pollTimer); pollTimer = null;
    if (totalCoinReceived > 0) finishTopup(true);
    else resetTransaction('Coin slot expired.');
  }

  function handleCoinBusy() {
    clearTimeout(pollTimer); pollTimer = null;
    if (totalCoinReceived > 0) finishTopup(true);
    else resetTransaction('Coin slot was cancelled.');
  }

  function pollCoin() {
    clearTimeout(pollTimer);
    if (!activeVoucher || finalizingTopup) return;
    rpc('/checkCoin', 'POST', { voucher: activeVoucher }).then(function (result) {
      var data = responseData(result), errorCode = String(data.errorCode || '');
      if (result.ok && (isTrue(data.status) || isTrue(data.success))) {
        displayTransaction(data);
        if ($('compat-progress')) $('compat-progress').textContent = 'Coin received. Insert another coin or wait for the timer.';
        if (data.remainTime !== undefined || data.waitTime !== undefined) updateCountdown(data);
        else {
          if ($('compat-progress-bar')) $('compat-progress-bar').style.width = '100%';
          if ($('compat-countdown')) $('compat-countdown').textContent = 'Renewed';
        }
      } else if (errorCode === 'coin.is.reading') {
        if (data.remainTime !== undefined || data.waitTime !== undefined) updateCountdown(data);
        if ($('compat-progress')) $('compat-progress').textContent = 'Verifying coin, please wait…';
      } else if (errorCode === 'coin.not.inserted') {
        displayTransaction(data);
        var seconds = updateCountdown(data);
        if ($('compat-progress')) $('compat-progress').textContent = totalCoinReceived > 0 ? 'Insert another coin to renew the timer, or press Done.' : 'Waiting for coin…';
        if (data.remainTime !== undefined && seconds <= 0) { handleCoinTimeout(); return; }
      } else if (errorCode === 'coins.wait.expired') {
        handleCoinTimeout();
        return;
      } else if (errorCode === 'coinslot.busy') {
        handleCoinBusy();
        return;
      } else {
        resetTransaction(data.message || errorCode || 'The coin slot reported an error.');
        return;
      }
      pollTimer = setTimeout(pollCoin, 1000);
    }).catch(function (error) {
      if ($('compat-progress')) $('compat-progress').textContent = error.message;
      pollTimer = setTimeout(pollCoin, 2500);
    });
  }

  function startTopup(payload) {
    if (startingTopup) return;
    startingTopup = true;
    rpc('/topUp', 'POST', payload).then(function (result) {
      var data = responseData(result);
      startingTopup = false;
      if (!result.ok || (!isTrue(data.status) && !isTrue(data.success))) {
        resetTransaction(data.message || data.errorCode || 'The coin slot rejected the request.');
        return;
      }
      activeVoucher = generatedVoucher(data) || activeVoucher;
      if (!activeVoucher) { resetTransaction('The vendo did not return a voucher code.'); return; }
      displayTransaction(data);
      if ($('compat-progress')) $('compat-progress').textContent = 'Coin slot active. Insert a coin now.';
      if ($('compat-progress-bar')) $('compat-progress-bar').style.width = '100%';
      if ($('compat-countdown')) $('compat-countdown').textContent = 'Ready';
      pollCoin();
    }).catch(function (error) {
      startingTopup = false;
      resetTransaction(error.message || 'Coin slot is unavailable.');
    });
  }

  function beginTopup(options) {
    if (startingTopup || pollTimer || finalizingTopup) return;
    options = options || {};
    transactionMode = options.mode || 'internet';
    alertMessage(''); totalCoinReceived = 0; finalizingTopup = false;
    if ($('compat-topup')) $('compat-topup').disabled = true;
    if ($('compat-finish')) $('compat-finish').disabled = true;
    if ($('compat-cancel')) $('compat-cancel').disabled = false;
    var voucher = options.voucher || (transactionMode === 'internet' ? currentVoucher() : '');
    activeVoucher = String(voucher || '').trim().toUpperCase();
    if (transactionMode === 'internet' && activeVoucher) setCurrentVoucher(activeVoucher);
    if ($('compat-code')) $('compat-code').textContent = activeVoucher || 'Generating…';
    if ($('compat-amount')) $('compat-amount').textContent = '₱0';
    if ($('compat-time')) $('compat-time').textContent = '—';
    if ($('compat-progress')) $('compat-progress').textContent = 'Activating coin slot…';
    if ($('compat-progress-bar')) $('compat-progress-bar').style.width = '100%';
    if ($('compat-countdown')) $('compat-countdown').textContent = 'Starting…';
    setTopupActive(true);
    var payload = { voucher: activeVoucher, mac: context.mac || '', ipAddress: context.ip || '', extendTime: 0 };
    if (options.chargerPort !== undefined) { payload.topupType = 'CHARGER'; payload.chargerPort = options.chargerPort; }
    startTopup(payload);
  }

  function showRates() {
    var list = $('compat-rate-list'), modalElement = $('compat-rates-modal');
    if (!list || !modalElement) return;
    list.textContent = 'Loading rates…';
    var modal = window.bootstrap && bootstrap.Modal ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
    if (modal) modal.show();
    rpc('/getRates?rateType=1&date=' + encodeURIComponent(new Date().toISOString()), 'GET').then(function (result) {
      if (!result.ok) throw new Error('Rates are unavailable.');
      var data = responseData(result), rates = Array.isArray(data) ? data : data.rates || [];
      if (!rates.length && typeof data.raw === 'string') rates = data.raw.split('|').filter(Boolean).map(function (row) { var column = row.split('#'); return { amount: column[0], minutes: column[3], data: column[4] }; });
      list.textContent = '';
      rates.forEach(function (rate) {
        var row = document.createElement('div'); row.className = 'compat-rate py-2 border-bottom';
        row.textContent = '₱' + (rate.amount || rate.price || rate.coin || '—') + ' · ' + (rate.time || rate.minutes || rate.duration || '—') + (rate.data ? ' · ' + rate.data + ' MB' : '');
        list.appendChild(row);
      });
      if (!rates.length && typeof data.raw === 'string') list.textContent = data.raw;
      if (!rates.length && typeof data.raw !== 'string') list.textContent = 'No rates were returned.';
    }).catch(function (error) { list.textContent = error.message; });
  }

  function showCharging() {
    rpc('/getChargingStation', 'GET', { date: Date.now() }).then(function (result) {
      if (!result.ok) throw new Error('Charging stations are unavailable.');
      var data = responseData(result), raw = typeof data.raw === 'string' ? data.raw : '', list = $('compat-charger-list');
      if (!list) return;
      list.textContent = '';
      raw.split('|').filter(Boolean).forEach(function (value, index) {
        var column = value.split('#'); if (column[1] === '-1') return;
        var row = document.createElement('div'), button = document.createElement('button'); row.className = 'compat-rate';
        row.appendChild(document.createTextNode((column[0] || 'Charging port ' + (index + 1)) + (Number(column[3]) * 1000 > Date.now() ? ' · In use' : ' · Available')));
        button.className = 'button secondary'; button.type = 'button'; button.textContent = 'Add charging time'; button.disabled = Number(column[3]) * 1000 > Date.now();
        button.addEventListener('click', function () { beginTopup({ voucher: column[0], chargerPort: index, mode: 'charger' }); });
        row.appendChild(button); list.appendChild(row);
      });
      if (!list.childNodes.length) list.textContent = 'No charging ports are configured.';
      list.hidden = false;
    }).catch(function (error) { alertMessage(error.message); });
  }

  function showEload() {
    var panel = $('compat-eload-panel'), products = $('compat-eload-products');
    if (!panel || !products) return;
    panel.hidden = false; products.textContent = 'Contacting the JuanFi e-load service…';
    rpc('/eload/rates', 'GET', { date: Date.now() }).then(function (result) {
      if (!result.ok) throw new Error('E-load rates are unavailable.');
      var data = responseData(result);
      if (data.raw === 'disabled') throw new Error('E-load is disabled on this vendo.');
      products.textContent = 'The vendo returned its compressed product catalog. Full product checkout requires binary catalog decoding and will remain unavailable until it passes a physical-device test.';
    }).catch(function (error) { products.textContent = error.message; });
  }

  window.addEventListener('message', function (event) {
    var data = event.data || {};
    if (!window.PIXIEPOINT_BOOTSTRAP && data.type === 'pixiepoint:init') { parentOrigin = event.origin; init(data); }
    else if (data.type === 'pixiepoint:response' && pending[data.id]) {
      var request = pending[data.id]; clearTimeout(request.timeout); delete pending[data.id];
      data.error ? request.reject(new Error(data.error)) : request.resolve(data.result || {});
    }
  });

  if ($('compat-vendo')) $('compat-vendo').addEventListener('change', selectVendo);
  if ($('compat-topup')) $('compat-topup').addEventListener('click', function () { beginTopup({ voucher: currentVoucher(), mode: 'internet' }); });
  if ($('compat-finish')) $('compat-finish').addEventListener('click', function () { finishTopup(false); });
  if ($('compat-cancel')) $('compat-cancel').addEventListener('click', cancelTopup);
  if ($('compat-rates')) $('compat-rates').addEventListener('click', showRates);
  if ($('compat-charging')) $('compat-charging').addEventListener('click', showCharging);
  if ($('compat-eload')) $('compat-eload').addEventListener('click', showEload);
  if ($('compat-voucher-form')) $('compat-voucher-form').addEventListener('submit', function (event) { event.preventDefault(); login(currentVoucher()); });

  if (window.PIXIEPOINT_BOOTSTRAP && $('compat-vendo')) init({ context: window.PIXIEPOINT_CONTEXT || {}, vendos: window.PIXIEPOINT_VENDOS || [] });
  else if (!window.PIXIEPOINT_BOOTSTRAP) window.parent.postMessage({ type: 'pixiepoint:ready' }, '*');
})();