(function () {
  "use strict";

  if (window.PIXIEPOINT_JUANFI_LOADED) return;
  window.PIXIEPOINT_JUANFI_LOADED = true;

  var parentOrigin = "*", context = {}, vendos = [], selected = null;
  var pending = Object.create(null), sequence = 0, pollTimer = null, activeVoucher = "", totalCoinReceived = 0;
  var $ = function (id) { return document.getElementById(id); };
  var debugLog = window.PIXIEPOINT_DEBUG_LOG = window.PIXIEPOINT_DEBUG_LOG || [];

  function debugEnabled() {
    var query = new URLSearchParams(location.search);
    var stored = null;
    try { stored = localStorage.getItem("pixiepoint.debug"); } catch (_) {}
    if (query.get("debug") === "0" || stored === "0") return false;
    return true;
  }

  function safe(value, seen) {
    if (value === null || typeof value !== "object") return value;
    seen = seen || [];
    if (seen.indexOf(value) >= 0) return "[Circular]";
    seen.push(value);
    var output = Array.isArray(value) ? [] : {};
    Object.keys(value).forEach(function (key) {
      output[key] = /password|challenge|chap/i.test(key) ? "[REDACTED]" : safe(value[key], seen);
    });
    seen.pop();
    return output;
  }

  function trace(operation, details, level) {
    var entry = { time: new Date().toISOString(), operation: operation, details: safe(details || {}) };
    debugLog.push(entry);
    if (debugLog.length > 1000) debugLog.shift();
    if (!debugEnabled() || !window.console) return;
    var method = level === "error" ? "error" : level === "warn" ? "warn" : "log";
    console[method]("[PixiePoint Hotspot] " + operation, entry.details);
  }

  window.PIXIEPOINT_DEBUG = {
    enable: function () { try { localStorage.setItem("pixiepoint.debug", "1"); } catch (_) {} console.info("[PixiePoint Hotspot] Debug logging enabled. Reload the portal."); },
    disable: function () { try { localStorage.setItem("pixiepoint.debug", "0"); } catch (_) {} console.info("[PixiePoint Hotspot] Debug logging disabled. Run PIXIEPOINT_DEBUG.enable() to restore it."); },
    history: function () { return debugLog.slice(); },
    clear: function () { debugLog.length = 0; }
  };
  if (window.console) console.info("[PixiePoint Hotspot] Debug logger loaded. Run PIXIEPOINT_DEBUG.enable() if operation logs are disabled.");

  window.addEventListener("error", function (event) {
    trace("runtime.error", { message: event.message, file: event.filename, line: event.lineno, column: event.colno, stack: event.error && event.error.stack }, "error");
  });
  window.addEventListener("unhandledrejection", function (event) {
    trace("runtime.unhandledRejection", { reason: event.reason && (event.reason.stack || event.reason.message) || String(event.reason) }, "error");
  });

  function localRequest(path, method, data) {
    return new Promise(function (resolve, reject) {
      if (!selected || !selected.baseUrl) {
        trace("request.blocked", { path: path, reason: "No local vendo is selected", selected: selected }, "error");
        reject(new Error("No local vendo is selected."));
        return;
      }

      var xhr = new XMLHttpRequest();
      var requestId = "local-" + (++sequence) + "-" + Date.now(), startedAt = Date.now();
      var query = method === "GET" && data && Object.keys(data).length
        ? "?" + new URLSearchParams(data).toString()
        : "";
      var url = selected.baseUrl + path + query;
      trace("request.start", { id: requestId, transport: "xhr", method: method || "GET", url: url, data: data, vendo: selected });
      xhr.open(method || "GET", url, true);
      xhr.timeout = 7000;
      xhr.onload = function () {
        var body = xhr.responseText;
        try { body = JSON.parse(body); } catch (_) {}
        trace("request.response", { id: requestId, status: xhr.status, durationMs: Date.now() - startedAt, headers: xhr.getAllResponseHeaders(), body: body }, xhr.status >= 200 && xhr.status < 300 ? "debug" : "warn");
        resolve({ ok: xhr.status >= 200 && xhr.status < 300, status: xhr.status, body: body });
      };
      xhr.onerror = function () { trace("request.networkError", { id: requestId, url: url, status: xhr.status, readyState: xhr.readyState, durationMs: Date.now() - startedAt }, "error"); reject(new Error("The local vendo is unreachable.")); };
      xhr.ontimeout = function () { trace("request.timeout", { id: requestId, url: url, timeoutMs: xhr.timeout, durationMs: Date.now() - startedAt }, "error"); reject(new Error("The local vendo timed out.")); };
      if (method === "POST") {
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
        xhr.send(new URLSearchParams(data || {}).toString());
      } else {
        xhr.send();
      }
    });
  }

  function alertMessage(message) {
    trace("ui.alert", { message: message, visible: !!message }, message ? "warn" : "debug");
    var el = $("compat-alert");
    el.textContent = message || "";
    el.hidden = !message;
  }

  function rpc(path, method, data) {
    trace("rpc.execute", { path: path, method: method || "GET", data: data || {}, bootstrap: !!window.PIXIEPOINT_BOOTSTRAP });
    if (window.PIXIEPOINT_BOOTSTRAP) return localRequest(path, method || "GET", data || {});
    return new Promise(function (resolve, reject) {
      var id = "rpc-" + (++sequence) + "-" + Date.now();
      var timeout = setTimeout(function () {
        delete pending[id];
        trace("request.postMessageTimeout", { id: id, path: path, timeoutMs: 9000 }, "error");
        reject(new Error("The local vendo did not respond."));
      }, 9000);
      pending[id] = { resolve: resolve, reject: reject, timeout: timeout };
      trace("request.postMessage", { id: id, targetOrigin: parentOrigin, path: path, method: method || "GET", data: data || {} });
      window.parent.postMessage({ type: "pixiepoint:request", id: id, vendoId: selected && selected.id, path: path, method: method || "GET", data: data || {} }, parentOrigin);
    });
  }

  function responseData(result) {
    var body = result && result.body;
    if (body && typeof body === "object") return body;
    if (typeof body !== "string") return {};
    try { return JSON.parse(body); } catch (_) { return { raw: body }; }
  }

  function isTrue(value) { return value === true || value === "true" || value === 1 || value === "1"; }

  function number(value) {
    value = Number(value);
    return Number.isFinite(value) && value > 0 ? value : 0;
  }

  function currentVoucher() {
    var input = $("compat-voucher");
    return input ? input.value.trim().toUpperCase() : "";
  }

  function setCurrentVoucher(voucher) {
    voucher = String(voucher || "").trim().toUpperCase();
    var input = $("compat-voucher");
    if (input && voucher) input.value = voucher;
    return voucher;
  }

  function setReady(ready, message) {
    trace("state.vendoReady", { ready: ready, message: message, vendo: selected });
    $("compat-topup").disabled = !ready;
    $("compat-rates").disabled = !ready;
    $("compat-health").textContent = message;
    $("compat-health").className = "compat-status " + (ready ? "online" : "offline");
  }

  function health() {
    trace("operation.healthCheck", { vendo: selected });
    if (!selected) return;
    rpc("/health").then(function (result) {
      if (!result.ok) throw new Error("HTTP " + result.status);
      setReady(true, selected.name + " is ready");
      alertMessage("");
    }).catch(function (error) {
      trace("operation.healthCheckFailed", { error: error && error.message, vendo: selected }, "error");
      setReady(false, selected.name + " is unavailable");
      alertMessage("PixiePoint is online, but this browser cannot reach the local coin slot. Check its power, Wi-Fi connection, and address.");
    });
  }

  function selectVendo() {
    selected = vendos.filter(function (v) { return v.id === $("compat-vendo").value; })[0] || vendos[0] || null;
    trace("operation.selectVendo", { selected: selected, requestedId: $("compat-vendo").value });
    setReady(false, "Checking the local vendo…");
    health();
  }

  function init(data) {
    context = data.context || {};
    vendos = data.vendos || [];
    var select = $("compat-vendo");
    Array.from(select.options).forEach(function (option) {
      var vendo = vendos.filter(function (item) { return String(item.id) === String(option.value); })[0];
      if (vendo) vendo.debugEnabled = vendo.debugEnabled || option.dataset.debug === "1";
    });
    if (window.console) console.info("[PixiePoint Hotspot] Operation logging is " + (debugEnabled() ? "ENABLED" : "DISABLED") + ".", { vendos: safe(vendos), context: safe(context) });
    trace("portal.init", { context: context, vendos: vendos, bootstrap: !!window.PIXIEPOINT_BOOTSTRAP, href: location.href, userAgent: navigator.userAgent });
    select.textContent = "";
    vendos.forEach(function (vendo) {
      var option = document.createElement("option");
      option.value = vendo.id;
      option.textContent = vendo.name;
      if (vendo.interfaceName && vendo.interfaceName === context.interfaceName) option.selected = true;
      select.appendChild(option);
    });
    if (!vendos.length) {
      setReady(false, "No local vendo configured");
      alertMessage("The operator has not configured a coin slot for this hotspot.");
      return;
    }
    selectVendo();
  }

  function generatedVoucher(data) {
    return String(data.voucher || data.voucherCode || data.code || "").trim().toUpperCase();
  }

  function displayTransaction(data) {
    activeVoucher = generatedVoucher(data) || activeVoucher;
    setCurrentVoucher(activeVoucher);
    if (data.totalCoin !== undefined) totalCoinReceived = number(data.totalCoin);
    else if (data.amount !== undefined) totalCoinReceived = number(data.amount);
    else if (data.coin !== undefined) totalCoinReceived = number(data.coin);
    $("compat-code").textContent = activeVoucher || "Preparing…";
    $("compat-amount").textContent = "₱" + totalCoinReceived;
    var seconds = Number(data.timeAdded || 0), time = data.time || data.minutes || data.duration;
    if (!time && seconds) time = Math.floor(seconds / 3600) + "h " + Math.floor((seconds % 3600) / 60) + "m";
    $("compat-time").textContent = time || "—";
    $("compat-transaction").hidden = false;
    $("compat-finish").disabled = totalCoinReceived <= 0;
    $("compat-cancel").disabled = totalCoinReceived > 0;
    trace("state.transaction", { voucher: activeVoucher, totalCoin: totalCoinReceived, response: data });
  }

  function updateCountdown(data) {
    var remainMs = number(data.remainTime), waitMs = number(data.waitTime);
    var percent = waitMs > 0 ? Math.max(0, Math.min(100, remainMs / waitMs * 100)) : 100;
    var seconds = Math.max(0, Math.ceil(remainMs / 1000));
    $("compat-progress-bar").style.width = percent + "%";
    $("compat-countdown").textContent = seconds + "s";
    trace("state.countdown", { remainTime: remainMs, waitTime: waitMs, seconds: seconds, percent: percent });
    return seconds;
  }

  function pollCoin() {
    clearTimeout(pollTimer);
    if (!activeVoucher) return;
    trace("operation.pollCoin", { voucher: activeVoucher, totalCoin: totalCoinReceived });
    rpc("/checkCoin", "POST", { voucher: activeVoucher }).then(function (result) {
      var data = responseData(result), errorCode = String(data.errorCode || "");
      if (result.ok && (isTrue(data.status) || isTrue(data.success))) {
        displayTransaction(data);
        if (data.remainTime !== undefined || data.waitTime !== undefined) updateCountdown(data);
        else {
          $("compat-progress-bar").style.width = "100%";
          $("compat-countdown").textContent = "Renewed";
        }
        $("compat-progress").textContent = "Coin received. Insert another coin or press Done.";
      } else if (errorCode === "coin.not.inserted") {
        displayTransaction(data);
        var seconds = updateCountdown(data);
        $("compat-progress").textContent = totalCoinReceived > 0 ? "Insert another coin or press Done." : "Waiting for coin…";
        if (data.remainTime !== undefined && seconds <= 0) {
          clearTimeout(pollTimer);
          $("compat-progress").textContent = "Coin slot expired.";
          $("compat-topup").disabled = false;
          return;
        }
      } else if (errorCode === "coin.is.reading") {
        if (data.remainTime !== undefined || data.waitTime !== undefined) updateCountdown(data);
        $("compat-progress").textContent = "Verifying coin, please wait…";
      } else {
        throw new Error(data.message || "The coin slot reported an error.");
      }
      pollTimer = setTimeout(pollCoin, 1000);
    }).catch(function (error) {
      trace("operation.pollCoinFailed", { error: error && error.message, voucher: activeVoucher }, "error");
      $("compat-progress").textContent = error.message;
      pollTimer = setTimeout(pollCoin, 3000);
    });
  }

  function beginTopup() {
    alertMessage("");
    totalCoinReceived = 0;
    activeVoucher = currentVoucher();
    trace("action.beginTopup", { voucher: activeVoucher, mac: context.mac, ip: context.ip, vendo: selected });
    $("compat-topup").disabled = true;
    $("compat-code").textContent = activeVoucher || "Generating…";
    $("compat-amount").textContent = "₱0";
    $("compat-time").textContent = "—";
    $("compat-progress-bar").style.width = "100%";
    $("compat-countdown").textContent = "Starting…";
    $("compat-progress").textContent = "Activating coin slot…";
    $("compat-transaction").hidden = false;
    rpc("/topUp", "POST", { voucher: activeVoucher, mac: context.mac || "", ipAddress: context.ip || "", extendTime: 0 }).then(function (result) {
      var data = responseData(result);
      if (!result.ok || (!isTrue(data.status) && !isTrue(data.success))) throw new Error(data.message || data.errorCode || "The coin slot rejected the request.");
      displayTransaction(data);
      if (!activeVoucher) throw new Error("The coin slot did not return a voucher code.");
      $("compat-countdown").textContent = "Ready";
      $("compat-progress").textContent = "Coin slot active. Insert a coin now.";
      pollCoin();
    }).catch(function (error) {
      trace("action.beginTopupFailed", { error: error && error.message, voucher: activeVoucher }, "error");
      alertMessage(error.message);
      $("compat-transaction").hidden = true;
      $("compat-topup").disabled = false;
    });
  }

  function login(voucher) {
    voucher = String(voucher || "").trim().toUpperCase();
    if (!voucher) return;
    trace("action.login", { voucher: voucher, transport: window.PIXIEPOINT_BOOTSTRAP ? "form" : "postMessage", passwordMode: selected && selected.passwordMode, loginUrl: context.loginUrl });
    if (window.PIXIEPOINT_BOOTSTRAP) {
      var password = selected && selected.passwordMode === "voucher" ? voucher : "";
      var chap = window.PIXIEPOINT_CHAP || {};
      var form = chap.id ? $("chap-login") : $("pap-login");
      if (!form || !form.elements.namedItem("username") || !form.elements.namedItem("password")) {
        alertMessage("The hotspot login form is unavailable. Please reload the Wi-Fi portal.");
        return;
      }
      form.elements.namedItem("username").value = voucher;
      form.elements.namedItem("password").value = chap.id
        ? hexMD5(chap.id + password + chap.challenge)
        : password;
      form.submit();
      return;
    }
    window.parent.postMessage({ type: "pixiepoint:login", voucher: voucher, vendoId: selected && selected.id }, parentOrigin);
  }

  function finishTopup() {
    trace("action.finishTopup", { voucher: activeVoucher, totalCoin: totalCoinReceived });
    clearTimeout(pollTimer);
    rpc("/useVoucher", "POST", { voucher: activeVoucher }).then(function (result) {
      var data = responseData(result);
      if (!result.ok || (!isTrue(data.status) && !isTrue(data.success))) throw new Error(data.message || data.errorCode || "The voucher could not be activated.");
      login(activeVoucher);
    }).catch(function (error) { trace("action.finishTopupFailed", { error: error && error.message, voucher: activeVoucher }, "error"); alertMessage(error.message); });
  }

  function cancelTopup() {
    trace("action.cancelTopup", { voucher: activeVoucher, totalCoin: totalCoinReceived });
    clearTimeout(pollTimer);
    rpc("/cancelTopUp", "POST", { voucher: activeVoucher, mac: context.mac || "" }).catch(function () {}).then(function () {
      activeVoucher = "";
      totalCoinReceived = 0;
      $("compat-transaction").hidden = true;
      $("compat-topup").disabled = false;
    });
  }

  function showRates() {
    trace("action.showRates", { vendo: selected });
    rpc("/getRates?rateType=1&date=" + encodeURIComponent(new Date().toISOString()), "GET").then(function (result) {
      if (!result.ok) throw new Error("Rates are unavailable.");
      var data = responseData(result), rates = Array.isArray(data) ? data : (data.rates || []);
      var list = $("compat-rate-list");
      list.textContent = "";
      rates.forEach(function (rate) {
        var row = document.createElement("div");
        row.className = "compat-rate";
        row.textContent = "₱" + (rate.amount || rate.price || rate.coin || "—") + " · " + (rate.time || rate.minutes || rate.duration || "—");
        list.appendChild(row);
      });
      if (!rates.length) list.textContent = typeof data.raw === "string" ? data.raw : "No rates were returned.";
      list.hidden = false;
    }).catch(function (error) { trace("action.showRatesFailed", { error: error && error.message }, "error"); alertMessage(error.message); });
  }

  window.addEventListener("message", function (event) {
    var data = event.data || {};
    if (data.type && String(data.type).indexOf("pixiepoint:") === 0) trace("message.received", { origin: event.origin, data: data });
    if (data.type === "pixiepoint:init") {
      parentOrigin = event.origin;
      init(data);
    } else if (data.type === "pixiepoint:response" && pending[data.id]) {
      var request = pending[data.id];
      clearTimeout(request.timeout);
      delete pending[data.id];
      trace("request.postMessageResponse", { id: data.id, error: data.error, result: data.result }, data.error ? "error" : "debug");
      data.error ? request.reject(new Error(data.error)) : request.resolve(data.result || {});
    }
  });

  if ($("compat-vendo")) $("compat-vendo").addEventListener("change", selectVendo);
  if ($("compat-topup")) $("compat-topup").addEventListener("click", beginTopup);
  if ($("compat-finish")) $("compat-finish").addEventListener("click", finishTopup);
  if ($("compat-cancel")) $("compat-cancel").addEventListener("click", cancelTopup);
  if ($("compat-rates")) $("compat-rates").addEventListener("click", showRates);
  if ($("compat-voucher-form")) $("compat-voucher-form").addEventListener("submit", function (event) {
    event.preventDefault();
    login($("compat-voucher").value.trim().toUpperCase());
  });
  if (window.PIXIEPOINT_BOOTSTRAP && $("compat-vendo")) {
    init({ context: window.PIXIEPOINT_CONTEXT || {}, vendos: window.PIXIEPOINT_VENDOS || [] });
  } else if (!window.PIXIEPOINT_BOOTSTRAP) {
    trace("portal.readyMessage", { targetOrigin: "*" });
    window.parent.postMessage({ type: "pixiepoint:ready" }, "*");
  }
}());
