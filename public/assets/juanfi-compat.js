(function () {
  "use strict";

  var context = window.PIXIEPOINT_CONTEXT || {}, vendos = window.PIXIEPOINT_VENDOS || [], selected = null;
  var pollTimer = null, activeVoucher = "";
  var $ = function (id) { return document.getElementById(id); };

  function alertMessage(message) {
    var el = $("compat-alert");
    if (!el) return;
    el.textContent = message || "";
    el.hidden = !message;
  }

  function request(path, method, data) {
    return new Promise(function (resolve, reject) {
      if (!selected || !selected.baseUrl) return reject(new Error("No local vendo address configured."));
      var xhr = new XMLHttpRequest(), url = selected.baseUrl.replace(/\/$/, "") + path;
      xhr.open(method || "GET", url, true);
      xhr.timeout = 9000;
      xhr.onreadystatechange = function () {
        if (xhr.readyState !== 4) return;
        resolve({ ok: xhr.status >= 200 && xhr.status < 300, status: xhr.status, body: xhr.responseText });
      };
      xhr.onerror = function () { reject(new Error("The local vendo did not respond.")); };
      xhr.ontimeout = function () { reject(new Error("The local vendo did not respond.")); };
      if ((method || "GET").toUpperCase() === "POST") {
        xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded; charset=UTF-8");
        xhr.send(Object.keys(data || {}).map(function (key) {
          var value = data[key];
          if (value === undefined || value === null) value = "";
          return encodeURIComponent(key) + "=" + encodeURIComponent(String(value));
        }).join("&").replace(/%20/g, "+"));
      } else xhr.send();
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
    if ($("compat-topup")) $("compat-topup").disabled = !ready;
    if ($("compat-rates")) $("compat-rates").disabled = !ready;
    if ($("compat-health")) {
      $("compat-health").textContent = message;
      $("compat-health").className = "compat-status " + (ready ? "online" : "offline");
    }
  }

  function health() {
    if (!selected) return;
    request("/health").then(function (result) {
      if (!result.ok) throw new Error("HTTP " + result.status);
      setReady(true, selected.name + " is ready");
      alertMessage("");
    }).catch(function () {
      setReady(false, selected.name + " is unavailable");
      alertMessage("PixiePoint is online, but this browser cannot reach the local coin slot. Check its power, Wi-Fi connection, and address.");
    });
  }

  function selectVendo() {
    var select = $("compat-vendo");
    selected = vendos.filter(function (v) { return select && String(v.id) === String(select.value); })[0] || vendos[0] || null;
    if (!selected) return;
    setReady(false, "Checking the local vendo…");
    health();
  }

  function init() {
    var select = $("compat-vendo");
    if (!select) return;
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
    request("/checkCoin", "POST", { voucher: activeVoucher }).then(function (result) {
      var data = responseData(result);
      if (result.ok && (isTrue(data.status) || isTrue(data.success))) displayTransaction(data);
      else if (data.errorCode !== "coin.not.inserted" && data.errorCode !== "coin.is.reading") throw new Error(data.message || data.errorCode || "The coin slot reported an error.");
      pollTimer = setTimeout(pollCoin, 1000);
    }).catch(function (error) {
      alertMessage(error.message);
      pollTimer = setTimeout(pollCoin, 3000);
    });
  }

  function beginTopup() {
    alertMessage("");
    $("compat-topup").disabled = true;
    request("/topUp", "POST", { voucher: "", mac: context.mac || "", ipAddress: context.ip || "", extendTime: 0 }).then(function (result) {
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
    voucher = String(voucher || "").trim();
    if (!voucher) return;
    var form = $("chap-login"), chap = window.PIXIEPOINT_CHAP || {};
    if (form && chap.id && chap.challenge && typeof hexMD5 === "function") {
      form.elements.namedItem("username").value = voucher;
      form.elements.namedItem("password").value = hexMD5(chap.id + voucher + chap.challenge);
      form.submit();
      return;
    }
    form = $("pap-login");
    if (!form) return;
    form.elements.namedItem("username").value = voucher;
    form.elements.namedItem("password").value = voucher;
    form.submit();
  }

  function finishTopup() {
    clearTimeout(pollTimer);
    request("/useVoucher", "POST", { voucher: activeVoucher }).then(function (result) {
      var data = responseData(result);
      if (!result.ok || (!isTrue(data.status) && !isTrue(data.success))) throw new Error(data.message || data.errorCode || "The voucher could not be activated.");
      login(activeVoucher);
    }).catch(function (error) { alertMessage(error.message); });
  }

  function cancelTopup() {
    clearTimeout(pollTimer);
    request("/cancelTopUp", "POST", { voucher: activeVoucher, mac: context.mac || "" }).catch(function () {}).then(function () {
      activeVoucher = "";
      $("compat-transaction").hidden = true;
      $("compat-topup").disabled = false;
    });
  }

  function showRates() {
    request("/getRates?rateType=1&date=" + encodeURIComponent(new Date().toISOString()), "GET").then(function (result) {
      if (!result.ok) throw new Error("Rates are unavailable.");
      var data = responseData(result), rates = Array.isArray(data) ? data : (data.rates || []), list = $("compat-rate-list");
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

  if ($("compat-vendo")) $("compat-vendo").addEventListener("change", selectVendo);
  if ($("compat-topup")) $("compat-topup").addEventListener("click", beginTopup);
  if ($("compat-finish")) $("compat-finish").addEventListener("click", finishTopup);
  if ($("compat-cancel")) $("compat-cancel").addEventListener("click", cancelTopup);
  if ($("compat-rates")) $("compat-rates").addEventListener("click", showRates);
  if ($("compat-voucher-form")) $("compat-voucher-form").addEventListener("submit", function (event) {
    event.preventDefault();
    login($("compat-voucher").value.trim().toUpperCase());
  });
  init();
}());
