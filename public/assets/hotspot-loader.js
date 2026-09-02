(function () {
  "use strict";

  const hostedOrigin = window.PIXIEPOINT_HOSTED_ORIGIN || "https://hs.portalx.win";
  const bootstrapVersion = "5.3.8";
  const version = Date.now();
  const isLogin = !!window.PIXIEPOINT_CONTEXT;
  const isStatus = !!window.PIXIEPOINT_SESSION;
  let started = false;
  let retryTimer = 0;
  let voucherResolved = false;

  function status(message) {
    const element = document.getElementById("boot-status");
    if (element) element.textContent = message;
  }

  function request(url, type) {
    return new Promise(function (resolve, reject) {
      const xhr = new XMLHttpRequest();
      xhr.open("GET", url, true);
      xhr.timeout = 7000;
      if (type) xhr.setRequestHeader("Accept", type);
      xhr.onload = function () {
        if (xhr.status >= 200 && xhr.status < 300) resolve(xhr.responseText);
        else reject(new Error("HTTP " + xhr.status));
      };
      xhr.onerror = xhr.ontimeout = function () { reject(new Error("Request failed")); };
      xhr.send();
    });
  }

  function randomVoucher() {
    const alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    const bytes = new Uint8Array(6);

    if (window.crypto && crypto.getRandomValues) {
      crypto.getRandomValues(bytes);
    } else {
      for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
    }

    let voucher = "PP";
    for (let i = 0; i < bytes.length; i++) voucher += alphabet[bytes[i] % alphabet.length];
    return voucher;
  }

  function setVoucher(voucher, force) {
    if (!isLogin) return;
    const input = document.getElementById("compat-voucher");
    if (!input) return;

    voucher = String(voucher || "").trim().toUpperCase();
    if (!voucher) return;
    if (!force && input.value.trim() !== "") return;

    input.value = voucher;
    voucherResolved = true;
  }

  function applyDeviceProfile(profile) {
    if (!isLogin || !profile || !profile.ok) return;
    const savedVoucher = String(profile.saved_voucher || "").trim();
    if (savedVoucher) {
      setVoucher(savedVoucher, true);
      return;
    }
    if (!voucherResolved) setVoucher(randomVoucher(), false);
  }

  function ensureVoucherFallback() {
    if (!isLogin || voucherResolved) return;
    setVoucher(randomVoucher(), false);
  }

  function loadStyle(href, id) {
    return new Promise(function (resolve, reject) {
      if (id && document.getElementById(id)) return resolve();
      const link = document.createElement("link");
      if (id) link.id = id;
      link.rel = "stylesheet";
      link.href = href;
      link.onload = resolve;
      link.onerror = reject;
      document.head.appendChild(link);
    });
  }

  function loadScript(src, id) {
    return new Promise(function (resolve, reject) {
      if (id && document.getElementById(id)) return resolve();
      const script = document.createElement("script");
      if (id) script.id = id;
      script.src = src;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }

  async function loadLoginMarkup() {
    const root = document.getElementById("pixiepoint-root");
    if (!root) throw new Error("Portal root missing");

    const html = await request(`${hostedOrigin}/hotspot/compat?fragment=1&v=${version}`, "text/html");
    root.innerHTML = html;
  }

  async function loadVendos() {
    const context = window.PIXIEPOINT_CONTEXT || {};
    const query = new URLSearchParams({
      router_identity: context.routerIdentity || "",
      interface: context.interfaceName || ""
    });

    const raw = await request(`${hostedOrigin}/hotspot/vendos?${query.toString()}&v=${version}`, "application/json");
    const data = JSON.parse(raw);
    window.PIXIEPOINT_VENDOS = data && data.ok && Array.isArray(data.vendos) ? data.vendos : [];
  }

  async function loadPortal() {
    if (started) return;
    started = true;
    status("Hosted portal found · loading…");

    try {
      await Promise.all([
        loadStyle(`https://cdn.jsdelivr.net/npm/bootstrap@${bootstrapVersion}/dist/css/bootstrap.min.css`, "pixiepoint-bootstrap-css"),
        loadStyle(`${hostedOrigin}/assets/app.css?v=${version}`, "pixiepoint-css")
      ]);

      await loadScript(`https://cdn.jsdelivr.net/npm/bootstrap@${bootstrapVersion}/dist/js/bootstrap.bundle.min.js`, "pixiepoint-bootstrap-js");

      if (isLogin) {
        await Promise.all([loadLoginMarkup(), loadVendos()]);
        await loadScript(`${hostedOrigin}/assets/juanfi-compat.js?v=${version}`, "pixiepoint-app");
      } else if (isStatus) {
        await loadScript(`${hostedOrigin}/assets/session-portal.js?v=${version}`, "pixiepoint-session");
      }

      await loadScript(`${hostedOrigin}/assets/device-info.js?v=${version}`, "pixiepoint-device-info");

      if (isLogin) {
        if (window.PIXIEPOINT_DEVICE_PROFILE) applyDeviceProfile(window.PIXIEPOINT_DEVICE_PROFILE);
        setTimeout(ensureVoucherFallback, 1500);
      }
    } catch (_) {
      started = false;
      status("Hosted portal assets unavailable · retrying…");
      clearTimeout(retryTimer);
      retryTimer = setTimeout(check, 4000);
    }
  }

  function check() {
    if (started) return;

    const requestHealth = new XMLHttpRequest();
    requestHealth.open("GET", `${hostedOrigin}/hotspot/health?t=${Date.now()}`, true);
    requestHealth.timeout = 5000;
    requestHealth.setRequestHeader("Accept", "application/json");

    requestHealth.onload = function () {
      let health = null;
      try { health = JSON.parse(requestHealth.responseText); } catch (_) {}

      if (requestHealth.status >= 200 && requestHealth.status < 300 && health && health.ready === true) {
        loadPortal();
        return;
      }

      status("Hosted portal unavailable · retrying…");
      clearTimeout(retryTimer);
      retryTimer = setTimeout(check, 4000);
    };

    requestHealth.onerror = requestHealth.ontimeout = function () {
      status(navigator.onLine === false ? "No network connection · retrying…" : "Hosted portal unavailable · retrying…");
      clearTimeout(retryTimer);
      retryTimer = setTimeout(check, 4000);
    };

    requestHealth.send();
  }

  window.addEventListener("pixiepoint:device-profile", function (event) {
    applyDeviceProfile(event.detail || {});
  });
  window.addEventListener("online", check);
  check();
}());
