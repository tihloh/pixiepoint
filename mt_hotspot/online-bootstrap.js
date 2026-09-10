(function () {
  "use strict";

  var TIMEOUT_MS = 7000;
  var RETRY_MS = 3000;
  var running = false;
  var attempts = 0;

  function text(id, value) {
    var element = document.getElementById(id);
    if (element) element.textContent = value;
  }

  function requestTarget(form) {
    var method = String(form.method || "GET").toUpperCase();
    var data = new URLSearchParams(new FormData(form));
    var url = form.action;
    var options = {
      method: method,
      mode: "cors",
      cache: "no-store",
      credentials: "omit",
      headers: { "Accept": "text/html" }
    };

    if (method === "GET") {
      var query = data.toString();
      if (query) url += (url.indexOf("?") >= 0 ? "&" : "?") + query;
    } else {
      options.headers["Content-Type"] = "application/x-www-form-urlencoded;charset=UTF-8";
      options.body = data.toString();
    }

    return { url: url, options: options };
  }

  function load() {
    if (running) return;

    var form = document.getElementById("forward");
    if (!form || !form.action) {
      text("bootstrap-status", "Hosted portal configuration is unavailable");
      return;
    }

    running = true;
    attempts += 1;
    text("bootstrap-status", attempts === 1 ? "Opening hosted portal…" : "Retrying hosted portal · attempt " + attempts);

    var request = requestTarget(form);
    var controller = window.AbortController ? new AbortController() : null;
    var timer = setTimeout(function () {
      if (controller) controller.abort();
    }, TIMEOUT_MS);

    if (controller) request.options.signal = controller.signal;

    fetch(request.url, request.options)
      .then(function (response) {
        if (!response.ok) throw new Error("portal-http-" + response.status);
        return response.text();
      })
      .then(function (html) {
        clearTimeout(timer);
        if (!html || !html.trim()) throw new Error("portal-empty");
        document.open();
        document.write(html);
        document.close();
      })
      .catch(function () {
        clearTimeout(timer);
        running = false;
        text("bootstrap-status", "Hosted portal unavailable · retrying in 3 seconds");
        setTimeout(load, RETRY_MS);
      });
  }

  window.addEventListener("online", load);
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", load);
  } else {
    load();
  }
})();
