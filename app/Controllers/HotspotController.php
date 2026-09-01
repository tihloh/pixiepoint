<?php

declare(strict_types=1);

namespace PixiePoint\App\Controllers;

use PDO;
use PixiePoint\App\Models\Router;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\DeviceIdentity;
use PixiePoint\App\Services\View;
use Tihloh\Prefab\Input\Input;

final class HotspotController
{
    public function __construct(
        private PDO $db,
        private Router $routers,
        private AuthContext $auth,
        private View $view,
        private DeviceIdentity $devices,
    ) {}

    public function portal(): never
    {
        if ($this->method() === 'GET') {
            redirect('/');
        }

        $result = Input::fromRequest()->process([
            'mac' => 'trim|string|max:64',
            'ip' => 'trim|string|max:45',
            'username' => 'trim|null_if_empty|nullable|string|max:128',
            'router_identity' => 'trim|required|string|max:160',
            'interface' => 'trim|null_if_empty|nullable|string|max:128',
            'ssid' => 'trim|null_if_empty|nullable|string|max:160',
            'server_address' => 'trim|null_if_empty|nullable|string|max:255',
            'login_url' => 'trim|required|string|max:1000',
            'original_url' => 'trim|null_if_empty|nullable|string|max:1000',
            'chap_id' => 'string|max:255',
            'chap_challenge' => 'string|max:255',
        ]);

        if ($result->fails()) {
            $this->view->page('Invalid hotspot request', $this->view->portalCard('<h1>Hotspot request rejected</h1>' . $this->errors($result->errors()) . '<p class="muted">Reconnect to the Wi-Fi network and try again.</p>'));
        }

        $data = $result->validated();
        $context = [
            'mac' => client_mac((string)($data['mac'] ?? '')),
            'ip' => substr((string)($data['ip'] ?? ''), 0, 45),
            'username' => (string)($data['username'] ?? ''),
            'router_identity' => (string)$data['router_identity'],
            'interface' => (string)($data['interface'] ?? ''),
            'ssid' => (string)($data['ssid'] ?? ''),
            'server_address' => (string)($data['server_address'] ?? ''),
            'login_url' => (string)$data['login_url'],
            'original_url' => (string)($data['original_url'] ?? ''),
            'chap_id' => (string)($data['chap_id'] ?? ''),
            'chap_challenge' => (string)($data['chap_challenge'] ?? ''),
        ];

        $scope = implode('|', array_filter([
            $context['router_identity'],
            $context['ssid'],
            $context['interface'],
        ], static fn ($value) => $value !== '')) ?: 'global';
        $device = $this->devices->observe(
            $context['mac'],
            $scope,
            $this->auth->auth()->id(),
            $context['ip'],
            (string)($_SERVER['HTTP_USER_AGENT'] ?? ''),
        );
        $context['device_id'] = (int)$device['id'];
        $context['device_uuid'] = (string)($device['uuid'] ?? '');
        $_SESSION['hotspot'] = $context;

        $router = $this->routers->enabledByIdentity($context['router_identity']);
        $notice = $router ? '' : '<div class="alert">This hotspot router is not registered or is disabled. Ask the operator to add identity <span class="code">' . e($context['router_identity']) . '</span>.</div>';
        $form = $router ? '<form method="post" action="/hotspot/authenticate"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="field"><label for="voucher">Access code</label><input id="voucher" name="voucher" autocomplete="one-time-code" autocapitalize="characters" required autofocus></div><button class="button full" type="submit">Connect this device</button></form>' : '';
        $account = $this->auth->auth()->check()
            ? '<p class="muted">This session can be linked to your PixiePoint account for history, points and support.</p>'
            : '<p class="muted">Guest access works normally. PixiePoint remembers this device using both its browser and observed network identities. <a href="/">Log in</a> or <a href="/register">register</a> to protect points and remaining time if those identities change.</p>';
        $this->view->page('Sign in', $this->view->portalCard('<h1>Connect to Wi-Fi</h1><p class="muted">Enter your access code to start this device’s MikroTik Hotspot session.</p>' . $notice . '<div class="context"><div><small>Device</small>' . e($context['mac'] ?: 'Private/randomized identity') . '</div><div><small>Router</small>' . e($context['router_identity'] ?: 'Unknown') . '</div></div>' . $form . $account));
    }

