(function () {
  'use strict';

  const session = window.PIXIEPOINT_SESSION || {};
  const root = document.getElementById('pixiepoint-root');
  const vendos = window.PIXIEPOINT_VENDOS || [];
  const vendo = vendos[0] || null;

  if (!root) return;

  function number(value) {
    value = Number(value);
    return Number.isFinite(value) && value > 0 ? Math.floor(value) : 0;
  }

  function duration(seconds) {
    seconds = number(seconds);
    const hours = Math.floor(seconds / 3600);
    const minutes = Math.floor((seconds % 3600) / 60);
    const remaining = seconds % 60;

    if (hours) return `${hours}h ${minutes}m`;
    if (minutes) return `${minutes}m ${remaining}s`;
    return `${remaining}s`;
  }

  function bytes(value) {
    value = number(value);
    if (value < 1024) return `${value} B`;
    if (value < 1048576) return `${(value / 1024).toFixed(1)} KB`;
    if (value < 1073741824) return `${(value / 1048576).toFixed(1)} MB`;
    return `${(value / 1073741824).toFixed(2)} GB`;
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (char) {
      return {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;',
      }[char];
    });
  }

  function request(path, method, data) {
    return new Promise(function (resolve, reject) {
      if (!vendo) {
        reject(new Error('No coin slot configured.'));
        return;
      }

      const xhr = new XMLHttpRequest();
      const query = method === 'GET' && data ? `?${new URLSearchParams(data).toString()}` : '';

      xhr.open(method || 'GET', vendo.baseUrl + path + query, true);
      xhr.timeout = 7000;

      xhr.onload = function () {
        let body = xhr.responseText;
        try {
          body = JSON.parse(body);
        } catch (_) {}
        resolve({
          ok: xhr.status >= 200 && xhr.status < 300,
          status: xhr.status,
          body: body,
        });
      };

      xhr.onerror = function () {
        reject(new Error('Coin slot unavailable.'));
      };
      xhr.ontimeout = function () {
        reject(new Error('Coin slot timed out.'));
      };

      if (method === 'POST') {
        xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
        xhr.send(new URLSearchParams(data || {}).toString());
        return;
      }

      xhr.send();
    });
  }

  function responseData(result) {
    if (result && typeof result.body === 'object') return result.body || {};
    try {
      return JSON.parse(result.body || '{}');
    } catch (_) {
      return {};
    }
  }

  function isTrue(value) {
    return value === true || value === 1 || value === '1' || value === 'true';
  }

  window.openLogout = function () {
    if (window.name !== 'hotspot_status') return true;

    window.open(
      session.logoutUrl,
      'hotspot_logout',
      'toolbar=0,location=0,directories=0,status=0,menubars=0,resizable=1,width=280,height=250',
    );
    window.close();
    return false;
  };

  function endSession() {
    const logoutUrl = session.logoutUrl || '';
    const loginUrl = session.loginUrl || '/login';

    if (!logoutUrl) {
      location.href = loginUrl;
      return;
    }

    const separator = logoutUrl.includes('?') ? '&' : '?';
    const xhr = new XMLHttpRequest();
    let finished = false;

    function returnToLogin() {
      if (finished) return;
      finished = true;
      location.href = loginUrl;
    }

    xhr.open('GET', `${logoutUrl}${separator}erase-cookie=on`, true);
    xhr.timeout = 4000;
    xhr.onload = returnToLogin;
    xhr.onerror = returnToLogin;
    xhr.ontimeout = returnToLogin;
    xhr.send();
  }

  let timeLeft = number(session.sessionTimeLeft);
  let pollTimer = null;
  let extendCoinTotal = 0;
  let finalizingExtension = false;

  document.body.className = '';
  root.outerHTML = `
    <main class="portal">
      <section class="card">
        <div class="brand">
          <div class="logo">P</div>
          <div>
            <strong>PixiePoint Wi-Fi</strong>
            <div class="muted">MikroTik hotspot access</div>
          </div>
        </div>

        <h1>You're connected</h1>
        <p class="muted">Live Wi-Fi session for ${escapeHtml(session.mac || 'this device')}.</p>

        <div class="context">
          <div>
            <small>Time left</small>
            <span id="pp-left">${timeLeft ? duration(timeLeft) : 'Unlimited'}</span>
          </div>
          <div>
            <small>Data</small>
            <span>↓ ${bytes(session.bytesOut)} / ↑ ${bytes(session.bytesIn)}</span>
          </div>
        </div>

        <button
          class="button secondary full"
          id="pp-extend"
          type="button"
          ${vendo && session.username ? '' : 'hidden'}
        >Extend time</button>

        <div id="pp-extend-box" class="compat-transaction" hidden>
          <div class="d-flex justify-content-between gap-3 align-items-start">
            <div>
              <small>Extend current voucher</small>
              <strong class="d-block">${escapeHtml(session.username || '—')}</strong>
            </div>
            <small id="pp-extend-countdown" class="compat-countdown">Waiting…</small>
          </div>

          <div class="compat-coin-progress" aria-label="Extension coin slot timeout">
            <div id="pp-extend-progress-bar" class="compat-coin-progress-bar"></div>
          </div>

          <div class="context">
            <div>
              <small>Coin total</small>
              <span id="pp-extend-amount">₱0</span>
            </div>
            <div>
              <small>Added time</small>
              <span id="pp-extend-time">—</span>
            </div>
          </div>

          <p id="pp-extend-status" class="muted mb-2">Activating coin slot…</p>

          <div class="actions">
            <button class="button" id="pp-extend-finish" type="button" disabled>Done</button>
            <button class="button secondary" id="pp-extend-cancel" type="button">Cancel</button>
          </div>
        </div>

        <div class="actions">
          <form action="${escapeHtml(session.logoutUrl || '#')}" name="logout" onsubmit="return openLogout()">
            <button class="button secondary" type="submit">Disconnect</button>
          </form>
          <button class="button secondary" id="pp-end-session" type="button">End session</button>
        </div>
      </section>
    </main>
  `;

  const extendButton = document.getElementById('pp-extend');
  const extendBox = document.getElementById('pp-extend-box');
  const extendStatus = document.getElementById('pp-extend-status');
  const extendAmount = document.getElementById('pp-extend-amount');
  const extendTime = document.getElementById('pp-extend-time');
  const extendCountdown = document.getElementById('pp-extend-countdown');
  const extendProgressBar = document.getElementById('pp-extend-progress-bar');
  const finishButton = document.getElementById('pp-extend-finish');
  const cancelButton = document.getElementById('pp-extend-cancel');
  const endSessionButton = document.getElementById('pp-end-session');

  function transactionAmount(data) {
    return number(
      data.totalCoin !== undefined
        ? data.totalCoin
        : data.amount !== undefined
          ? data.amount
          : data.coin,
    );
  }

  function updateExtensionDetails(data) {
    extendCoinTotal = Math.max(extendCoinTotal, transactionAmount(data));
    extendAmount.textContent = `₱${extendCoinTotal}`;

    const seconds = number(data.timeAdded);
    const addedTime =
      data.time || data.minutes || data.duration || (seconds ? duration(seconds) : '—');
    extendTime.textContent = addedTime;

    finishButton.disabled = extendCoinTotal <= 0;
    cancelButton.disabled = extendCoinTotal > 0;
  }

  function updateExtensionCountdown(data) {
    const remainMs = number(data.remainTime);
    const waitMs = number(data.waitTime);
    const percent = waitMs > 0 ? Math.max(0, Math.min(100, (remainMs / waitMs) * 100)) : 100;
    const seconds = Math.max(0, Math.ceil(remainMs / 1000));

    extendProgressBar.style.width = `${percent}%`;
    extendCountdown.textContent = seconds > 0 ? `${seconds}s` : '0s';
    return seconds;
  }

  function resetExtension(message) {
    clearTimeout(pollTimer);
    pollTimer = null;
    extendCoinTotal = 0;
    finalizingExtension = false;
    extendBox.hidden = true;
    extendButton.hidden = false;
    extendButton.disabled = false;
    extendAmount.textContent = '₱0';
    extendTime.textContent = '—';
    extendProgressBar.style.width = '100%';
    extendCountdown.textContent = 'Waiting…';
    finishButton.disabled = true;
    cancelButton.disabled = false;

    if (message) {
      extendStatus.textContent = message;
    }
  }

  function finalizeExtension(autoFinish) {
    if (finalizingExtension || extendCoinTotal <= 0) return;

    finalizingExtension = true;
    clearTimeout(pollTimer);
    pollTimer = null;
    finishButton.disabled = true;
    cancelButton.disabled = true;
    extendStatus.textContent = autoFinish
      ? 'Coin time ended. Saving extension…'
      : 'Saving extension…';

    request('/useVoucher', 'POST', { voucher: session.username })
      .then(function (result) {
        const data = responseData(result);
        if (!result.ok || (!isTrue(data.status) && !isTrue(data.success))) {
          throw new Error(data.message || data.errorCode || 'Could not save extension.');
        }

        extendStatus.textContent = 'Time extended. Refreshing status…';
        setTimeout(function () {
          location.href = session.refreshUrl || location.href;
        }, 700);
      })
      .catch(function (error) {
        if (autoFinish && extendCoinTotal > 0) {
          extendStatus.textContent = 'Extension completed. Refreshing status…';
          setTimeout(function () {
            location.href = session.refreshUrl || location.href;
          }, 700);
          return;
        }

        finalizingExtension = false;
        finishButton.disabled = extendCoinTotal <= 0;
        cancelButton.disabled = extendCoinTotal > 0;
        extendStatus.textContent = error.message;
      });
  }

  function cancelExtension(autoCancel) {
    if (extendCoinTotal > 0) return;

    clearTimeout(pollTimer);
    pollTimer = null;
    cancelButton.disabled = true;
    extendStatus.textContent = autoCancel ? 'No coin received. Closing coin slot…' : 'Cancelling…';

    request('/cancelTopUp', 'POST', {
      voucher: session.username,
      mac: session.mac || '',
    })
      .catch(function () {})
      .then(function () {
        resetExtension(autoCancel ? 'No coin received. Coin slot closed.' : 'Extension cancelled.');
      });
  }

  function handleExtensionTimeout() {
    clearTimeout(pollTimer);
    pollTimer = null;

    if (extendCoinTotal > 0) {
      finalizeExtension(true);
    } else {
      cancelExtension(true);
    }
  }

  function pollExtension() {
    clearTimeout(pollTimer);
    if (finalizingExtension) return;

    request('/checkCoin', 'POST', { voucher: session.username })
      .then(function (result) {
        const data = responseData(result);
        const errorCode = String(data.errorCode || '');

        if (result.ok && (isTrue(data.status) || isTrue(data.success))) {
          updateExtensionDetails(data);
          extendProgressBar.style.width = '100%';
          extendCountdown.textContent = 'Renewed';
          extendStatus.textContent = 'Coin received. Insert another coin or wait for the timer.';
        } else if (errorCode === 'coin.is.reading') {
          extendStatus.textContent = 'Verifying coin, please wait…';
        } else if (errorCode === 'coin.not.inserted') {
          updateExtensionDetails(data);
          const seconds = updateExtensionCountdown(data);
          extendStatus.textContent =
            extendCoinTotal > 0
              ? 'Insert another coin to renew the timer, or press Done.'
              : 'Waiting for coin…';

          if (seconds <= 0) {
            handleExtensionTimeout();
            return;
          }
        } else if (errorCode === 'coinslot.busy') {
          if (extendCoinTotal > 0) {
            finalizeExtension(true);
          } else {
            resetExtension('Coin slot was cancelled or is busy.');
          }
          return;
        } else {
          throw new Error(data.message || errorCode || 'The coin slot reported an error.');
        }

        pollTimer = setTimeout(pollExtension, 1000);
      })
      .catch(function (error) {
        extendStatus.textContent = error.message;
        pollTimer = setTimeout(pollExtension, 2500);
      });
  }

  function startExtension(retryCount) {
    request('/topUp', 'POST', {
      voucher: session.username,
      mac: session.mac || '',
      ipAddress: session.ip || '',
      extendTime: 1,
    })
      .then(function (result) {
        const data = responseData(result);
        if (!result.ok || (!isTrue(data.status) && !isTrue(data.success))) {
          throw new Error(data.message || data.errorCode || 'Extension failed.');
        }

        updateExtensionDetails(data);
        extendStatus.textContent = 'Coin slot active. Insert a coin now.';
        extendProgressBar.style.width = '100%';
        extendCountdown.textContent = 'Ready';
        pollExtension();
      })
      .catch(function (error) {
        if (retryCount < 3) {
          setTimeout(function () {
            startExtension(retryCount + 1);
          }, 1000);
          return;
        }

        resetExtension(error.message || 'Coin slot is unavailable.');
      });
  }

  if (endSessionButton) {
    endSessionButton.onclick = function () {
      endSessionButton.disabled = true;
      endSessionButton.textContent = 'Ending…';
      endSession();
    };
  }

  if (extendButton) {
    extendButton.onclick = function () {
      if (!session.username || !vendo) return;

      clearTimeout(pollTimer);
      extendCoinTotal = 0;
      finalizingExtension = false;
      extendButton.hidden = true;
      extendBox.hidden = false;
      extendAmount.textContent = '₱0';
      extendTime.textContent = '—';
      extendProgressBar.style.width = '100%';
      extendCountdown.textContent = 'Starting…';
      extendStatus.textContent = 'Activating coin slot…';
      finishButton.disabled = true;
      cancelButton.disabled = false;

      startExtension(0);
    };
  }

  if (finishButton) {
    finishButton.onclick = function () {
      finalizeExtension(false);
    };
  }

  if (cancelButton) {
    cancelButton.onclick = function () {
      cancelExtension(false);
    };
  }

  setInterval(function () {
    if (timeLeft <= 0) return;
    timeLeft--;
    const timeLeftElement = document.getElementById('pp-left');
    if (timeLeftElement) timeLeftElement.textContent = duration(timeLeft);
  }, 1000);
})();
