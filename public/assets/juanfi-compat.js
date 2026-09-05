(function () {
  'use strict';

  var parentOrigin = '*',
    context = {},
    vendos = [],
    selected = null;
  var pending = Object.create(null),
    sequence = 0,
    pollTimer = null,
    activeVoucher = '',
    transactionMode = 'internet';
  var totalCoinReceived = 0,
    finalizingTopup = false;
  var $ = function (id) {
    return document.getElementById(id);
  };

  function localRequest(path, method, data) {
    return new Promise(function (resolve, reject) {
      var xhr = new XMLHttpRequest();
      var query =
        method === 'GET' && data && Object.keys(data).length
          ? '?' + new URLSearchParams(data).toString()
          : '';
      if (!selected) return reject(new Error('No local vendo is selected.'));

      xhr.open(method || 'GET', selected.baseUrl + path + query, true);
      xhr.timeout = 7000;
      xhr.onload = function () {
        var body = xhr.responseText;
        try {
          body = JSON.parse(body);
        } catch (_) {}
        resolve({ ok: xhr.status >= 200 && xhr.status < 300, status: xhr.status, body: body });
      };
      xhr.onerror = function () {
        reject(new Error('The local vendo is unreachable.'));
      };
      xhr.ontimeout = function () {
        reject(new Error('The local vendo timed out.'));
      };

      if (method === 'POST') {
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send(new URLSearchParams(data || {}).toString());
      } else {
        xhr.send();
      }
    });
  }

  function alertMessage(message) {
    var el = $('compat-alert');
    el.textContent = message || '';
    el.hidden = !message;
  }

  function rpc(path, method, data) {
    if (window.PIXIEPOINT_BOOTSTRAP) return localRequest(path, method || 'GET', data || {});

    return new Promise(function (resolve, reject) {
      var id = 'rpc-' + ++sequence + '-' + Date.now();
      var timeout = setTimeout(function () {
        delete pending[id];
        reject(new Error('The local vendo did not respond.'));
      }, 9000);

      pending[id] = { resolve: resolve, reject: reject, timeout: timeout };
      window.parent.postMessage(
        {
          type: 'pixiepoint:request',
          id: id,
          vendoId: selected && selected.id,
          path: path,
          method: method || 'GET',
          data: data || {},
        },
        parentOrigin,
      );
    });
  }

  function responseData(result) {
    var body = result && result.body;
    if (body && typeof body === 'object') return body;
    if (typeof body !== 'string') return {};
    try {
      return JSON.parse(body);
    } catch (_) {
      return { raw: body };
    }
  }

  function isTrue(value) {
    return value === true || value === 'true' || value === 1 || value === '1';
  }

  function number(value) {
    value = Number(value);
    return Number.isFinite(value) && value > 0 ? value : 0;
  }

  function currentVoucher() {
    var input = $('compat-voucher');
    return input ? input.value.trim().toUpperCase() : '';
  }

  function setCurrentVoucher(voucher) {
    voucher = String(voucher || '')
      .trim()
      .toUpperCase();
    var input = $('compat-voucher');
    if (input && voucher) input.value = voucher;
    return voucher;
  }

  function setTopupActive(active) {
    $('compat-topup').hidden = active;
    $('compat-connect').hidden = active;
    $('compat-transaction').hidden = !active;
  }

  function setReady(ready, message) {
    $('compat-topup').disabled = !ready;
    $('compat-rates').disabled = !ready;
    $('compat-health').textContent = message;
    $('compat-health').className = 'compat-status ' + (ready ? 'online' : 'offline');
  }

  function health() {
    if (!selected) return;
    rpc('/health')
      .then(function (result) {
        if (!result.ok) throw new Error('HTTP ' + result.status);
        setReady(true, (selected.businessName || selected.name) + ' is ready');
        alertMessage('');
      })
      .catch(function () {
        setReady(false, (selected.businessName || selected.name) + ' is unavailable');
        alertMessage(
          'PixiePoint is online, but this browser cannot reach the local coin slot. Check its power, Wi-Fi connection, and address.',
        );
      });
  }

  function selectVendo() {
    selected =
      vendos.filter(function (v) {
        return v.id === $('compat-vendo').value;
      })[0] ||
      vendos[0] ||
      null;
    setReady(false, 'Checking the local vendo…');
    health();
  }

  function init(data) {
    context = data.context || {};
    vendos = data.vendos || [];

    var select = $('compat-vendo');
    select.textContent = '';
    vendos.forEach(function (vendo) {
      var option = document.createElement('option');
      option.value = vendo.id;
      option.textContent = vendo.businessName || vendo.name;
      if (vendo.interfaceName && vendo.interfaceName === context.interfaceName)
        option.selected = true;
      select.appendChild(option);
    });

    if (!vendos.length) {
      setReady(false, 'No local vendo configured');
      alertMessage('The operator has not configured a coin slot for this hotspot.');
      return;
    }

    $('compat-charging').hidden = !vendos.some(function (v) {
      return v.chargingEnabled;
    });
    $('compat-eload').hidden = !vendos.some(function (v) {
      return v.eloadEnabled;
    });
    selectVendo();
  }

  function generatedVoucher(data) {
    return String(data.voucher || data.voucherCode || data.code || '')
      .trim()
      .toUpperCase();
  }

  function transactionAmount(data) {
    return number(
      data.totalCoin !== undefined
        ? data.totalCoin
        : data.amount !== undefined
          ? data.amount
          : data.coin,
    );
  }

  function displayTransaction(data) {
    activeVoucher = generatedVoucher(data) || activeVoucher;
    if (transactionMode === 'internet') setCurrentVoucher(activeVoucher);

    totalCoinReceived = Math.max(totalCoinReceived, transactionAmount(data));
    $('compat-code').textContent = activeVoucher || 'Preparing…';
    $('compat-amount').textContent = '₱' + totalCoinReceived;

    var seconds = number(data.timeAdded || 0);
    var time = data.time || data.minutes || data.duration;
    if (!time && seconds)
      time = Math.floor(seconds / 3600) + 'h ' + Math.floor((seconds % 3600) / 60) + 'm';
    $('compat-time').textContent = time || '—';
    setTopupActive(true);

    $('compat-finish').disabled = totalCoinReceived <= 0;
    $('compat-cancel').disabled = totalCoinReceived > 0;
  }

  function updateCountdown(data) {
    var remainMs = number(data.remainTime);
    var waitMs = number(data.waitTime);
    var percent = waitMs > 0 ? Math.max(0, Math.min(100, (remainMs / waitMs) * 100)) : 100;
    var seconds = Math.max(0, Math.ceil(remainMs / 1000));

    $('compat-progress-bar').style.width = percent + '%';
    $('compat-countdown').textContent = seconds > 0 ? seconds + 's' : '0s';
    return seconds;
  }

  function resetTransaction(message) {
    clearTimeout(pollTimer);
    pollTimer = null;
    activeVoucher = '';
    totalCoinReceived = 0;
    finalizingTopup = false;
    setTopupActive(false);
    $('compat-progress-bar').style.width = '100%';
    $('compat-countdown').textContent = 'Waiting…';
    $('compat-finish').disabled = true;
    $('compat-cancel').disabled = false;
    $('compat-topup').disabled = false;
    if (message) alertMessage(message);
  }

  function login(voucher) {
    voucher = String(voucher || '')
      .trim()
      .toUpperCase();
    if (!voucher) return;

    if (window.PIXIEPOINT_BOOTSTRAP) {
      var password = selected && selected.passwordMode === 'voucher' ? voucher : '';
      var chap = window.PIXIEPOINT_CHAP || {};
      var form;

      if (chap.id && $('chap-login')) {
        form = $('chap-login');
        form.username.value = voucher;
        form.password.value = hexMD5(chap.id + password + chap.challenge);
      } else {
        form = $('pap-login');
        form.username.value = voucher;
        form.password.value = password;
      }
      form.submit();
      return;
    }

    window.parent.postMessage(
      { type: 'pixiepoint:login', voucher: voucher, vendoId: selected && selected.id },
      parentOrigin,
    );
  }

  function finishTopup(autoLogin) {
    if (finalizingTopup || !activeVoucher || totalCoinReceived <= 0) return;

    finalizingTopup = true;
    clearTimeout(pollTimer);
    pollTimer = null;
    $('compat-finish').disabled = true;
    $('compat-cancel').disabled = true;
    $('compat-progress').textContent = autoLogin
      ? 'Coin time ended. Connecting…'
      : 'Saving credit…';

    rpc('/useVoucher', 'POST', { voucher: activeVoucher })
      .then(function (result) {
        var data = responseData(result);
        if (!result.ok || (!isTrue(data.status) && !isTrue(data.success))) {
          throw new Error(data.message || data.errorCode || 'The voucher could not be activated.');
        }

        if (transactionMode === 'internet') {
          setCurrentVoucher(activeVoucher);
          login(activeVoucher);
        } else {
          resetTransaction('Charging time was added successfully.');
        }
      })
      .catch(function (error) {
        if (autoLogin && transactionMode === 'internet' && totalCoinReceived > 0) {
          setCurrentVoucher(activeVoucher);
          login(activeVoucher);
          return;
        }

        finalizingTopup = false;
        $('compat-finish').disabled = totalCoinReceived <= 0;
        $('compat-progress').textContent = error.message;
      });
  }

  function cancelTopup(autoCancel) {
    if (!activeVoucher) {
      resetTransaction();
      return;
    }

    clearTimeout(pollTimer);
    pollTimer = null;
    $('compat-cancel').disabled = true;

    rpc('/cancelTopUp', 'POST', {
      voucher: activeVoucher,
      mac: context.mac || '',
    })
      .catch(function () {})
      .then(function () {
        resetTransaction(
          autoCancel ? 'No coin received. Coin slot closed.' : 'Coin insertion cancelled.',
        );
      });
  }

  function handleCoinTimeout() {
    if (finalizingTopup) return;
    clearTimeout(pollTimer);
    pollTimer = null;

    if (totalCoinReceived > 0) {
      finishTopup(true);
    } else {
      cancelTopup(true);
    }
  }

  function pollCoin() {
    clearTimeout(pollTimer);
    if (!activeVoucher || finalizingTopup) return;

    rpc('/checkCoin', 'POST', { voucher: activeVoucher })
      .then(function (result) {
        var data = responseData(result);
        var errorCode = String(data.errorCode || '');

        if (result.ok && (isTrue(data.status) || isTrue(data.success))) {
          displayTransaction(data);
          $('compat-progress').textContent =
            'Coin received. Insert another coin or wait for the timer.';
          $('compat-progress-bar').style.width = '100%';
          $('compat-countdown').textContent = 'Renewed';
        } else if (errorCode === 'coin.is.reading') {
          $('compat-progress').textContent = 'Verifying coin, please wait…';
        } else if (errorCode === 'coin.not.inserted') {
          displayTransaction(data);
          var seconds = updateCountdown(data);
          $('compat-progress').textContent =
            totalCoinReceived > 0
              ? 'Insert another coin to renew the timer, or press Done.'
              : 'Waiting for coin…';

          if (seconds <= 0) {
            handleCoinTimeout();
            return;
          }
        } else if (errorCode === 'coinslot.busy') {
          if (totalCoinReceived > 0) {
            finishTopup(true);
          } else {
            resetTransaction('Coin slot was cancelled or is busy.');
          }
          return;
        } else {
          throw new Error(data.message || errorCode || 'The coin slot reported an error.');
        }

        pollTimer = setTimeout(pollCoin, 1000);
      })
      .catch(function (error) {
        $('compat-progress').textContent = error.message;
        pollTimer = setTimeout(pollCoin, 2500);
      });
  }

  function startTopup(payload, retryCount) {
    rpc('/topUp', 'POST', payload)
      .then(function (result) {
        var data = responseData(result);
        if (!result.ok || (!isTrue(data.status) && !isTrue(data.success))) {
          throw new Error(data.message || data.errorCode || 'The coin slot rejected the request.');
        }

        activeVoucher = generatedVoucher(data) || activeVoucher;
        displayTransaction(data);
        $('compat-progress').textContent = 'Coin slot active. Insert a coin now.';
        $('compat-progress-bar').style.width = '100%';
        $('compat-countdown').textContent = 'Ready';
        pollCoin();
      })
      .catch(function (error) {
        if (retryCount < 3) {
          setTimeout(function () {
            startTopup(payload, retryCount + 1);
          }, 1000);
          return;
        }

        resetTransaction(error.message || 'Coin slot is unavailable.');
      });
  }

  function beginTopup(options) {
    options = options || {};
    transactionMode = options.mode || 'internet';
    alertMessage('');
    totalCoinReceived = 0;
    finalizingTopup = false;
    $('compat-topup').disabled = true;
    $('compat-finish').disabled = true;
    $('compat-cancel').disabled = false;

    var voucher = options.voucher || (transactionMode === 'internet' ? currentVoucher() : '');
    activeVoucher = String(voucher || '')
      .trim()
      .toUpperCase();

    if (!activeVoucher) {
      resetTransaction('A voucher code is required before inserting coins.');
      return;
    }

    if (transactionMode === 'internet') setCurrentVoucher(activeVoucher);

    $('compat-code').textContent = activeVoucher;
    $('compat-amount').textContent = '₱0';
    $('compat-time').textContent = '—';
    $('compat-progress').textContent = 'Activating coin slot…';
    $('compat-progress-bar').style.width = '100%';
    $('compat-countdown').textContent = 'Starting…';
    setTopupActive(true);

    var payload = {
      voucher: activeVoucher,
      mac: context.mac || '',
      ipAddress: context.ip || '',
      extendTime: 0,
    };

    if (options.chargerPort !== undefined) {
      payload.topupType = 'CHARGER';
      payload.chargerPort = options.chargerPort;
    }

    startTopup(payload, 0);
  }

  function showRates() {
    rpc('/getRates?rateType=1&date=' + encodeURIComponent(new Date().toISOString()), 'GET')
      .then(function (result) {
        if (!result.ok) throw new Error('Rates are unavailable.');
        var data = responseData(result);
        var rates = Array.isArray(data) ? data : data.rates || [];

        if (!rates.length && typeof data.raw === 'string') {
          rates = data.raw
            .split('|')
            .filter(Boolean)
            .map(function (row) {
              var column = row.split('#');
              return { amount: column[0], minutes: column[3], data: column[4] };
            });
        }

        var list = $('compat-rate-list');
        list.textContent = '';
        rates.forEach(function (rate) {
          var row = document.createElement('div');
          row.className = 'compat-rate';
          row.textContent =
            '₱' +
            (rate.amount || rate.price || rate.coin || '—') +
            ' · ' +
            (rate.time || rate.minutes || rate.duration || '—') +
            (rate.data ? ' · ' + rate.data + ' MB' : '');
          list.appendChild(row);
        });

        if (!rates.length && typeof data.raw === 'string') list.textContent = data.raw;
        if (!rates.length && typeof data.raw !== 'string') list.textContent = 'No rates were returned.';
        list.hidden = false;
      })
      .catch(function (error) {
        alertMessage(error.message);
      });
  }

  function showCharging() {
    rpc('/getChargingStation', 'GET', { date: Date.now() })
      .then(function (result) {
        if (!result.ok) throw new Error('Charging stations are unavailable.');
        var data = responseData(result);
        var raw = typeof data.raw === 'string' ? data.raw : '';
        var list = $('compat-charger-list');
        list.textContent = '';

        raw
          .split('|')
          .filter(Boolean)
          .forEach(function (value, index) {
            var column = value.split('#');
            if (column[1] === '-1') return;

            var row = document.createElement('div');
            var button = document.createElement('button');
            row.className = 'compat-rate';
            row.appendChild(
              document.createTextNode(
                (column[0] || 'Charging port ' + (index + 1)) +
                  (Number(column[3]) * 1000 > Date.now() ? ' · In use' : ' · Available'),
              ),
            );
            button.className = 'button secondary';
            button.type = 'button';
            button.textContent = 'Add charging time';
            button.disabled = Number(column[3]) * 1000 > Date.now();
            button.addEventListener('click', function () {
              beginTopup({ voucher: column[0], chargerPort: index, mode: 'charger' });
            });
            row.appendChild(button);
            list.appendChild(row);
          });

        if (!list.childNodes.length) list.textContent = 'No charging ports are configured.';
        list.hidden = false;
      })
      .catch(function (error) {
        alertMessage(error.message);
      });
  }

  function showEload() {
    var panel = $('compat-eload-panel');
    var products = $('compat-eload-products');
    panel.hidden = false;
    products.textContent = 'Contacting the JuanFi e-load service…';

    rpc('/eload/rates', 'GET', { date: Date.now() })
      .then(function (result) {
        if (!result.ok) throw new Error('E-load rates are unavailable.');
        var data = responseData(result);
        if (data.raw === 'disabled') throw new Error('E-load is disabled on this vendo.');
        products.textContent =
          'The vendo returned its compressed product catalog. Full product checkout requires binary catalog decoding and will remain unavailable until it passes a physical-device test.';
      })
      .catch(function (error) {
        products.textContent = error.message;
      });
  }

  window.addEventListener('message', function (event) {
    var data = event.data || {};
    if (data.type === 'pixiepoint:init') {
      parentOrigin = event.origin;
      init(data);
    } else if (data.type === 'pixiepoint:response' && pending[data.id]) {
      var request = pending[data.id];
      clearTimeout(request.timeout);
      delete pending[data.id];
      data.error ? request.reject(new Error(data.error)) : request.resolve(data.result || {});
    }
  });

  $('compat-vendo').addEventListener('change', selectVendo);
  $('compat-topup').addEventListener('click', function () {
    beginTopup({ voucher: currentVoucher(), mode: 'internet' });
  });
  $('compat-finish').addEventListener('click', function () {
    finishTopup(false);
  });
  $('compat-cancel').addEventListener('click', function () {
    cancelTopup(false);
  });
  $('compat-rates').addEventListener('click', showRates);
  $('compat-charging').addEventListener('click', showCharging);
  $('compat-eload').addEventListener('click', showEload);
  $('compat-voucher-form').addEventListener('submit', function (event) {
    event.preventDefault();
    login(currentVoucher());
  });

  if (window.PIXIEPOINT_BOOTSTRAP) {
    init({ context: window.PIXIEPOINT_CONTEXT || {}, vendos: window.PIXIEPOINT_VENDOS || [] });
  } else {
    window.parent.postMessage({ type: 'pixiepoint:ready' }, '*');
  }
})();
