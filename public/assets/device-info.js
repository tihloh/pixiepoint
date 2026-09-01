(function () {
  "use strict";

  const context = window.PIXIEPOINT_SESSION || window.PIXIEPOINT_CONTEXT || {};
  const hostedOrigin = window.PIXIEPOINT_HOSTED_ORIGIN || "https://hs.portalx.win";

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

  function escapeHtml(value) {
    return String(value ?? "").replace(/[&<>"']/g, function (char) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
      }[char];
    });
  }

  function formatDate(value) {
    if (!value) return "";
    const parsed = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleString([], {
      month: "short",
      day: "numeric",
      hour: "numeric",
      minute: "2-digit"
    });
  }

  function findCard() {
    return document.querySelector(".portal .card") || document.querySelector(".portal");
  }

  function render(data) {
    const card = findCard();
    if (!card || document.getElementById("pp-device-info")) return;

    const timeLeft = number(context.sessionTimeLeft);
    const account = data.account && data.account.linked
      ? escapeHtml(data.account.name || "Linked account")
      : "Guest device";

    const history = Array.isArray(data.history) ? data.history : [];
    const historyHtml = history.length
      ? history.map(function (item) {
          const description = item.extension ? "Time extension" : "Wi-Fi purchase";
          return `
            <div class="compat-rate">
              <div>
                <strong>${description}</strong>
                <small>${escapeHtml(formatDate(item.created_at))}</small>
              </div>
              <span>₱${number(item.amount)} · ${duration(item.duration_seconds)}</span>
            </div>
          `;
        }).join("")
      : '<p class="muted">No purchase history yet.</p>';

    const panel = document.createElement("section");
    panel.id = "pp-device-info";
    panel.className = "compat-transaction";
    panel.innerHTML = `
      <div class="brand">
        <div>
          <strong>${data.registered ? "Registered device" : "Device details"}</strong>
          <div class="muted">${account}</div>
        </div>
      </div>

      <div class="context">
        <div>
          <small>Points</small>
          <strong>${number(data.points)} pts</strong>
        </div>
        ${timeLeft > 0 ? `
          <div>
            <small>Time left</small>
            <strong>${duration(timeLeft)}</strong>
          </div>
        ` : ""}
        <div>
          <small>Purchases</small>
          <strong>${number(data.stats && data.stats.purchases)}</strong>
        </div>
        <div>
          <small>Total purchased</small>
          <strong>${duration(data.stats && data.stats.purchased_seconds)}</strong>
        </div>
      </div>

      <details>
        <summary>Recent history</summary>
        <div class="compat-rate-list">${historyHtml}</div>
      </details>

      ${data.registered
        ? '<p class="muted">This device is linked to your PixiePoint account. Sensitive account actions still require sign-in.</p>'
        : '<p class="muted">Register or sign in to protect points and reconnect this device if its private MAC changes.</p>'}
    `;

    const brand = card.querySelector(".brand");
    if (brand && brand.nextSibling) {
      card.insertBefore(panel, brand.nextSibling);
    } else {
      card.prepend(panel);
    }
  }

  function load() {
    if (!context.mac) return;

    const query = new URLSearchParams({
      mac: context.mac || "",
      ip: context.ip || "",
      router_identity: context.routerIdentity || "",
      interface: context.interfaceName || ""
    });

    const xhr = new XMLHttpRequest();
    xhr.open("GET", `${hostedOrigin}/hotspot/device-info?${query.toString()}`, true);
    xhr.timeout = 5000;
    xhr.setRequestHeader("Accept", "application/json");

    xhr.onload = function () {
      if (xhr.status < 200 || xhr.status >= 300) return;

      try {
        const data = JSON.parse(xhr.responseText);
        if (data && data.ok) render(data);
      } catch (_) {}
    };

    xhr.send();
  }

  let attempts = 0;
  const waitForPortal = setInterval(function () {
    attempts++;
    if (findCard()) {
      clearInterval(waitForPortal);
      load();
      return;
    }

    if (attempts >= 30) clearInterval(waitForPortal);
  }, 200);
}());
