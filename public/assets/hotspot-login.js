(function () {
  "use strict";

  var context = window.PIXIEPOINT_CONTEXT || {};
  var chap = window.PIXIEPOINT_CHAP || {};
  var $ = function (id) { return document.getElementById(id); };

  function login(voucher) {
    voucher = String(voucher || "").trim().toUpperCase();
    if (!voucher) return;

    var form = $("chap-login");
    if (form && chap.id && chap.challenge && typeof hexMD5 === "function") {
      form.action = context.loginUrl || form.action || "";
      form.elements.namedItem("username").value = voucher;
      form.elements.namedItem("password").value = hexMD5(chap.id + voucher + chap.challenge);
      var dst = form.elements.namedItem("dst");
      if (dst) dst.value = context.originalUrl || "";
      form.submit();
      return;
    }

    form = $("pap-login");
    if (!form) return;
    form.action = context.loginUrl || form.action || "";
    form.elements.namedItem("username").value = voucher;
    form.elements.namedItem("password").value = voucher;
    var dst = form.elements.namedItem("dst");
    if (dst) dst.value = context.originalUrl || "";
    form.submit();
  }

  var voucherForm = $("compat-voucher-form");
  if (voucherForm) {
    voucherForm.addEventListener("submit", function (event) {
      event.preventDefault();
      login($("compat-voucher").value);
    });
  }
}());
