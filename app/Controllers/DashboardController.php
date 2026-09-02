<?php

declare(strict_types=1);

namespace PixiePoint\App\Controllers;

use PDO;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\DeviceIdentity;
use PixiePoint\App\Services\PointWallet;
use PixiePoint\App\Services\View;
use Throwable;
use Tihloh\Prefab\Input\Input;

final class DashboardController
{
    public function __construct(
        private PDO $db,
        private AuthContext $auth,
        private View $view,
        private DeviceIdentity $devices,
        private PointWallet $points,
    ) {}

    public function index(): never
    {
        $user = $this->auth->requireAccount();
        $uid = (int)$user['id'];

        $deviceStmt = $this->db->prepare('SELECT COUNT(*) FROM devices WHERE user_id=? AND merged_into_device_id IS NULL');
        $deviceStmt->execute([$uid]);
        $sessionStmt = $this->db->prepare('SELECT COUNT(*) FROM sessions WHERE user_id=?');
        $sessionStmt->execute([$uid]);

        $metrics = [
            'Points' => $this->points->balanceForDevice(0, $uid),
            'My devices' => (int)$deviceStmt->fetchColumn(),
            'My sessions' => (int)$sessionStmt->fetchColumn(),
        ];

        if ($this->auth->can('routers.view')) {
            $metrics['Routers'] = (int)$this->db->query('SELECT COUNT(*) FROM routers WHERE enabled=1')->fetchColumn();
        }
        if ($this->auth->can('sessions.view')) {
            $metrics['Active sessions'] = (int)$this->db->query("SELECT COUNT(*) FROM sessions WHERE status='active'")->fetchColumn();
        }
        if ($this->auth->can('users.view')) {
            $metrics['Users'] = (int)$this->db->query('SELECT COUNT(*) FROM users')->fetchColumn();
        }

        $recentStmt = $this->db->prepare('SELECT s.*,d.mac,r.name router_name FROM sessions s LEFT JOIN devices d ON d.id=s.device_id LEFT JOIN routers r ON r.id=s.router_id WHERE s.user_id=? ORDER BY s.updated_at DESC LIMIT 8');
        $recentStmt->execute([$uid]);

        $content = $this->view->render('dashboard/index', [
            'user' => $user,
            'role' => $this->auth->isPlatformOwner() ? 'Platform owner' : 'PixiePoint user',
            'metrics' => $metrics,
            'sessions' => $recentStmt->fetchAll(),
            'deviceRecovery' => $this->deviceRecoveryView($uid),
            'hasManagement' => array_filter($this->auth->navigation()) !== [],
        ]);

        $this->view->page('Dashboard', $content, true, $this->auth->navigation());
    }

    public function claimDevice(): never
    {
        $user = $this->auth->requireAccount();
        require_csrf();

        $result = Input::fromRequest()->process([
            'device_id' => 'required|integer|min:1',
            'target_device_id' => 'default:0|integer|min:0',
        ]);
        if ($result->fails()) {
            $_SESSION['device_claim_message'] = '<div class="alert">The device confirmation was invalid. Please try again.</div>';
            redirect('/dashboard');
        }

        $data = $result->validated();
        $current = $this->devices->currentDevice();
        $deviceId = (int)$data['device_id'];
        if (!$current || (int)$current['id'] !== $deviceId) {
            $_SESSION['device_claim_message'] = '<div class="alert">This browser is no longer presenting the same device identity. Reconnect and try again.</div>';
            redirect('/dashboard');
        }

        try {
            $uid = (int)$user['id'];
            $claimedPoints = $this->points->claimDeviceWallet($deviceId, $uid);
            $targetId = (int)($data['target_device_id'] ?? 0);
            if ($targetId > 0) {
                $this->devices->mergeInto($deviceId, $targetId, $uid);
                $_SESSION['device_claim_message'] = '<div class="notice">Device restored. This browser and its current MAC are now linked to your existing device record.' . ($claimedPoints > 0 ? ' ' . e($claimedPoints) . ' guest points were claimed.' : '') . '</div>';
            } else {
                $this->devices->claimAsNew($deviceId, $uid);
                $_SESSION['device_claim_message'] = '<div class="notice">Device saved to your PixiePoint account.' . ($claimedPoints > 0 ? ' ' . e($claimedPoints) . ' guest points were claimed.' : '') . '</div>';
            }
        } catch (Throwable $e) {
            $_SESSION['device_claim_message'] = '<div class="alert">' . e($e->getMessage()) . '</div>';
        }

        redirect('/dashboard');
    }

    private function deviceRecoveryView(int $userId): string
    {
        $message = (string)($_SESSION['device_claim_message'] ?? '');
        unset($_SESSION['device_claim_message']);

        $current = $this->devices->currentDevice();
        $known = [];
        $guestPoints = 0;
        if ($current && $current['user_id'] === null) {
            $guestPoints = $this->points->balanceForDevice((int)$current['id']);
            $known = $this->devices->userDevices($userId);
        }

        return $this->view->render('dashboard/device-recovery', [
            'message' => $message,
            'current' => $current,
            'userId' => $userId,
            'guestPoints' => $guestPoints,
            'known' => $known,
            'csrf' => csrf_token(),
        ]);
    }
}
