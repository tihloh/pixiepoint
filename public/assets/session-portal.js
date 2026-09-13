(function () {
  'use strict';

  const session = window.PIXIEPOINT_SESSION || {};
  const $ = id => document.getElementById(id);

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

  const timeLeft = number(session.sessionTimeLeft);
  if ($('pp-time-left')) $('pp-time-left').textContent = timeLeft > 0 ? duration(timeLeft) : 'Unlimited';
  if ($('pp-data-total')) $('pp-data-total').textContent = `${bytes(session.bytesIn)} / ${bytes(session.bytesOut)}`;

  const disconnectForm = $('pp-disconnect-form');
  if (disconnectForm) disconnectForm.action = session.logoutUrl || '#';

  const endSession = $('pp-end-session');
  if (endSession) endSession.addEventListener('click', function () {
    if (session.logoutUrl) window.location.href = session.logoutUrl;
  });

  const login = $('pp-login');
  if (login) login.href = session.loginUrl || '#';
}());
