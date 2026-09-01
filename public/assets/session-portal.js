(function () {
  "use strict";

  const session = window.PIXIEPOINT_SESSION || {};
  const root = document.getElementById("pixiepoint-root");
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
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
      }[char];
    });
  }

  function request(path, method, data) {
    return new Promise(function (resolve, reject) {
      if (!vendo) {
        reject(new Error("No coin slot configured."));
        return;
      }

      const xhr = new XMLHttpRequest();
      const query = method === "GET" && data
        ? `?${new URLSearchParams(data).toString()}`
        : "";

      xhr.open(method || "GET", vendo.baseUrl + path + query, true);
      xhr.timeout = 7000;

      xhr.onload = function () {
        let body = xhr.responseText;

        try {
          body = JSON.parse(body);
        } catch (_) {}

        resolve({
          ok: xhr.status >= 200 && xhr.status < 300,
          status: xhr.status,
          body: body
        });
      };

      xhr.onerror = function () {
        reject(new Error("Coin slot unavailable."));
      };

      xhr.ontimeout = function () {
        reject(new Error("Coin slot timed out."));
      };

      if (method === "POST") {
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.send(new URLSearchParams(data || {}).toString());
        return;
      }

      xhr.send();
    });
  }

  function responseData(result) {
    if (result && typeof result.body === "object") {
      return result.body || {};
    }

    try {
      return JSON.parse(result.body || "{}");
    } catch (_) {
      return {};
    }
  }

  function isTrue(value) {
    return value === true || value === 1 || value === "1" || value === "true";
  }

  function hotspotLogout(endSession, button) {
    if (!session.logoutUrl) return;

    if (button) {
      button.disabled = true;
      button.textContent = endSession ? "Ending session…" : "Disconnecting…";
    }

    const finish = function () {
      if (endSession) {
        location.replace(session.loginUrl || "/");
        return;
      }

      location.replace(session.logoutUrl || session.loginUrl || "/");
    };

    const xhr = new XMLHttpRequest();
    xhr.open("POST", session.logoutUrl, true);
    xhr.timeout = 5000;
    xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
    xhr.onload = finish;
    xhr.onerror = finish;
    xhr.ontimeout = finish;

    try {
      xhr.send(endSession ? "erase-cookie=on" : "erase-cookie=off");
    } catch (_) {
      finish();
    }
  }

  let uptime = number(session.uptime);
  let timeLeft = number(session.sessionTimeLeft);
  let pollTimer = null;

  document.body.className = "";

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
        <p class="muted">Live Wi-Fi session for ${escapeHtml(session.mac || "this device")}.</p>

        <div class="context">
          <div>
            <small>Connected</small>
            <span id="pp-uptime">${duration(uptime)}</span>
          </div>
          <div>
            <small>Time left</small>
            <span id="pp-left">${timeLeft ? duration(timeLeft) : "Unlimited"}</span>
          </div>
          <div>
            <small>Downloaded</small>
            ${bytes(session.bytesOut)}
          </div>
          <div>
            <small>Uploaded</small>
            ${bytes(session.bytesIn)}
          </div>
        </div>

        <button
          class="button secondary full"
          id="pp-extend"
          type="button"
          ${vendo && session.username ? "" : "hidden"}
        >
          Extend time
        </button>

        <div id="pp-extend-box" class="compat-transaction" hidden>
          <p id="pp-extend-status" class="muted">
            Insert coins to add time to your current voucher.
          </p>

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

          <div class="actions">
            <button class="button" id="pp-extend-finish" type="button">Save extension</button>
            <button class="button secondary" id="pp-extend-cancel" type="button">Cancel</button>
          </div>
        </div>

        <div class="actions">
          <button class="button secondary" id="pp-disconnect" type="button">Disconnect</button>
          <button class="button" id="pp-end-session" type="button">End session</button>
        </div>

        <p class="muted">
          Disconnect ends Wi-Fi access. End session clears automatic hotspot login and returns this device to the login portal.
        </p>
      </section>
    </main>
  `;

  function pollExtension() {
    clearTimeout(pollTimer);

    request("/checkCoin", "POST", { voucher: session.username })
      .then(function (result) {
        const data = responseData(result);

        if (result.ok && (isTrue(data.status) || isTrue(data.success))) {
          document.getElementById("pp-extend-amount").textContent =
            `₱${data.totalCoin || data.amount || data.coin || 0}`;

          const seconds = number(data.timeAdded);
          const addedTime = data.time || data.minutes || data.duration ||
            (seconds ? duration(seconds) : "—");

          document.getElementById("pp-extend-time").textContent = addedTime;
          document.getElementById("pp-extend-status").textContent =
            "Coins detected. You can keep adding or save the extension.";
        }

        pollTimer = setTimeout(pollExtension, 1000);
      })
      .catch(function () {
        document.getElementById("pp-extend-status").textContent =
          "Waiting for the coin slot…";

        pollTimer = setTimeout(pollExtension, 2500);
      });
  }

  const extendButton = document.getElementById("pp-extend");
  const finishButton = document.getElementById("pp-extend-finish");
  const cancelButton = document.getElementById("pp-extend-cancel");
  const disconnectButton = document.getElementById("pp-disconnect");
  const endSessionButton = document.getElementById("pp-end-session");

  if (extendButton) {
    extendButton.onclick = function () {
      const box = document.getElementById("pp-extend-box");
      const status = document.getElementById("pp-extend-status");

      box.hidden = false;
      extendButton.disabled = true;
      status.textContent = "Starting extension…";

      request("/topUp", "POST", {
        voucher: session.username,
        mac: session.mac || "",
        ipAddress: session.ip || "",
        extendTime: 1
      })
        .then(function (result) {
          const data = responseData(result);

          if (!result.ok || (!isTrue(data.status) && !isTrue(data.success))) {
            throw new Error(data.message || data.errorCode || "Extension failed.");
          }

          status.textContent = "Insert coins to add time.";
          pollExtension();
        })
        .catch(function (error) {
          status.textContent = error.message;
          extendButton.disabled = false;
        });
    };
  }

  if (finishButton) {
    finishButton.onclick = function () {
      clearTimeout(pollTimer);
      finishButton.disabled = true;

      request("/useVoucher", "POST", { voucher: session.username })
        .then(function (result) {
          const data = responseData(result);

          if (!result.ok || (!isTrue(data.status) && !isTrue(data.success))) {
            throw new Error(data.message || data.errorCode || "Could not save extension.");
          }

          document.getElementById("pp-extend-status").textContent =
            "Time extended. Refreshing status…";

          setTimeout(function () {
            location.href = session.refreshUrl || location.href;
          }, 700);
        })
        .catch(function (error) {
          document.getElementById("pp-extend-status").textContent = error.message;
          finishButton.disabled = false;
        });
    };
  }

  if (cancelButton) {
    cancelButton.onclick = function () {
      clearTimeout(pollTimer);

      request("/cancelTopUp", "POST", {
        voucher: session.username,
        mac: session.mac || ""
      })
        .catch(function () {})
        .then(function () {
          document.getElementById("pp-extend-box").hidden = true;
          extendButton.disabled = false;
        });
    };
  }

  if (disconnectButton) {
    disconnectButton.onclick = function () {
      hotspotLogout(false, disconnectButton);
    };
  }

  if (endSessionButton) {
    endSessionButton.onclick = function () {
      hotspotLogout(true, endSessionButton);
    };
  }

  setInterval(function () {
    uptime++;

    const uptimeElement = document.getElementById("pp-uptime");
    if (uptimeElement) uptimeElement.textContent = duration(uptime);

    if (timeLeft > 0) {
      timeLeft--;

      const timeLeftElement = document.getElementById("pp-left");
      if (timeLeftElement) timeLeftElement.textContent = duration(timeLeft);
    }
  }, 1000);
}());
