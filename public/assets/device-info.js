(function () {
  "use strict";

  const context = window.PIXIEPOINT_SESSION || window.PIXIEPOINT_CONTEXT || {};
  const hostedOrigin = window.PIXIEPOINT_HOSTED_ORIGIN || "https://hs.portalx.win";
  const uuidKey = "pixiepoint:device-uuid";

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

  function storedUuid() {
    try {
      return String(localStorage.getItem(uuidKey) || "").trim().toLowerCase();
    } catch (_) {
      return "";
    }
  }

  function rememberUuid(uuid) {
    uuid = String(uuid || "").trim().toLowerCase();
    if (!uuid) return;

    try {
      localStorage.setItem(uuidKey, uuid);
    } catch (_) {}
  }

  function findCard() {
    return document.querySelector(".portal .card") || document.querySelector(".portal");
  }

  function historyMarkup(history) {
    if (!history.length) return '<p class="text-body-secondary mb-0">No purchase history yet.</p>';

    return history.map(function (item) {
      const description = item.extension ? "Time extension" : "Wi-Fi purchase";
      return `
        <div class="d-flex justify-content-between gap-3 py-2 border-bottom">
          <div>
            <strong class="d-block">${description}</strong>
            <small class="text-body-secondary">${escapeHtml(formatDate(item.created_at))}</small>
          </div>
          <span class="text-nowrap">₱${number(item.amount)} · ${duration(item.duration_seconds)}</span>
        </div>
      `;
    }).join("");
  }

  function createHistoryModal(history) {
    const old = document.getElementById("pp-history-modal");
    if (old) old.remove();

    const modalElement = document.createElement("div");
    modalElement.className = "modal fade";
    modalElement.id = "pp-history-modal";
    modalElement.tabIndex = -1;
    modalElement.setAttribute("aria-labelledby", "pp-history-title");
    modalElement.setAttribute("aria-hidden", "true");

    modalElement.innerHTML = `
      <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">
          <div class="modal-header">
            <h2 class="modal-title fs-5" id="pp-history-title">Recent history</h2>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body">${historyMarkup(history)}</div>
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

    const panel = document.createElement("section");
    panel.id = "pp-device-info";
    panel.className = "card bg-body-tertiary border-0 mt-3";
    panel.innerHTML = `
      <div class="card-body p-3">
        <div class="mb-2">
          <strong class="d-block">${data.registered ? "Registered device" : "Device details"}</strong>
          <small class="text-body-secondary">${account}</small>
        </div>

        <div class="row g-2 mb-3">
          <div class="col-6"><div class="border rounded-3 p-2 h-100"><small class="text-body-secondary d-block">Points</small><strong>${number(data.points)} pts</strong></div></div>
          <div class="col-6"><div class="border rounded-3 p-2 h-100"><small class="text-body-secondary d-block">Purchases</small><strong>${number(data.stats && data.stats.purchases)}</strong></div></div>
          <div class="col-6"><div class="border rounded-3 p-2 h-100"><small class="text-body-secondary d-block">Total purchased</small><strong>${duration(data.stats && data.stats.purchased_seconds)}</strong></div></div>
          <div class="col-6"><div class="border rounded-3 p-2 h-100"><small class="text-body-secondary d-block">Spent</small><strong>₱${number(data.stats && data.stats.spent)}</strong></div></div>
        </div>

        <button class="btn btn-outline-secondary btn-sm w-100" id="pp-history-open" type="button">Recent history</button>

        <p class="text-body-secondary small mt-2 mb-0">
          ${data.registered
            ? "Linked to your PixiePoint account. Sensitive account actions still require sign-in."
            : "Register or sign in to protect points and recover this device if its private MAC changes."}
        </p>
      </div>
    `;

    const brand = card.querySelector(".brand");
    if (brand && brand.nextSibling) card.insertBefore(panel, brand.nextSibling);
    else card.prepend(panel);

    const modal = createHistoryModal(history);
    document.getElementById("pp-history-open").onclick = function () {
      modal.show();
    };
  }

  function publish(data) {
    const uuid = data && data.device && data.device.uuid;
    if (uuid) rememberUuid(uuid);

    window.PIXIEPOINT_DEVICE_PROFILE = data;
    window.dispatchEvent(new CustomEvent("pixiepoint:device-profile", { detail: data }));
  }

  function requestProfile(uuid, allowMacFallback) {
    const query = new URLSearchParams({
      uuid: uuid || "",
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
      if (xhr.status >= 200 && xhr.status < 300) {
        try {
          const data = JSON.parse(xhr.responseText);
          if (data && data.ok) {
            publish(data);
            render(data);
            return;
          }
        } catch (_) {}
      }

      if (uuid && allowMacFallback && context.mac) {
        try { localStorage.removeItem(uuidKey); } catch (_) {}
        requestProfile("", false);
      }
    };

    xhr.onerror = xhr.ontimeout = function () {
      if (uuid && allowMacFallback && context.mac) requestProfile("", false);
    };

    xhr.send();
  }

  function load() {
    const uuid = storedUuid();
    if (uuid) {
      requestProfile(uuid, true);
      return;
    }

    if (context.mac) requestProfile("", false);
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