    /** Hosted UI for the local MikroTik/legacy JuanFi browser bridge. */
    public function compatibilityPortal(): never
    {
        $body = '<div class="compat" id="compat-app">'
            . '<div class="brand"><span class="brandmark">P</span><span>PixiePoint</span></div>'
            . '<h1>Connect to Wi-Fi</h1><p class="muted">Insert coins or enter an existing voucher.</p>'
            . '<div id="compat-alert" class="alert" hidden></div>'
            . '<div class="field"><label for="compat-vendo">Coin slot</label><select id="compat-vendo"></select><small id="compat-health" class="compat-status">Connecting to the local vendo…</small></div>'
            . '<button class="button full" id="compat-topup" type="button" disabled>Insert coin</button>'
            . '<button class="button secondary full" id="compat-rates" type="button" disabled>View rates</button>'
            . '<div class="compat-tools"><button class="button secondary" id="compat-extend-toggle" type="button" disabled>Extend voucher</button><button class="button secondary" id="compat-charging" type="button" hidden>Phone charging</button><button class="button secondary" id="compat-eload" type="button" hidden>Buy e-load</button></div>'
            . '<form id="compat-extend-form" class="compat-inline" hidden><div class="field"><label for="compat-extend-code">Voucher to extend</label><input id="compat-extend-code" autocomplete="one-time-code" autocapitalize="characters" required></div><button class="button full" type="submit">Insert coins to extend</button></form>'
            . '<div id="compat-transaction" class="compat-transaction" hidden><small>Your voucher</small><strong id="compat-code">—</strong><div class="context"><div><small>Coin total</small><span id="compat-amount">₱0</span></div><div><small>Time</small><span id="compat-time">—</span></div></div><p id="compat-progress" class="muted">Waiting for coins…</p><div class="actions"><button class="button" id="compat-finish" type="button">Done &amp; connect</button><button class="button secondary" id="compat-cancel" type="button">Cancel</button></div></div>'
            . '<form id="compat-convert-form" class="compat-inline" hidden><div class="field"><label for="compat-convert-code">Convert time into another voucher</label><input id="compat-convert-code" autocomplete="one-time-code" autocapitalize="characters" required></div><button class="button full" type="submit">Convert voucher</button></form>'
            . '<form id="compat-voucher-form" class="compat-voucher"><div class="field"><label for="compat-voucher">Have a voucher?</label><input id="compat-voucher" autocomplete="one-time-code" autocapitalize="characters" required></div><button class="button full" type="submit">Connect</button></form>'
            . '<div id="compat-rate-list" class="compat-rate-list" hidden></div>'
            . '<div id="compat-charger-list" class="compat-rate-list" hidden></div>'
            . '<div id="compat-eload-panel" class="compat-rate-list" hidden><p class="muted">E-load compatibility is enabled for this vendo. Product retrieval and payment stay in the hosted portal.</p><div id="compat-eload-products">Loading products…</div></div>'
            . '<noscript><div class="alert">JavaScript is required to communicate with the local coin slot.</div></noscript>'
            . '</div><script src="/assets/juanfi-compat.js" defer></script>';
        $this->view->page('Connect to Wi-Fi', $this->view->portalCard($body));
    }

    public function authenticate(): never
    {
        require_csrf();
        $context = $_SESSION['hotspot'] ?? null;
        if (!is_array($context) || empty($context['login_url'])) {
            $this->view->page('Session expired', $this->view->portalCard('<h1>Start again</h1><div class="alert">The hotspot context has expired. Reconnect to the Wi-Fi network.</div>'));
        }

        $result = Input::fromRequest()->process([
            'voucher' => 'trim|uppercase|required|string|max:128',
        ]);
        if ($result->fails()) {
            $this->view->page('Invalid code', $this->view->portalCard('<h1>Code not accepted</h1>' . $this->errors($result->errors()) . '<a class="button full" href="javascript:history.back()">Try another code</a>'));
        }

        $router = $this->routers->enabledByIdentity((string)$context['router_identity']);
        if (!$router) {
            http_response_code(403);
            $this->view->page('Router unavailable', $this->view->portalCard('<h1>Router unavailable</h1><div class="alert">This hotspot is not registered or has been disabled.</div>'));
        }
        if (!$this->safeLoginUrl($context, $router)) {
            http_response_code(403);
            $this->view->page('Router address mismatch', $this->view->portalCard('<h1>Router address mismatch</h1><div class="alert">The router login address does not match the hostname registered by the operator. No credential was sent.</div>'));
        }

        $code = (string)$result->validated()['voucher'];
        $stmt = $this->db->prepare("SELECT * FROM vouchers WHERE code=? AND enabled=1 AND uses<max_uses AND (expires_at IS NULL OR expires_at='' OR expires_at>?)");
        $stmt->execute([$code, now()]);
        $voucher = $stmt->fetch();
        if (!$voucher) {
            $this->view->page('Invalid code', $this->view->portalCard('<h1>Code not accepted</h1><div class="alert">That access code is invalid, expired, or has already been used.</div><a class="button full" href="javascript:history.back()">Try another code</a>'));
        }

        $deviceId = (int)($context['device_id'] ?? 0) ?: null;
        $device = $deviceId ? $this->devices->findDevice($deviceId) : null;
        $deviceId = $device ? (int)$device['id'] : null;
        $userId = $this->auth->auth()->id() ?? ($device['user_id'] ?? null);

        $this->db->beginTransaction();
        $this->db->prepare('UPDATE vouchers SET uses=uses+1 WHERE id=?')->execute([$voucher['id']]);
        $this->db->prepare("INSERT INTO sessions(user_id,voucher_id,router_id,device_id,username,client_ip,status,started_at,updated_at) VALUES(?,?,?,?,?,?,'authorizing',?,?)")
            ->execute([$userId, $voucher['id'], $router['id'], $deviceId, $voucher['code'], $context['ip'], now(), now()]);
        $this->db->prepare('UPDATE routers SET last_seen_at=? WHERE id=?')->execute([now(), $router['id']]);
        $this->db->commit();

        $action = e((string)$context['login_url']);
        $destination = e((string)($context['original_url'] ?: '/hotspot/session'));
        $body = '<h1>Authorizing…</h1><p class="muted">Your access code was accepted. Connecting this device now.</p><form id="router-login" action="' . $action . '" method="post"><input type="hidden" name="username" value="' . e($voucher['code']) . '"><input type="hidden" name="password" value="' . e($voucher['password']) . '"><input type="hidden" name="dst" value="' . $destination . '"><input type="hidden" name="popup" value="true"></form><script>document.getElementById("router-login").submit()</script><noscript><button class="button full" type="submit" form="router-login">Continue</button></noscript>';
        $this->view->page('Authorizing', $this->view->portalCard($body));
    }

