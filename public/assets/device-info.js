(function () {
  "use strict";

  const context = window.PIXIEPOINT_SESSION || window.PIXIEPOINT_CONTEXT || {};
  const hostedOrigin = window.PIXIEPOINT_HOSTED_ORIGIN || "https://hs.portalx.win";
  const deviceUuidKey = "pixiepoint:device-uuid";

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
    return document.querySelector(".portal > .card") || document.querySelector(".portal");
  }

  function historyMarkup(history) {
    if (!history.length) {
      return '<p class="text-body-secondary mb-0">No purchase history yet.</p>';
    }

    return history.map(function (item) {
      const description = item.extension ? "Time extension" : "Wi-Fi purchase";
      return `
        <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
          <div class="min-w-0">
            <strong class="d-block">${description}</strong>
            <small class="text-body-secondary">${escapeHtml(formatDate(item.created_at))}</small>
          </div>
          <span class="text-nowrap">₱${number(item.amount)} · ${duration(item.duration_seconds)}</span>
        </div>
      `;
    }).join("");
  }

  function createDetailsModal(data, account, history) {
    const existing = document.getElementById("pp-device-modal");
    if (existing) existing.remove();

    const modalElement = document.createElement("div");
    modalElement.className = "modal fade";
    modalElement.id = "pp-device-modal";
    modalElement.tabIndex = -1;
    modalElement.setAttribute("aria-labelledby", "pp-device-modal-title");
    modalElement.setAttribute("aria-hidden", "true");

    modalElement.innerHTML = `
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <div>
              <h2 class="modal-title fs-5 mb-0" id="pp-device-modal-title">Device details</h2>
              <small class="text-body-secondary">${account}</small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">
            <div class="row g-2 mb-3">
              <div class="col-6">
                <div class="border rounded-3 p-2 h-100">
                  <small class="text-body-secondary d-block">Points</small>
                  <strong>${number(data.points)} pts</strong>
                </div>
              </div>
              <div class="col-6">
                <div class="border rounded-3 p-2 h-100">
                  <small class="text-body-secondary d-block">Purchases</small>
                  <strong>${number(data.stats && data.stats.purchases)}</strong>
                </div>
              </div>
              <div class="col-6">
                <div class="border rounded-3 p-2 h-100">
                  <small class="text-body-secondary d-block">Total purchased</small>
                  <strong>${duration(data.stats && data.stats.purchased_seconds)}</strong>
                </div>
              </div>
              <div class="col-6">
                <div class="border rounded-3 p-2 h-100">
                  <small class="text-body-secondary d-block">Spent</small>
                  <strong>₱${number(data.stats && data.stats.spent)}</strong>
                </div>
              </div>
            </div>

            <h3 class="fs-6 mb-2">Recent history</h3>
            ${historyMarkup(history)}

            <p class="text-body-secondary small mt-3 mb-0">
              ${data.registered
                ? "Linked to your PixiePoint account. Sensitive account actions still require sign-in."
                : "Register or sign in to protect points and recover this device if its private MAC changes."}
            </p>
          </div>
        </div>
      </div>
    `;

    document.body.appendChild(modalElement);
    return new bootstrap.Modal(modalElement);
  }

  function render(data) {
    const card = findCard();
    if (!card || document.getElementById("pp-device-info")) return;

    const account = data.account && data.account.linked
      ? escapeHtml(data.account.name || "Linked account")
      : "Guest device";
    const history = Array.isArray(data.history) ? data.history : [];

    const panel = document.createElement("button");
    panel.id = "pp-device-info";
    panel.type = "button";
    panel.className = "btn btn-outline-secondary w-100 d-flex align-items-center justify-content-between gap-2 mt-2 text-start";
    panel.innerHTML = `
      <span class="min-w-0">
        <strong class="d-block text-truncate">${account}</strong>
        <small class="text-body-secondary d-block text-truncate">${number(data.points)} pts · ${number(data.stats && data.stats.purchases)} purchases</small>
      </span>
      <span aria-hidden="true">›</span>
    `;

    card.appendChild(panel);

    const modal = createDetailsModal(data, account, history);
    panel.onclick = function () {
      modal.show();
    };
  }

  function storedUuid() {
    try {
      return localStorage.getItem(deviceUuidKey) || "";
    } catch (_) {
      return "";
    }
  }

  function rememberUuid(uuid) {
    if (!uuid) return;
    try {
      localStorage.setItem(deviceUuidKey, uuid);
    } catch (_) {}
  }

  function load() {
    const uuid = storedUuid();
    if (!uuid && !context.mac) return;

    const query = new URLSearchParams({
      uuid: uuid,
      mac: uuid ? "" : (context.mac || ""),
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
        if (!data || !data.ok) return;

        rememberUuid(data.device && data.device.uuid);
        window.PIXIEPOINT_DEVICE_PROFILE = data;
        window.dispatchEvent(new CustomEvent("pixiepoint:device-profile", { detail: data }));
        render(data);
      } catch (_) {}
    };

    xhr.send();
  }

  let attempts = 0;
  const waitForPortal = setInterval(function () {
    attempts++;
    if (findCard() && window.bootstrap && bootstrap.Modal) {
      clearInterval(waitForPortal);
      load();
      return;
    }

    if (attempts >= 30) clearInterval(waitForPortal);
  }, 200);
}());
