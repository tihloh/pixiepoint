(function () {
  "use strict";

  const session = window.PIXIEPOINT_DISCONNECTED || {};
  const root = document.getElementById("pixiepoint-root");

  if (!root) return;

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

  function bytes(value) {
    value = number(value);

    if (value < 1024) return `${value} B`;
    if (value < 1048576) return `${(value / 1024).toFixed(1)} KB`;
    if (value < 1073741824) return `${(value / 1048576).toFixed(1)} MB`;
    return `${(value / 1073741824).toFixed(2)} GB`;
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, function (char) {
      return {
        "&": "&amp;",
        "<": "&lt;",
        ">": "&gt;",
        '"': "&quot;",
        "'": "&#39;"
      }[char];
    });
  }

  const totalTransfer = number(session.bytesIn) + number(session.bytesOut);
  const timeLeft = number(session.sessionTimeLeft);

  document.body.className = "";

  root.outerHTML = `
    <main class="portal">
      <section class="card">
        <div class="brand">
          <div class="logo">P</div>
          <div>
            <strong>PixiePoint Wi-Fi</strong>
            <div class="muted">MikroTik hotspot access</div>
          </div>
        </div>

        <h1>Session paused</h1>
        <p class="muted">Your Wi-Fi access is disconnected, but your remaining time is preserved.</p>

        <div class="context">
          ${timeLeft > 0 ? `
            <div>
              <small>Time left</small>
              <strong>${duration(timeLeft)}</strong>
            </div>
          ` : ""}
          <div>
            <small>Used this session</small>
            <strong>${duration(session.uptime)}</strong>
          </div>
          <div>
            <small>Total transfer</small>
            <strong>${bytes(totalTransfer)}</strong>
          </div>
        </div>

        <div class="actions">
          <a class="button" href="${escapeHtml(session.loginUrl || "#")}">Resume</a>
          <a class="button secondary" href="${escapeHtml(session.loginUrl || "#")}">End session</a>
        </div>

        <p class="muted">Resume reconnects using the remaining time. End session will return to the login flow.</p>
      </section>
    </main>
  `;
}());
