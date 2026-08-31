(function () {
  "use strict";

  var HEALTH_URL = "https://hs.portalx.win/hotspot/health";
  var TIMEOUT_MS = 5000;
  var RETRY_MS = 3000;
  var running = false;
  var attempts = 0;

  function text(id, value) {
    var element = document.getElementById(id);
    if (element) element.textContent = value;
  }

  function check() {
    if (running) return;
    running = true;
    attempts += 1;
    text("bootstrap-status", "Checking hosted service · attempt " + attempts);

    var controller = window.AbortController ? new AbortController() : null;
    var timer = setTimeout(function () {
      if (controller) controller.abort();
    }, TIMEOUT_MS);

    fetch(HEALTH_URL + "?t=" + Date.now(), {
      method: "GET",
      mode: "cors",
      cache: "no-store",
      credentials: "omit",
      headers: { "Accept": "application/json" },
      signal: controller ? controller.signal : undefined
    }).then(function (response) {
      if (!response.ok) throw new Error("health-http-" + response.status);
      return response.json();
    }).then(function (health) {
      if (!health || health.ready !== true) throw new Error("health-not-ready");
      clearTimeout(timer);
      text("bootstrap-status", "Service reached. Opening…");
      var form = document.getElementById("forward");
      if (form) form.submit();
    }).catch(function () {
      clearTimeout(timer);
      running = false;
      text("bootstrap-status", "Hosted service unavailable · retrying in 3 seconds");
      setTimeout(check, RETRY_MS);
    });
  }

  window.addEventListener("online", check);
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", check);
  } else {
    check();
  }
})();