    public function session(): never
    {
        $result = Input::fromRequest()->process([
            'mac' => 'trim|string|max:64',
            'ip' => 'trim|string|max:45',
            'bytes_in' => 'default:0|integer|min:0',
            'bytes_out' => 'default:0|integer|min:0',
            'uptime' => 'default:0|integer|min:0',
            'logout_url' => 'trim|required|string|max:1000',
        ]);
        $data = $result->validated();
        $mac = client_mac((string)($data['mac'] ?? ''));
        $in = max(0, (int)($data['bytes_in'] ?? 0));
        $out = max(0, (int)($data['bytes_out'] ?? 0));
        $uptime = max(0, (int)($data['uptime'] ?? 0));
        $account = $this->auth->auth()->check()
            ? '<a class="button full" href="/dashboard">My PixiePoint account</a>'
            : '<p class="muted">Create a PixiePoint account later to protect points and remaining time even if your private MAC or browser changes.</p>';
        $body = '<h1>You’re connected</h1><p class="muted">Live Wi-Fi session for ' . e($mac ?: 'this device') . '.</p><div class="context"><div><small>Connected</small>' . e(duration_nice($uptime)) . '</div><div><small>IP address</small>' . e($data['ip'] ?? '') . '</div><div><small>Downloaded</small>' . e(bytes_nice($out)) . '</div><div><small>Uploaded</small>' . e(bytes_nice($in)) . '</div></div><form method="post" action="' . e($data['logout_url'] ?? '#') . '"><button class="button full" type="submit">Disconnect</button></form>' . $account;
        $this->view->page('Session', $this->view->portalCard($body));
    }

    public function disconnected(): never
    {
        $result = Input::fromRequest()->process([
            'uptime' => 'default:0|integer|min:0',
            'bytes_in' => 'default:0|integer|min:0',
            'bytes_out' => 'default:0|integer|min:0',
            'login_url' => 'trim|required|string|max:1000',
        ]);
        $data = $result->validated();
        $body = '<h1>You’re offline</h1><p class="muted">The Wi-Fi session has ended.</p><div class="context"><div><small>Session time</small>' . e(duration_nice((int)($data['uptime'] ?? 0))) . '</div><div><small>Total transfer</small>' . e(bytes_nice((int)($data['bytes_in'] ?? 0) + (int)($data['bytes_out'] ?? 0))) . '</div></div><a class="button full" href="' . e($data['login_url'] ?? '#') . '">Connect again</a>';
        $this->view->page('Disconnected', $this->view->portalCard($body));
    }

    private function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    private function errors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $fieldErrors) foreach ((array)$fieldErrors as $message) $messages[] = e($message);
        return '<div class="alert">' . implode('<br>', $messages ?: ['Invalid request.']) . '</div>';
    }

    private function safeLoginUrl(array $context, array $router): bool
    {
        $url = parse_url((string)($context['login_url'] ?? ''));
        if (!$url || !in_array(strtolower((string)($url['scheme'] ?? '')), ['http', 'https'], true)) return false;
        $actualHost = strtolower(rtrim((string)($url['host'] ?? ''), '.'));
        $configured = trim((string)($router['public_host'] ?? ''));
        if ($configured === '') return false;
        $configuredUrl = str_contains($configured, '://') ? $configured : 'https://' . $configured;
        $expectedHost = strtolower(rtrim((string)(parse_url($configuredUrl, PHP_URL_HOST) ?: ''), '.'));
        $actualScheme = strtolower((string)$url['scheme']);
        $expectedScheme = strtolower((string)(parse_url($configuredUrl, PHP_URL_SCHEME) ?: 'https'));
        $actualPort = (int)($url['port'] ?? ($actualScheme === 'https' ? 443 : 80));
        $expectedPort = (int)(parse_url($configuredUrl, PHP_URL_PORT) ?: ($expectedScheme === 'https' ? 443 : 80));
        return $actualHost !== '' && $expectedHost !== '' && hash_equals($expectedHost, $actualHost) && $actualPort === $expectedPort;
    }
}
