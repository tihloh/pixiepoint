(function(){
  'use strict';
  function $(id){return document.getElementById(id);}
  function login(voucher){
    voucher=String(voucher||'').trim().toUpperCase();
    if(!voucher)return;
    var chap=window.PIXIEPOINT_CHAP||{},form=$('chap-login');
    if(form&&chap.id&&chap.challenge&&typeof hexMD5==='function'){
      form.elements.namedItem('username').value=voucher;
      form.elements.namedItem('password').value=hexMD5(chap.id+voucher+chap.challenge);
      form.submit();
      return;
    }
    form=$('pap-login');
    if(!form)return;
    form.elements.namedItem('username').value=voucher;
    form.elements.namedItem('password').value=voucher;
    form.submit();
  }
  var form=$('compat-voucher-form');
  if(form)form.addEventListener('submit',function(event){event.preventDefault();login($('compat-voucher')?$('compat-voucher').value:'');});
  window.PIXIEPOINT_HOTSPOT_LOGIN=login;
}());
