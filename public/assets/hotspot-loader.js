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

  function randomVoucher() {
    const alphabet = "ABCDEFGHJKLMNPQRSTUVWXYZ23456789";
    const bytes = new Uint8Array(6);

    if (window.crypto && crypto.getRandomValues) {
      crypto.getRandomValues(bytes);
    } else {
      for (let i = 0; i < bytes.length; i++) bytes[i] = Math.floor(Math.random() * 256);
    }

    let voucher = "PP";
    for (let i = 0; i < bytes.length; i++) {
      voucher += alphabet[bytes[i] % alphabet.length];
    }
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
      if (id && document.getElementById(id)) {
        resolve();
        return;
      }

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
      if (id && document.getElementById(id)) {
        resolve();
        return;
      }

      const script = document.createElement("script");
      if (id) script.id = id;
      script.src = src;
      script.onload = resolve;
      script.onerror = reject;
      document.head.appendChild(script);
    });
  }

  async function loadPortal() {
    if (started) return;
    started = true;
    status("Hosted portal found · loading…");

    try {
      await Promise.all([
        loadStyle(
          `https://cdn.jsdelivr.net/npm/bootstrap@${bootstrapVersion}/dist/css/bootstrap.min.css`,
          "pixiepoint-bootstrap-css"
        ),
        loadStyle(`${hostedOrigin}/assets/app.css?v=${version}`, "pixiepoint-css")
      ]);

      await loadScript(
        `https://cdn.jsdelivr.net/npm/bootstrap@${bootstrapVersion}/dist/js/bootstrap.bundle.min.js`,
        "pixiepoint-bootstrap-js"
      );

      if (isStatus) {
        await loadScript(`${hostedOrigin}/assets/session-portal.js?v=${version}`, "pixiepoint-session");
      } else if (isLogin) {
        await loadScript(`${hostedOrigin}/assets/juanfi-compat.js?v=${version}`, "pixiepoint-app");
      }

      await loadScript(`${hostedOrigin}/assets/device-info.js?v=${version}`, "pixiepoint-device-info");

      if (isLogin) {
        if (window.PIXIEPOINT_DEVICE_PROFILE) {
          applyDeviceProfile(window.PIXIEPOINT_DEVICE_PROFILE);
        }
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

    const request = new XMLHttpRequest();
    request.open("GET", `${hostedOrigin}/hotspot/health?t=${Date.now()}`, true);
    request.timeout = 5000;
    request.setRequestHeader("Accept", "application/json");

    request.onload = function () {
      let health = null;
      try {
        health = JSON.parse(request.responseText);
      } catch (_) {}

      if (request.status >= 200 && request.status < 300 && health && health.ready === true) {
        loadPortal();
        return;
      }

      status("Hosted portal unavailable · retrying…");
      clearTimeout(retryTimer);
      retryTimer = setTimeout(check, 4000);
    };

    request.onerror = request.ontimeout = function () {
      status(navigator.onLine === false
        ? "No network connection · retrying…"
        : "Hosted portal unavailable · retrying…");
      clearTimeout(retryTimer);
      retryTimer = setTimeout(check, 4000);
    };

    request.send();
  }

  window.addEventListener("pixiepoint:device-profile", function (event) {
    applyDeviceProfile(event.detail || {});
  });
  window.addEventListener("online", check);
  check();
}());
