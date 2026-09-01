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

        $cards = '';
        foreach ($metrics as $label => $value) {
            $cards .= '<div class="metric"><small>' . e($label) . '</small><strong>' . e($value) . '</strong></div>';
        }

        $recentStmt = $this->db->prepare('SELECT s.*,d.mac,r.name router_name FROM sessions s LEFT JOIN devices d ON d.id=s.device_id LEFT JOIN routers r ON r.id=s.router_id WHERE s.user_id=? ORDER BY s.updated_at DESC LIMIT 8');
        $recentStmt->execute([$uid]);
        $rows = '';
        foreach ($recentStmt->fetchAll() as $session) {
            $rows .= '<tr><td>' . e($session['username'] ?: '—') . '</td><td class="code">' . e($session['mac'] ?: '—') . '</td><td>' . e($session['router_name'] ?: '—') . '</td><td><span class="badge ' . ($session['status'] === 'active' ? '' : 'off') . '">' . e($session['status']) . '</span></td><td>' . e($session['updated_at']) . '</td></tr>';
        }

        $role = $this->auth->isPlatformOwner() ? 'Platform owner' : 'PixiePoint user';
        $management = array_filter($this->auth->navigation());
        $managementNote = $management
            ? '<section class="panel"><h2>Management access</h2><p class="muted">Additional PixiePoint features are shown according to your Prefab permissions.</p></section>'
            : '';

        $deviceRecovery = $this->deviceRecoveryPanel($uid);
        $content = '<div class="heading"><div><h1>My dashboard</h1><p class="muted">Welcome, ' . e($user['name']) . ' · ' . e($role) . '</p></div></div><section class="grid">' . $cards . '</section>' . $deviceRecovery . $managementNote . '<section class="panel"><h2>My recent Wi-Fi sessions</h2><table><thead><tr><th>Access</th><th>Device</th><th>Router</th><th>Status</th><th>Updated</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="5" class="empty">No account-linked sessions yet. Guest sessions continue to work normally.</td></tr>') . '</tbody></table></section>';
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

    private function deviceRecoveryPanel(int $userId): string
    {
        $message = (string)($_SESSION['device_claim_message'] ?? '');
        unset($_SESSION['device_claim_message']);

        $current = $this->devices->currentDevice();
        if (!$current) return $message;
        if ($current['user_id'] !== null && (int)$current['user_id'] === $userId) {
            return $message;
        }
        if ($current['user_id'] !== null && (int)$current['user_id'] !== $userId) {
            return $message . '<section class="panel"><h2>Device identity conflict</h2><div class="alert">This browser is linked to a device owned by another account. PixiePoint will not merge it automatically.</div></section>';
        }

        $guestPoints = $this->points->balanceForDevice((int)$current['id']);
        $known = $this->devices->userDevices($userId);
        $choices = '';
        foreach ($known as $device) {
            if ((int)$device['id'] === (int)$current['id']) continue;
            $label = $device['mac'] ?: ('Device ' . substr((string)$device['uuid'], 0, 8));
            $choices .= '<form method="post" action="/devices/claim"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><input type="hidden" name="device_id" value="' . e($current['id']) . '"><input type="hidden" name="target_device_id" value="' . e($device['id']) . '"><button class="button secondary full" type="submit">This is ' . e($label) . '</button></form>';
        }

        $newDevice = '<form method="post" action="/devices/claim"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><input type="hidden" name="device_id" value="' . e($current['id']) . '"><input type="hidden" name="target_device_id" value="0"><button class="button full" type="submit">Save as a new device</button></form>';
        $pointsNote = $guestPoints > 0 ? '<div class="notice"><strong>' . e($guestPoints) . ' guest points</strong> are waiting on this device. Confirm it to claim them into your account.</div>' : '';
        $explanation = $known
            ? '<p class="muted">PixiePoint could not confidently match this browser/MAC combination. If this is one of your existing devices, confirm it below to restore that device identity, guest wallet and history together.</p>'
            : '<p class="muted">PixiePoint detected an anonymous device from before you signed in. Save it to your account so its guest wallet and identity become protected by your account.</p>';

        return $message . '<section class="panel"><h2>Confirm this device</h2>' . $pointsNote . $explanation . '<div class="actions">' . $choices . $newDevice . '</div></section>';
    }
}
