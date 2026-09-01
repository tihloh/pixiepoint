(function () {
  "use strict";

  const context = window.PIXIEPOINT_SESSION || window.PIXIEPOINT_CONTEXT || {};
  const hostedOrigin = window.PIXIEPOINT_HOSTED_ORIGIN || "https://hs.portalx.win";
  const uuidKey = "pixiepoint:device-uuid";
  const isStatus = !!window.PIXIEPOINT_SESSION;

  function number(value) {
    value = Number(value);
    return Number.isFinite(value) && value > 0 ? Math.floor(value) : 0;
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
    if (!value) return "—";
    const parsed = new Date(String(value).replace(" ", "T"));
    if (Number.isNaN(parsed.getTime())) return value;
    return parsed.toLocaleString([], {
      month: "short",
      day: "numeric",
      year: "numeric",
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
    try { localStorage.setItem(uuidKey, uuid); } catch (_) {}
  }

  function findCard() {
    return document.querySelector(".portal .card") || document.querySelector(".portal");
  }

  function row(label, value) {
    return `
      <div class="d-flex justify-content-between gap-3 py-1 border-bottom border-secondary-subtle small">
        <span class="text-body-secondary">${escapeHtml(label)}</span>
        <strong class="text-end text-break">${escapeHtml(value || "—")}</strong>
      </div>
    `;
  }

  function render(data) {
    const card = findCard();
    if (!card || document.getElementById("pp-device-info")) return;

    const device = data.device || {};
    const account = data.account && data.account.linked
      ? data.account.name || "Linked account"
      : "Guest device";
    const voucher = String(data.saved_voucher || "").trim();

    const panel = document.createElement("details");
    panel.id = "pp-device-info";
    panel.className = "border rounded-3 mt-3 px-3 py-2";
    panel.open = true;
    panel.innerHTML = `
      <summary class="fw-bold py-1">Device details</summary>
      <div class="pt-2">
        ${row("Device / Account", account)}
        ${row("IP Address", device.ip || context.ip || "—")}
        ${row("MAC Address", device.mac || context.mac || "—")}
        ${row("Connected", formatDate(device.first_seen_at))}
        ${row("Last seen", formatDate(device.last_seen_at))}
        ${row("Total spent", `₱${number(data.stats && data.stats.spent).toFixed(2)}`)}
        ${row("Points", `${number(data.points)} pts`)}
        ${row("Purchases", String(number(data.stats && data.stats.purchases)))}
        <div class="py-2 border-bottom border-secondary-subtle small">
          <span class="text-body-secondary d-block mb-1">Last voucher</span>
          <span class="badge text-bg-primary">${escapeHtml(voucher || "None")}</span>
        </div>
        ${row("UUID", device.uuid || "—")}
      </div>
    `;

    if (isStatus) {
      card.appendChild(panel);
    } else {
      const brand = card.querySelector(".brand");
      if (brand && brand.nextSibling) card.insertBefore(panel, brand.nextSibling);
      else card.prepend(panel);
    }
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
    if (findCard() && window.bootstrap) {
      clearInterval(waitForPortal);
      load();
      return;
    }
    if (attempts >= 30) clearInterval(waitForPortal);
  }, 200);
}());
