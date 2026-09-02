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

        $this->adminPage('Routers', 'admin/routers', [
            'message' => $message,
            'routers' => $this->db->query('SELECT * FROM routers ORDER BY created_at DESC')->fetchAll(),
            'canManageRouters' => $this->auth->can('routers.manage'),
            'csrf' => csrf_token(),
        ]);
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

        $this->adminPage('Vouchers', 'admin/vouchers', [
            'message' => $message,
            'vouchers' => $this->db->query('SELECT * FROM vouchers ORDER BY created_at DESC LIMIT 100')->fetchAll(),
            'csrf' => csrf_token(),
        ]);
    }

    public function devices(): never
    {
        $devices = $this->db->query('SELECT d.*,u.email,COUNT(s.id) sessions FROM devices d LEFT JOIN users u ON u.id=d.user_id LEFT JOIN sessions s ON s.device_id=d.id GROUP BY d.id ORDER BY d.last_seen_at DESC')->fetchAll();
        $this->adminPage('Devices', 'admin/devices', ['devices' => $devices]);
    }

    public function sessions(): never
    {
        $sessions = $this->db->query('SELECT s.*,d.mac,r.name router_name,u.email account_email FROM sessions s LEFT JOIN devices d ON d.id=s.device_id LEFT JOIN routers r ON r.id=s.router_id LEFT JOIN users u ON u.id=s.user_id ORDER BY s.updated_at DESC LIMIT 250')->fetchAll();
        $this->adminPage('Sessions', 'admin/sessions', ['sessions' => $sessions]);
    }

    public function sales(): never
    {
        $summary = $this->db->query("SELECT COALESCE(SUM(amount_pesos),0) total,COUNT(*) transactions,COALESCE(SUM(points_awarded),0) points FROM router_login_events WHERE created_at >= CURDATE()")->fetch() ?: [];
        $events = $this->db->query('SELECT e.*,r.name router_name,d.mac device_mac FROM router_login_events e JOIN routers r ON r.id=e.router_id LEFT JOIN devices d ON d.id=e.device_id ORDER BY e.created_at DESC LIMIT 250')->fetchAll();
        $this->adminPage('Sales', 'admin/sales', ['summary' => $summary, 'events' => $events]);
    }

    public function logs(): never
    {
        $this->adminPage('Logs', 'admin/logs', ['logs' => $this->logs->recent(200)]);
    }

    private function adminPage(string $title, string $view, array $data): never
    {
        $this->view->page($title, $this->view->render($view, $data), true, $this->auth->navigation());
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
        foreach ($errors as $fieldErrors) {
            foreach ((array)$fieldErrors as $message) $messages[] = e($message);
        }
        return '<div class="alert">' . implode('<br>', $messages ?: ['Please check the form.']) . '</div>';
    }
}
