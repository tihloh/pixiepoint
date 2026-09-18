const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const crypto = require('node:crypto');
const {
  execFileSync
} = require('node:child_process');
const path = require('node:path');
const root = path.resolve(__dirname, '..');
const fixtures = JSON.parse(execFileSync('php', [path.join(__dirname, 'mikrotik-fixtures.php')], {
  encoding: 'utf8'
}));
const cases = fixtures.logins;
const md5 = fs.readFileSync(path.join(root, 'public/assets/md5.js'), 'utf8');
for (const test of cases) {
  let submits = 0;
  const fields = {
    username: {},
    password: {}
  };
  const form = {
    elements: {
      namedItem: name => fields[name]
    },
    submit() {
      submits++;
    }
  };
  const inputs = {
    'compat-voucher': {
      value: 'AbC123'
    },
    'pp-mikrotik-username': {
      value: 'member'
    },
    'pp-mikrotik-password': {
      value: 'secret'
    }
  };
  const sandbox = {
    document: {
      forms: {
        namedItem: () => form
      },
      getElementById: id => inputs[id] || null,
      querySelectorAll: () => []
    },
    location: {
      search: ''
    },
    URLSearchParams
  };
  sandbox.window = sandbox;
  vm.createContext(sandbox);
  vm.runInContext(md5, sandbox);
  for (const match of test.html.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)) vm.runInContext(match[1], sandbox);
  const hash = password => crypto.createHash('md5').update(Buffer.concat([Buffer.from([0]), Buffer.from(password), Buffer.from([0, 9, 32, 127, 128, 255, 92, 49])])).digest('hex');
  assert.equal(sandbox.doRouterLogin(), false);
  assert.equal(fields.username.value, 'member');
  assert.equal(fields.password.value, test.chap ? hash('secret') : 'secret');
  sandbox.doLogin();
  const password = test.mode === 'voucher' ? 'AbC123' : '';
  assert.equal(fields.username.value, 'AbC123');
  assert.equal(fields.password.value, test.chap ? hash(password) : password);
  assert.equal(submits, 2);
  assert.equal(sandbox.PIXIEPOINT_CONTEXT.loginUrl, test.context.loginUrl);
  assert.equal(sandbox.PIXIEPOINT_CONTEXT.mac, test.context.mac);
  const trial = new URL(test.html.match(/id="pp-trial-start"[^>]*href="([^"]+)"/)[1].replaceAll('&amp;', '&'));
  assert.equal(trial.searchParams.get('dst'), test.context.originalUrl);
  assert.equal(trial.searchParams.get('username'), 'T-AA:BB:CC:DD:EE:FF');
  assert.ok(test.html.includes('onsubmit="return doLogin()"'));
}
const login = fs.readFileSync(path.join(root, 'mt_hotspot/login.html'), 'utf8');
const encode = login.match(/function chapBytes\(value\)\{[^\n]+/)[0];
const sandbox = {};
vm.createContext(sandbox);
vm.runInContext(encode, sandbox);
const allBytes = String.fromCharCode(...Array.from({
  length: 256
}, (_, i) => i));
const transported = new URLSearchParams(new URLSearchParams({
  chap: sandbox.chapBytes(allBytes)
}).toString()).get('chap');
const decoded = transported.replace(/\\([0-7]{3})/g, (_, n) => String.fromCharCode(parseInt(n, 8)));
assert.equal(decoded, allBytes);
console.log('Passed ' + cases.length + ' themed CHAP/PAP member/voucher/trial cases and all 256 CHAP transport bytes.');

for (const test of fixtures.sessions) {
  const sandbox = {
    document: {
      querySelectorAll: () => [],
      getElementById: () => null
    }
  };
  sandbox.window = sandbox;
  vm.createContext(sandbox);
  for (const match of test.status.matchAll(/<script\b[^>]*>([\s\S]*?)<\/script>/g)) vm.runInContext(match[1], sandbox);
  assert.equal(sandbox.PIXIEPOINT_SESSION.logoutUrl, 'http://10.0.0.1/logout');
  assert.equal(sandbox.PIXIEPOINT_SESSION.sessionTimeLeft, '125');
  assert.ok(test.status.includes('action="http://10.0.0.1/logout"'));
  assert.ok(test.logout.includes('href="http://10.0.0.1/login"'));
  assert.ok(!test.logout.includes('compat-voucher-form'));
}
console.log('Passed fresh-handoff isolation and status/logout checks for all four themes.');