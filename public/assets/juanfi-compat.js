(function () {
  "use strict";

  var parentOrigin = "*", context = {}, vendos = [], selected = null;
  var pending = Object.create(null), sequence = 0, pollTimer = null, activeVoucher = "";
  var $ = function (id) { return document.getElementById(id); };

  function alertMessage(message) {
    var el = $("compat-alert");
    el.textContent = message || "";
    el.hidden = !message;
  }

  function rpc(path, method, data) {
    return new Promise(function (resolve, reject) {
      var id = "rpc-" + (++sequence) + "-" + Date.now();
      var timeout = setTimeout(function () {
        delete pending[id];
        reject(new Error("The local vendo did not respond."));
      }, 9000);
      pending[id] = { resolve: resolve, reject: reject, timeout: timeout };
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

  function setReady(ready, message) {
    $("compat-topup").disabled = !ready;
    $("compat-rates").disabled = !ready;
    $("compat-health").textContent = message;
    $("compat-health").className = "compat-status " + (ready ? "online" : "offline");
  }

  function health() {
    if (!selected) return;
    rpc("/health").then(function (result) {
      if (!result.ok) throw new Error("HTTP " + result.status);
      setReady(true, selected.name + " is ready");
      alertMessage("");
    }).catch(function () {
      setReady(false, selected.name + " is unavailable");
      alertMessage("PixiePoint is online, but this browser cannot reach the local coin slot. Check its power, Wi-Fi connection, and address.");
    });
  }

  function selectVendo() {
    selected = vendos.filter(function (v) { return v.id === $("compat-vendo").value; })[0] || vendos[0] || null;
    setReady(false, "Checking the local vendo…");
    health();
  }

  function init(data) {
    context = data.context || {};
    vendos = data.vendos || [];
    var select = $("compat-vendo");
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
    return String(data.voucher || data.voucherCode || data.code || "").trim();
  }

  function displayTransaction(data) {
    activeVoucher = generatedVoucher(data) || activeVoucher;
    $("compat-code").textContent = activeVoucher || "Preparing…";
    $("compat-amount").textContent = "₱" + (data.totalCoin || data.amount || data.coin || 0);
    var seconds = Number(data.timeAdded || 0), time = data.time || data.minutes || data.duration;
    if (!time && seconds) time = Math.floor(seconds / 3600) + "h " + Math.floor((seconds % 3600) / 60) + "m";
    $("compat-time").textContent = time || "—";
    $("compat-transaction").hidden = false;
  }

  function pollCoin() {
    clearTimeout(pollTimer);
    if (!activeVoucher) return;
    rpc("/checkCoin", "POST", { voucher: activeVoucher }).then(function (result) {
      var data = responseData(result);
      if (result.ok && (isTrue(data.status) || isTrue(data.success))) displayTransaction(data);
      else if (data.errorCode !== "coin.not.inserted" && data.errorCode !== "coin.is.reading") {
        throw new Error(data.message || "The coin slot reported an error.");
      }
      pollTimer = setTimeout(pollCoin, 1000);
    }).catch(function (error) {
      alertMessage(error.message);
      pollTimer = setTimeout(pollCoin, 3000);
    });
  }

  function beginTopup() {
    alertMessage("");
    $("compat-topup").disabled = true;
    rpc("/topUp", "POST", { voucher: "", mac: context.mac || "" }).then(function (result) {
      var data = responseData(result);
      if (!result.ok || (!isTrue(data.status) && !isTrue(data.success))) throw new Error(data.message || data.errorCode || "The coin slot rejected the request.");
      displayTransaction(data);
      if (!activeVoucher) throw new Error("The coin slot did not return a voucher code.");
      pollCoin();
    }).catch(function (error) {
      alertMessage(error.message);
      $("compat-topup").disabled = false;
    });
  }

  function login(voucher) {
    window.parent.postMessage({ type: "pixiepoint:login", voucher: voucher, vendoId: selected && selected.id }, parentOrigin);
  }

  function finishTopup() {
    clearTimeout(pollTimer);
    rpc("/useVoucher", "POST", { voucher: activeVoucher }).then(function (result) {
      var data = responseData(result);
      if (!result.ok || (!isTrue(data.status) && !isTrue(data.success))) throw new Error(data.message || data.errorCode || "The voucher could not be activated.");
      login(activeVoucher);
    }).catch(function (error) { alertMessage(error.message); });
  }

  function cancelTopup() {
    clearTimeout(pollTimer);
    rpc("/cancelTopUp", "POST", { voucher: activeVoucher, mac: context.mac || "" }).catch(function () {}).then(function () {
      activeVoucher = "";
      $("compat-transaction").hidden = true;
      $("compat-topup").disabled = false;
    });
  }

  function showRates() {
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
    }).catch(function (error) { alertMessage(error.message); });
  }

  window.addEventListener("message", function (event) {
    var data = event.data || {};
    if (data.type === "pixiepoint:init") {
      parentOrigin = event.origin;
      init(data);
    } else if (data.type === "pixiepoint:response" && pending[data.id]) {
      var request = pending[data.id];
      clearTimeout(request.timeout);
      delete pending[data.id];
      data.error ? request.reject(new Error(data.error)) : request.resolve(data.result || {});
    }
  });

  $("compat-vendo").addEventListener("change", selectVendo);
  $("compat-topup").addEventListener("click", beginTopup);
  $("compat-finish").addEventListener("click", finishTopup);
  $("compat-cancel").addEventListener("click", cancelTopup);
  $("compat-rates").addEventListener("click", showRates);
  $("compat-voucher-form").addEventListener("submit", function (event) {
    event.preventDefault();
    login($("compat-voucher").value.trim().toUpperCase());
  });
  window.parent.postMessage({ type: "pixiepoint:ready" }, "*");
}());
