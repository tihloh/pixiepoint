(function () {
  'use strict';

  var origin = 'https://hs.portalx.win';
  var url = origin + '/hotspot/compat' + (location.search || '');
  url += (url.indexOf('?') >= 0 ? '&' : '?') + '_embed=' + Date.now();

  fetch(url, {
    method: 'GET',
    mode: 'cors',
    cache: 'no-store',
    credentials: 'omit'
  }).then(function (response) {
    if (!response.ok) throw new Error('HTTP ' + response.status);
    return response.text();
  }).then(function (html) {
    html = html.replace(/<head([^>]*)>/i, '<head$1><base href="' + origin + '/">');
    document.open();
    document.write(html);
    document.close();
  }).catch(function (error) {
    document.body.innerHTML = '<main style="font-family:sans-serif;padding:24px;max-width:560px;margin:auto"><h1>PixiePoint unavailable</h1><p>The hosted portal could not be loaded.</p><p><small>' + String(error.message || error).replace(/[&<>"']/g, function (c) { return ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]; }) + '</small></p></main>';
  });
})();
