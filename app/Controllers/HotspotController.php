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
        if ($this->method() === 'GET') redirect('/');

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
            $this->messagePage(
                'Invalid hotspot request',
                'Hotspot request rejected',
                $this->errors($result->errors()) . '<p class="muted">Reconnect to the Wi-Fi network and try again.</p>'
            );
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
        $this->portalView('Sign in', 'hotspot/portal', [
            'context' => $context,
            'routerAvailable' => (bool)$router,
            'authenticated' => $this->auth->auth()->check(),
            'csrf' => csrf_token(),
        ]);
    }

    /** Hosted UI for the local MikroTik/legacy JuanFi browser bridge. */
    public function compatibilityPortal(): never
    {
        $this->portalView('Connect to Wi-Fi', 'hotspot/compatibility');
    }

    public function authenticate(): never
    {
        require_csrf();
        $context = $_SESSION['hotspot'] ?? null;
        if (!is_array($context) || empty($context['login_url'])) {
            $this->messagePage(
                'Session expired',
                'Start again',
                '<div class="alert">The hotspot context has expired. Reconnect to the Wi-Fi network.</div>'
            );
        }

        $result = Input::fromRequest()->process([
            'voucher' => 'trim|uppercase|required|string|max:128',
        ]);
        if ($result->fails()) {
            $this->messagePage(
                'Invalid code',
                'Code not accepted',
                $this->errors($result->errors()),
                'javascript:history.back()',
                'Try another code'
            );
        }

        $router = $this->routers->enabledByIdentity((string)$context['router_identity']);
        if (!$router) {
            http_response_code(403);
            $this->messagePage(
                'Router unavailable',
                'Router unavailable',
                '<div class="alert">This hotspot is not registered or has been disabled.</div>'
            );
        }

        if (!$this->safeLoginUrl($context, $router)) {
            http_response_code(403);
            $this->messagePage(
                'Router address mismatch',
                'Router address mismatch',
                '<div class="alert">The router login address does not match the hostname registered by the operator. No credential was sent.</div>'
            );
        }

        $code = (string)$result->validated()['voucher'];
        $stmt = $this->db->prepare("SELECT * FROM vouchers WHERE code=? AND enabled=1 AND uses<max_uses AND (expires_at IS NULL OR expires_at='' OR expires_at>?)");
        $stmt->execute([$code, now()]);
        $voucher = $stmt->fetch();
        if (!$voucher) {
            $this->messagePage(
                'Invalid code',
                'Code not accepted',
                '<div class="alert">That access code is invalid, expired, or has already been used.</div>',
                'javascript:history.back()',
                'Try another code'
            );
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

        $this->portalView('Authorizing', 'hotspot/authorizing', [
            'action' => (string)$context['login_url'],
            'destination' => (string)($context['original_url'] ?: '/hotspot/session'),
            'username' => (string)$voucher['code'],
            'password' => (string)$voucher['password'],
        ]);
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

        $this->portalView('Session', 'hotspot/session', [
            'mac' => client_mac((string)($data['mac'] ?? '')),
            'ip' => (string)($data['ip'] ?? ''),
            'bytesIn' => max(0, (int)($data['bytes_in'] ?? 0)),
            'bytesOut' => max(0, (int)($data['bytes_out'] ?? 0)),
            'uptime' => max(0, (int)($data['uptime'] ?? 0)),
            'logoutUrl' => (string)($data['logout_url'] ?? '#'),
            'authenticated' => $this->auth->auth()->check(),
        ]);
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

        $this->portalView('Disconnected', 'hotspot/disconnected', [
            'uptime' => max(0, (int)($data['uptime'] ?? 0)),
            'bytesIn' => max(0, (int)($data['bytes_in'] ?? 0)),
            'bytesOut' => max(0, (int)($data['bytes_out'] ?? 0)),
            'loginUrl' => (string)($data['login_url'] ?? '#'),
        ]);
    }

    private function portalView(string $title, string $view, array $data = []): never
    {
        $this->view->page($title, $this->view->portalCard($this->view->render($view, $data)));
    }

    private function messagePage(
        string $title,
        string $heading,
        string $message,
        ?string $actionUrl = null,
        ?string $actionLabel = null,
    ): never {
        $this->portalView($title, 'hotspot/message', [
            'heading' => $heading,
            'message' => $message,
            'actionUrl' => $actionUrl,
            'actionLabel' => $actionLabel,
        ]);
    }

    private function method(): string
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    }

    private function errors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $fieldErrors) {
            foreach ((array)$fieldErrors as $message) $messages[] = e($message);
        }
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

        return $actualHost !== ''
            && $expectedHost !== ''
            && hash_equals($expectedHost, $actualHost)
            && $actualPort === $expectedPort;
    }
}
