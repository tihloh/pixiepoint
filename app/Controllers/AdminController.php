<?php

declare(strict_types=1);

namespace PixiePoint\App\Controllers;

use PDO;
use PDOException;
use PixiePoint\App\Http\Request;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\View;

final class AdminController
{
    public function __construct(private PDO $db, private AuthContext $auth, private View $view) {}

    public function routers(Request $request): never
    {
        $this->auth->requirePlatformOwner($this->view);
        $message = '';
        if ($request->method === 'POST') {
            require_csrf();
            $name = trim((string)$request->input('name', ''));
            $identity = trim((string)$request->input('identity', ''));
            if ($name !== '' && $identity !== '') {
                try {
                    $stmt = $this->db->prepare('INSERT INTO routers(name,identity,public_host,location,api_key) VALUES(?,?,?,?,?)');
                    $stmt->execute([$name,$identity,trim((string)$request->input('public_host','')),trim((string)$request->input('location','')),bin2hex(random_bytes(24))]);
                    $message = '<div class="alert ok">Router registered.</div>';
                } catch (PDOException) {
                    $message = '<div class="alert">That RouterOS identity is already registered.</div>';
                }
            }
        }
        $rows = '';
        foreach ($this->db->query('SELECT * FROM routers ORDER BY created_at DESC') as $router) {
            $rows .= '<tr><td>' . e($router['name']) . '<div class="muted">' . e($router['location']) . '</div></td><td class="code">' . e($router['identity']) . '</td><td>' . e($router['public_host'] ?: '—') . '</td><td><span class="badge ' . ($router['enabled'] ? '' : 'off') . '">' . ($router['enabled'] ? 'Enabled' : 'Disabled') . '</span></td><td>' . e($router['last_seen_at'] ?: 'Never') . '</td></tr>';
        }
        $content = '<div class="heading"><div><h1>Routers</h1><p class="muted">Register each MikroTik using its exact RouterOS identity.</p></div></div>' . $message . '<section class="panel"><h2>Add router</h2><form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="form-grid"><div class="field"><label>Display name</label><input name="name" required></div><div class="field"><label>RouterOS identity</label><input name="identity" required></div><div class="field"><label>Public hostname / VPN IP</label><input name="public_host"></div><div class="field"><label>Location</label><input name="location"></div></div><button class="button">Register router</button></form></section><section class="panel"><table><thead><tr><th>Router</th><th>Identity</th><th>Address</th><th>Status</th><th>Last seen</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="5" class="empty">No routers registered.</td></tr>') . '</tbody></table></section>';
        $this->view->page('Routers', $content, true, true);
    }

    public function vouchers(Request $request): never
    {
        $this->auth->requirePlatformOwner($this->view);
        $message = '';
        if ($request->method === 'POST') {
            require_csrf();
            $code = strtoupper(trim((string)$request->input('code', '')) ?: substr(strtoupper(bin2hex(random_bytes(5))), 0, 10));
            $password = bin2hex(random_bytes(8));
            try {
                $stmt = $this->db->prepare('INSERT INTO vouchers(code,password,label,duration_minutes,data_limit_mb,max_devices,max_uses,expires_at) VALUES(?,?,?,?,?,?,?,?)');
                $stmt->execute([$code,$password,trim((string)$request->input('label','')),max(1,(int)$request->input('duration_minutes',60)),$request->input('data_limit_mb','')===''?null:max(1,(int)$request->input('data_limit_mb')),max(1,(int)$request->input('max_devices',1)),max(1,(int)$request->input('max_uses',1)),trim((string)$request->input('expires_at',''))?:null]);
                $message = '<div class="alert ok">Voucher <span class="code">' . e($code) . '</span> created.</div>';
            } catch (PDOException) {
                $message = '<div class="alert">That voucher code already exists.</div>';
            }
        }
        $rows = '';
        foreach ($this->db->query('SELECT * FROM vouchers ORDER BY created_at DESC LIMIT 100') as $v) {
            $rows .= '<tr><td class="code">' . e($v['code']) . '</td><td>' . e($v['label'] ?: '—') . '</td><td>' . e($v['duration_minutes']) . ' min</td><td>' . e($v['uses'] . ' / ' . $v['max_uses']) . '</td><td>' . e($v['expires_at'] ?: 'Never') . '</td><td><span class="badge ' . ($v['enabled'] ? '' : 'off') . '">' . ($v['enabled'] ? 'Enabled' : 'Disabled') . '</span></td></tr>';
        }
        $content = '<div class="heading"><div><h1>Access vouchers</h1><p class="muted">Issue time- and usage-limited Wi-Fi credentials.</p></div></div>' . $message . '<section class="panel"><h2>Create voucher</h2><form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="form-grid"><div class="field"><label>Code (blank for automatic)</label><input name="code"></div><div class="field"><label>Label</label><input name="label"></div><div class="field"><label>Duration in minutes</label><input name="duration_minutes" type="number" min="1" value="60"></div><div class="field"><label>Data limit in MB (optional)</label><input name="data_limit_mb" type="number" min="1"></div><div class="field"><label>Maximum devices</label><input name="max_devices" type="number" min="1" value="1"></div><div class="field"><label>Maximum uses</label><input name="max_uses" type="number" min="1" value="1"></div><div class="field"><label>Expires at (optional)</label><input name="expires_at" type="datetime-local"></div></div><button class="button">Create voucher</button></form></section><section class="panel"><table><thead><tr><th>Code</th><th>Label</th><th>Duration</th><th>Uses</th><th>Expires</th><th>Status</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="6" class="empty">No vouchers created.</td></tr>') . '</tbody></table></section>';
        $this->view->page('Vouchers', $content, true, true);
    }

    public function devices(Request $request): never
    {
        $this->auth->requirePlatformOwner($this->view);
        $rows = '';
        foreach ($this->db->query('SELECT d.*,u.email,COUNT(s.id) sessions FROM devices d LEFT JOIN users u ON u.id=d.user_id LEFT JOIN sessions s ON s.device_id=d.id GROUP BY d.id ORDER BY d.last_seen_at DESC') as $d) {
            $rows .= '<tr><td class="code">' . e($d['mac']) . '</td><td>' . e($d['email'] ?: 'Guest') . '</td><td>' . e($d['last_ip'] ?: '—') . '</td><td>' . e($d['sessions']) . '</td><td>' . e($d['last_seen_at']) . '</td></tr>';
        }
        $content = '<div class="heading"><div><h1>Devices</h1><p class="muted">MikroTik hotspot devices. Guest devices remain valid and may later be linked to accounts.</p></div></div><section class="panel"><table><thead><tr><th>MAC address</th><th>Account</th><th>Last IP</th><th>Sessions</th><th>Last seen</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="5" class="empty">No devices observed.</td></tr>') . '</tbody></table></section>';
        $this->view->page('Devices', $content, true, true);
    }

    public function sessions(Request $request): never
    {
        $this->auth->requirePlatformOwner($this->view);
        $rows = '';
        foreach ($this->db->query('SELECT s.*,d.mac,r.name router_name,u.email account_email FROM sessions s LEFT JOIN devices d ON d.id=s.device_id LEFT JOIN routers r ON r.id=s.router_id LEFT JOIN users u ON u.id=s.user_id ORDER BY s.updated_at DESC LIMIT 250') as $s) {
            $rows .= '<tr><td>' . e($s['account_email'] ?: 'Guest') . '</td><td>' . e($s['username'] ?: '—') . '</td><td class="code">' . e($s['mac'] ?: '—') . '</td><td>' . e($s['router_name'] ?: '—') . '</td><td><span class="badge ' . ($s['status'] === 'active' ? '' : 'off') . '">' . e($s['status']) . '</span></td><td>' . e(duration_nice((int)$s['uptime_seconds'])) . '</td><td>' . e(bytes_nice((int)$s['bytes_in'] + (int)$s['bytes_out'])) . '</td></tr>';
        }
        $content = '<div class="heading"><div><h1>Sessions</h1><p class="muted">MikroTik authentication and accounting history, including guests and registered users.</p></div></div><section class="panel"><table><thead><tr><th>Account</th><th>Hotspot user</th><th>Device</th><th>Router</th><th>Status</th><th>Uptime</th><th>Transfer</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="7" class="empty">No sessions recorded.</td></tr>') . '</tbody></table></section>';
        $this->view->page('Sessions', $content, true, true);
    }
}
