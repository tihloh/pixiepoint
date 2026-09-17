const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const view = fs.readFileSync(path.join(__dirname, '../app/Admin/VendoGateway/views/index.php'), 'utf8');
const controller = fs.readFileSync(path.join(__dirname, '../app/Admin/VendoGateway/Controller.php'), 'utf8');
assert.match(view, /Hardware configuration is unavailable until this device reports/);
assert.match(controller, /Device firmware target is unknown/);
const script = [...view.matchAll(/<script>([\s\S]*?)<\/script>/g)].at(-1)[1];

function element() {
  return {textContent: '', innerHTML: '', disabled: false, dataset: {}, scrollIntoView() {}};
}
async function check(status) {
  const message = element(), update = element(), latest = element(), state = element(), error = element(), summary = element(), target = element(), checked = element();
  update.disabled = true;
  const controls = {'[data-firmware-update]': update, '[data-firmware-latest]': latest,
    '[data-firmware-state]': state, '[data-firmware-error]': error,
    '[data-firmware-target]': target, '[data-firmware-checked]': checked};
  let onSubmit, requests = 0;
  const form = {
    querySelector(selector) { return selector === '[name=device_id]' ? {value: 'DEV-test'} : null; },
    addEventListener(type, handler) { assert.equal(type, 'submit'); onSubmit = handler; },
  };
  const checkButton = {...element(), name: 'action', value: 'firmware_check', innerHTML: 'Check now'};
  const sandbox = {
    document: {
      getElementById: () => message,
      querySelectorAll: selector => selector === '.js-vendo-form' ? [form] : [],
      querySelector: selector => selector.startsWith('[data-vendo-manage=')
        ? {querySelector: s => controls[s]} : {querySelector: () => summary},
    },
    location: {pathname: '/admin/vendo-gateway', search: '?device=DEV-test'},
    CSS: {escape: value => value},
    FormData: class extends Map {
      constructor() { super([['device_id', 'DEV-test']]); }
      append(key, value) { this.set(key, value); }
    },
    $: () => ({text(value) { this.value = value; return this; }, html() { return this.value; }}),
    fetch: async (url, options) => {
      requests++;
      assert.equal(url, '/admin/vendo-gateway?device=DEV-test');
      assert.equal(options.body.get('action'), 'firmware_check');
      return {json: async () => ({ok: true, action: 'firmware_check', firmware: status, message: 'Checked'})};
    },
  };
  vm.runInNewContext(script, sandbox);
  await onSubmit({preventDefault() {}, submitter: checkButton});
  assert.equal(requests, 1, 'Only the host frontend endpoint is contacted');
  assert.equal(checkButton.disabled, false);
  assert.equal(checkButton.innerHTML, 'Check now');
  assert.equal(update.disabled, status.update_available !== true);
  assert.equal(error.textContent, status.error || '');
  if (status.update_available === true) assert.equal(update.textContent, 'Update to v1.2.2');
  assert.equal(target.textContent, status.target ? 'Target: ' + status.target.toUpperCase() : 'Target unknown');
  assert.equal(checked.textContent, status.checked_at ? ' · Checked ' + status.checked_at : '');
  return {state, summary};
}
(async () => {
  await check({current_version:'1.0.0',latest_version:'1.2.2',update_available:true,target:'esp32',checked_at:'2026-09-17T03:00:00Z'});
  const current = await check({current_version:'1.2.2',latest_version:'1.2.2',update_available:false});
  assert.equal(current.state.textContent, 'Up to date');
  const unknown = await check({current_version:'1.0.0',latest_version:null,update_available:null,error:'Release metadata unavailable'});
  assert.equal(unknown.state.textContent, 'Release status unknown');
  console.log('Passed frontend manual-check cases: available, current, unavailable; update button changes without reload.');
})().catch(error => {console.error(error); process.exitCode = 1;});
