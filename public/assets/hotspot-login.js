(function() {
  'use strict';
  var form = document.getElementById('compat-voucher-form'),
    input = document.getElementById('compat-voucher');
  if (!form || !input) return;
  form.addEventListener('submit', function() {
    input.value = String(input.value || '').trim().toUpperCase();
  });
}());