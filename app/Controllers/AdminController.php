<?php

declare(strict_types=1);

namespace PixiePoint\App\Controllers;

use PDO;
use Throwable;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\View;
use Tihloh\Prefab\Input\Input;
use Tihloh\Prefab\Logs\Services\LogManager;

final class AdminController
{
    public function __construct(
        private PDO $db,
        private AuthContext $auth,
        private View $view,
        private LogManager $logs,
    ) {}

    public function routers(): never
    {
        $message = '';
        if ($this->isPost()) {
            require_csrf();
            $result = Input::fromRequest()->process([
                'name' => 'trim|required|string|max:160',
                'identity' => 'trim|required|string|max:160',
                'public_host' => 'trim|null_if_empty|nullable|string|max:255',
                'location' => 'trim|null_if_empty|nullable|string|max:255',
            ]);

            if ($result->fails()) {
                $message = $this->errors($result->errors());
            } else {
                $data = $result->validated();
                try {
                    $stmt = $this->db->prepare('INSERT INTO routers(name,identity,public_host,location,api_key) VALUES(?,?,?,?,?)');
                    $stmt->execute([$data['name'], $data['identity'], $data['public_host'] ?? null, $data['location'] ?? null, bin2hex(random_bytes(24))]);
                    $id = (int)$this->db->lastInsertId();
                    $this->audit('router.created', 'router', $id, 'MikroTik router was registered.', ['identity' => $data['identity']]);
                    $message = '<div class="alert ok">Router registered.</div>';
                } catch (Throwable) {
                    $message = '<div class="alert">That RouterOS identity is already registered or the router could not be saved.</div>';
                }
            }
        }

        $canManageRouters = $this->auth->can('routers.manage');
        $rows = '';
        foreach ($this->db->query('SELECT * FROM routers ORDER BY created_at DESC') as $router) {
            $keyCell = $canManageRouters ? '<td><code class="code">' . e($router['api_key']) . '</code></td>' : '';
            $rows .= '<tr><td>' . e($router['name']) . '<div class="muted">' . e($router['location']) . '</div></td><td class="code">' . e($router['identity']) . '</td><td>' . e($router['public_host'] ?: '—') . '</td>' . $keyCell . '<td><span class="badge ' . ($router['enabled'] ? '' : 'off') . '">' . ($router['enabled'] ? 'Enabled' : 'Disabled') . '</span></td><td>' . e($router['last_seen_at'] ?: 'Never') . '</td></tr>';
        }

        $keyHead = $canManageRouters ? '<th>Login script API key</th>' : '';
        $columns = $canManageRouters ? 6 : 5;
        $content = '<div class="heading"><div><h1>Routers</h1><p class="muted">Register each MikroTik using its exact RouterOS identity.</p></div></div>' . $message . '<section class="panel"><h2>Add router</h2><form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="form-grid"><div class="field"><label>Display name</label><input name="name" required></div><div class="field"><label>RouterOS identity</label><input name="identity" required></div><div class="field"><label>Public hostname / VPN IP</label><input name="public_host"></div><div class="field"><label>Location</label><input name="location"></div></div><button class="button">Register router</button></form></section><section class="panel"><table><thead><tr><th>Router</th><th>Identity</th><th>Address</th>' . $keyHead . '<th>Status</th><th>Last seen</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="' . $columns . '" class="empty">No routers registered.</td></tr>') . '</tbody></table></section>';
        $this->view->page('Routers', $content, true, $this->auth->navigation());
    }

    public function vouchers(): never
    {
        $message = '';
        if ($this->isPost()) {
            require_csrf();
            $result = Input::fromRequest()->process([
                'code' => 'trim|uppercase|null_if_empty|nullable|string|max:128',
                'label' => 'trim|null_if_empty|nullable|string|max:255',
                'duration_minutes' => 'required|integer|min:1|max:525600',
                'data_limit_mb' => 'null_if_empty|nullable|integer|min:1',
                'max_devices' => 'required|integer|min:1|max:1000',
                'max_uses' => 'required|integer|min:1|max:1000000',
                'expires_at' => 'trim|null_if_empty|nullable|string|max:32',
            ]);

            if ($result->fails()) {
                $message = $this->errors($result->errors());
            } else {
                $data = $result->validated();
                $code = $data['code'] ?? substr(strtoupper(bin2hex(random_bytes(5))), 0, 10);
                $password = bin2hex(random_bytes(8));
                try {
                    $stmt = $this->db->prepare('INSERT INTO vouchers(code,password,label,duration_minutes,data_limit_mb,max_devices,max_uses,expires_at) VALUES(?,?,?,?,?,?,?,?)');
                    $stmt->execute([$code, $password, $data['label'] ?? null, $data['duration_minutes'], $data['data_limit_mb'] ?? null, $data['max_devices'], $data['max_uses'], $data['expires_at'] ?? null]);
                    $id = (int)$this->db->lastInsertId();
                    $this->audit('voucher.created', 'voucher', $id, 'PisoWiFi voucher was created.', ['code' => $code]);
                    $message = '<div class="alert ok">Voucher <span class="code">' . e($code) . '</span> created.</div>';
                } catch (Throwable) {
                    $message = '<div class="alert">That voucher code already exists or the voucher could not be saved.</div>';
                }
            }
        }

        $rows = '';
        foreach ($this->db->query('SELECT * FROM vouchers ORDER BY created_at DESC LIMIT 100') as $v) {
            $rows .= '<tr><td class="code">' . e($v['code']) . '</td><td>' . e($v['label'] ?: '—') . '</td><td>' . e($v['duration_minutes']) . ' min</td><td>' . e($v['uses'] . ' / ' . $v['max_uses']) . '</td><td>' . e($v['expires_at'] ?: 'Never') . '</td><td><span class="badge ' . ($v['enabled'] ? '' : 'off') . '">' . ($v['enabled'] ? 'Enabled' : 'Disabled') . '</span></td></tr>';
        }

        $content = '<div class="heading"><div><h1>Access vouchers</h1><p class="muted">Issue time- and usage-limited Wi-Fi credentials.</p></div></div>' . $message . '<section class="panel"><h2>Create voucher</h2><form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="form-grid"><div class="field"><label>Code (blank for automatic)</label><input name="code"></div><div class="field"><label>Label</label><input name="label"></div><div class="field"><label>Duration in minutes</label><input name="duration_minutes" type="number" min="1" value="60"></div><div class="field"><label>Data limit in MB (optional)</label><input name="data_limit_mb" type="number" min="1"></div><div class="field"><label>Maximum devices</label><input name="max_devices" type="number" min="1" value="1"></div><div class="field"><label>Maximum uses</label><input name="max_uses" type="number" min="1" value="1"></div><div class="field"><label>Expires at (optional)</label><input name="expires_at" type="datetime-local"></div></div><button class="button">Create voucher</button></form></section><section class="panel"><table><thead><tr><th>Code</th><th>Label</th><th>Duration</th><th>Uses</th><th>Expires</th><th>Status</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="6" class="empty">No vouchers created.</td></tr>') . '</tbody></table></section>';
        $this->view->page('Vouchers', $content, true, $this->auth->navigation());
    }

    public function devices(): never
    {
        $rows = '';
        foreach ($this->db->query('SELECT d.*,u.email,COUNT(s.id) sessions FROM devices d LEFT JOIN users u ON u.id=d.user_id LEFT JOIN sessions s ON s.device_id=d.id GROUP BY d.id ORDER BY d.last_seen_at DESC') as $d) {
            $rows .= '<tr><td class="code">' . e($d['mac']) . '</td><td>' . e($d['email'] ?: 'Guest') . '</td><td>' . e($d['last_ip'] ?: '—') . '</td><td>' . e($d['sessions']) . '</td><td>' . e($d['last_seen_at']) . '</td></tr>';
        }
        $content = '<div class="heading"><div><h1>Devices</h1><p class="muted">MikroTik hotspot devices. Guest devices remain valid and may later be linked to accounts.</p></div></div><section class="panel"><table><thead><tr><th>MAC address</th><th>Account</th><th>Last IP</th><th>Sessions</th><th>Last seen</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="5" class="empty">No devices observed.</td></tr>') . '</tbody></table></section>';
        $this->view->page('Devices', $content, true, $this->auth->navigation());
    }

    public function sessions(): never
    {
        $rows = '';
        foreach ($this->db->query('SELECT s.*,d.mac,r.name router_name,u.email account_email FROM sessions s LEFT JOIN devices d ON d.id=s.device_id LEFT JOIN routers r ON r.id=s.router_id LEFT JOIN users u ON u.id=s.user_id ORDER BY s.updated_at DESC LIMIT 250') as $s) {
            $rows .= '<tr><td>' . e($s['account_email'] ?: 'Guest') . '</td><td>' . e($s['username'] ?: '—') . '</td><td class="code">' . e($s['mac'] ?: '—') . '</td><td>' . e($s['router_name'] ?: '—') . '</td><td><span class="badge ' . ($s['status'] === 'active' ? '' : 'off') . '">' . e($s['status']) . '</span></td><td>' . e(duration_nice((int)$s['uptime_seconds'])) . '</td><td>' . e(bytes_nice((int)$s['bytes_in'] + (int)$s['bytes_out'])) . '</td></tr>';
        }
        $content = '<div class="heading"><div><h1>Sessions</h1><p class="muted">MikroTik authentication and accounting history, including guests and registered users.</p></div></div><section class="panel"><table><thead><tr><th>Account</th><th>Hotspot user</th><th>Device</th><th>Router</th><th>Status</th><th>Uptime</th><th>Transfer</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="7" class="empty">No sessions recorded.</td></tr>') . '</tbody></table></section>';
        $this->view->page('Sessions', $content, true, $this->auth->navigation());
    }

    public function sales(): never
    {
        $summary = $this->db->query("SELECT COALESCE(SUM(amount_pesos),0) total,COUNT(*) transactions,COALESCE(SUM(points_awarded),0) points FROM router_login_events WHERE created_at >= CURDATE()")->fetch() ?: [];
        $rows = '';
        foreach ($this->db->query('SELECT e.*,r.name router_name,d.mac device_mac FROM router_login_events e JOIN routers r ON r.id=e.router_id LEFT JOIN devices d ON d.id=e.device_id ORDER BY e.created_at DESC LIMIT 250') as $event) {
            $kind = $event['is_extension'] ? 'Extension' : 'New access';
            $rows .= '<tr><td>' . e($event['created_at']) . '</td><td>' . e($event['router_name']) . '</td><td>' . e($event['vendo_name'] ?: '—') . '</td><td class="code">' . e($event['username']) . '</td><td class="code">' . e($event['device_mac'] ?: $event['mac'] ?: '—') . '</td><td>' . e($kind) . '</td><td>₱' . e(number_format((int)$event['amount_pesos'])) . '</td><td>' . e($event['points_awarded']) . '</td></tr>';
        }
        $metrics = '<section class="grid"><div class="metric"><small>Today sales</small><strong>₱' . e(number_format((int)($summary['total'] ?? 0))) . '</strong></div><div class="metric"><small>Transactions</small><strong>' . e($summary['transactions'] ?? 0) . '</strong></div><div class="metric"><small>Points awarded</small><strong>' . e($summary['points'] ?? 0) . '</strong></div></section>';
        $content = '<div class="heading"><div><h1>Vendo sales</h1><p class="muted">Idempotent sales recorded by authenticated RouterOS login events.</p></div></div>' . $metrics . '<section class="panel"><table><thead><tr><th>Time</th><th>Router</th><th>Vendo</th><th>Voucher</th><th>Device</th><th>Type</th><th>Amount</th><th>Points</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="8" class="empty">No login sales recorded.</td></tr>') . '</tbody></table></section>';
        $this->view->page('Sales', $content, true, $this->auth->navigation());
    }

    public function logs(): never
    {
        $rows = '';
        foreach ($this->logs->recent(200) as $log) {
            $rows .= '<tr><td class="code">' . e($log['action'] ?? '') . '</td><td>' . e($log['actor_id'] ?? 'System') . '</td><td>' . e(($log['subject_type'] ?? '—') . (($log['subject_id'] ?? null) !== null ? ' #' . $log['subject_id'] : '')) . '</td><td>' . e($log['message'] ?? '—') . '</td><td>' . e($log['created_at'] ?? $log['occurred_at'] ?? '—') . '</td></tr>';
        }
        $content = '<div class="heading"><div><h1>Activity logs</h1><p class="muted">Structured audit history provided by Prefab Logs.</p></div></div><section class="panel"><table><thead><tr><th>Action</th><th>Actor</th><th>Subject</th><th>Message</th><th>Time</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="5" class="empty">No activity recorded yet.</td></tr>') . '</tbody></table></section>';
        $this->view->page('Logs', $content, true, $this->auth->navigation());
    }

    private function isPost(): bool
    {
        return strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    private function audit(string $action, string $subjectType, int|string|null $subjectId, string $message, array $metadata = []): void
    {
        $this->logs->record([
            'action' => $action,
            'subject_type' => $subjectType,
            'subject_id' => $subjectId,
            'actor_id' => $this->auth->auth()->id(),
            'message' => $message,
            'metadata' => $metadata,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }

    private function errors(array $errors): string
    {
        $messages = [];
        foreach ($errors as $fieldErrors) foreach ((array)$fieldErrors as $message) $messages[] = e($message);
        return '<div class="alert">' . implode('<br>', $messages ?: ['Please check the form.']) . '</div>';
    }
}
