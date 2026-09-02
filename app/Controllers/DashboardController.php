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

/**
 * Handles the signed-in user dashboard and device claim/recovery actions.
 */
final class DashboardController
{
    public function __construct(
        private PDO $db,
        private AuthContext $auth,
        private View $view,
        private DeviceIdentity $devices,
        private PointWallet $points,
    ) {
    }

    /**
     * Builds the dashboard using only metrics the current account may see.
     */
    public function index(): never
    {
        $user = $this->auth->requireAccount();
        $userId = (int) $user['id'];

        $deviceStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM devices '
            . 'WHERE user_id = ? AND merged_into_device_id IS NULL',
        );
        $deviceStmt->execute([$userId]);

        $sessionStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM sessions WHERE user_id = ?',
        );
        $sessionStmt->execute([$userId]);

        $metrics = [
            'Points' => $this->points->balanceForDevice(0, $userId),
            'My devices' => (int) $deviceStmt->fetchColumn(),
            'My sessions' => (int) $sessionStmt->fetchColumn(),
        ];

        // Router metrics are scoped to the account's actual router memberships.
        // Platform owners retain the system-wide view.
        if ($this->auth->isPlatformOwner()) {
            $metrics['Routers'] = (int) $this->db
                ->query('SELECT COUNT(*) FROM routers WHERE enabled = 1')
                ->fetchColumn();
        } else {
            $routerCountStmt = $this->db->prepare(
                'SELECT COUNT(*) '
                . 'FROM routers r '
                . 'JOIN router_members rm ON rm.router_id=r.id '
                . 'WHERE rm.user_id=? AND r.enabled=1',
            );
            $routerCountStmt->execute([$userId]);
            $routerCount = (int) $routerCountStmt->fetchColumn();

            if ($routerCount > 0) {
                $metrics['Routers'] = $routerCount;
            }
        }

        if ($this->auth->can('sessions.view')) {
            if ($this->auth->isPlatformOwner()) {
                $metrics['Active sessions'] = (int) $this->db
                    ->query("SELECT COUNT(*) FROM sessions WHERE status = 'active'")
                    ->fetchColumn();
            } else {
                $activeStmt = $this->db->prepare(
                    "SELECT COUNT(DISTINCT s.id) FROM sessions s "
                    . 'JOIN router_members rm ON rm.router_id=s.router_id '
                    . "WHERE rm.user_id=? AND s.status='active'",
                );
                $activeStmt->execute([$userId]);
                $metrics['Active sessions'] = (int) $activeStmt->fetchColumn();
            }
        }

        if ($this->auth->can('users.view')) {
            $metrics['Users'] = (int) $this->db
                ->query('SELECT COUNT(*) FROM users')
                ->fetchColumn();
        }

        $recentStmt = $this->db->prepare(
            'SELECT s.*, d.mac, r.name AS router_name '
            . 'FROM sessions s '
            . 'LEFT JOIN devices d ON d.id = s.device_id '
            . 'LEFT JOIN routers r ON r.id = s.router_id '
            . 'WHERE s.user_id = ? '
            . 'ORDER BY s.updated_at DESC '
            . 'LIMIT 8',
        );
        $recentStmt->execute([$userId]);

        $content = $this->view->render('dashboard/index', [
            'user' => $user,
            'role' => $this->accountRole($user),
            'metrics' => $metrics,
            'sessions' => $recentStmt->fetchAll(),
            'deviceRecovery' => $this->deviceRecoveryView($userId),
            'hasManagement' => array_filter($this->auth->navigation()) !== [],
            'routerRegistrationCommand' => $this->routerRegistrationCommand($userId),
        ]);

        $this->view->page(
            'Dashboard',
            $content,
            true,
            $this->auth->navigation(),
        );
    }

    /**
     * Claims the current guest device as a new device or merges it into an
     * existing saved device owned by the account.
     */
    public function claimDevice(): never
    {
        $user = $this->auth->requireAccount();
        require_csrf();

        $result = Input::fromRequest()->process([
            'device_id' => 'required|integer|min:1',
            'target_device_id' => 'default:0|integer|min:0',
        ]);

        if ($result->fails()) {
            $_SESSION['device_claim_message'] =
                '<div class="alert">The device confirmation was invalid. Please try again.</div>';
            redirect('/dashboard');
        }

        $data = $result->validated();
        $current = $this->devices->currentDevice();
        $deviceId = (int) $data['device_id'];

        // Prevent a stale page from claiming a different device identity.
        if (!$current || (int) $current['id'] !== $deviceId) {
            $_SESSION['device_claim_message'] =
                '<div class="alert">This browser is no longer presenting the same device identity. Reconnect and try again.</div>';
            redirect('/dashboard');
        }

        try {
            $userId = (int) $user['id'];
            $claimedPoints = $this->points->claimDeviceWallet($deviceId, $userId);
            $targetId = (int) ($data['target_device_id'] ?? 0);

            if ($targetId > 0) {
                $this->devices->mergeInto($deviceId, $targetId, $userId);
                $_SESSION['device_claim_message'] =
                    '<div class="notice">Device restored.'
                    . ($claimedPoints > 0
                        ? ' ' . e($claimedPoints) . ' guest points were claimed.'
                        : '')
                    . '</div>';
            } else {
                $this->devices->claimAsNew($deviceId, $userId);
                $_SESSION['device_claim_message'] =
                    '<div class="notice">Device saved to your PixiePoint account.'
                    . ($claimedPoints > 0
                        ? ' ' . e($claimedPoints) . ' guest points were claimed.'
                        : '')
                    . '</div>';
            }
        } catch (Throwable $e) {
            $_SESSION['device_claim_message'] =
                '<div class="alert">' . e($e->getMessage()) . '</div>';
        }

        redirect('/dashboard');
    }

    /**
     * Returns the one-line RouterOS command that proves control of a MikroTik
     * and claims it for this account.
     */
    private function routerRegistrationCommand(int $userId): string
    {
        $stmt = $this->db->prepare(
            'SELECT account_api_key FROM users WHERE id=? LIMIT 1',
        );
        $stmt->execute([$userId]);
        $key = strtolower(trim((string) $stmt->fetchColumn()));

        if (!preg_match('/^[a-f0-9]{48}$/', $key)) {
            $key = bin2hex(random_bytes(24));
            $stmt = $this->db->prepare(
                'UPDATE users SET account_api_key=? WHERE id=?',
            );
            $stmt->execute([$key, $userId]);
        }

        // Keep RouterOS URLs path-only. This avoids query strings entirely.
        $url = 'https://hs.portalx.win/api/router/register/' . $key;

        return ':local identity [/system identity get name]; '
            . ':local serial [/system routerboard get serial-number]; '
            . ':local result [/tool fetch url="' . $url . '" mode=https '
            . 'http-header-field=("X-PixiePoint-Identity: " . $identity . ",X-PixiePoint-Serial: " . $serial) '
            . 'output=user as-value]; '
            . ':put ($result->"data")';
    }

    private function accountRole(array $user): string
    {
        return match ((string) ($user['platform_role'] ?? 'member')) {
            'platform_owner' => 'Platform owner',
            'pisowifi_owner' => 'PisoWiFi owner',
            default => 'Member',
        };
    }

    /**
     * Renders the device-recovery prompt only when the current browser still
     * represents an unclaimed guest device.
     */
    private function deviceRecoveryView(int $userId): string
    {
        $message = (string) ($_SESSION['device_claim_message'] ?? '');
        unset($_SESSION['device_claim_message']);

        $current = $this->devices->currentDevice();
        $known = [];
        $guestPoints = 0;

        if ($current && $current['user_id'] === null) {
            $guestPoints = $this->points->balanceForDevice((int) $current['id']);
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
