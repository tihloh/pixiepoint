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
     * Builds a consolidated dashboard across the account's accessible routers.
     */
    public function index(): never
    {
        $user = $this->auth->requireAccount();

        // Dashboard is the global level. Clear the entire lower selection context.
        unset($_SESSION['pixiepoint_selected_router_id'], $_SESSION['pixiepoint_selected_vendo_id']);

        $userId = (int) $user['id'];
        $platformOwner = $this->auth->isPlatformOwner();
        $routerIds = $this->accessibleRouterIds($userId, $platformOwner);

        $metrics = [
            'Points' => $this->points->balanceForDevice(0, $userId),
        ];

        if ($this->auth->can('routers.view')) {
            $metrics['Routers'] = count($routerIds);
        }

        if ($this->auth->can('vendos.view')) {
            $metrics['Vendos'] = $this->countForRouters('vendos', $routerIds);
        }

        if ($this->auth->can('vouchers.view')) {
            $metrics['Vouchers'] = $this->countForRouters('vouchers', $routerIds);
        }

        if ($this->auth->can('devices.view')) {
            $metrics['Devices'] = $this->deviceCountForRouters($routerIds);
        }

        if ($this->auth->can('sessions.view')) {
            $metrics['Sessions'] = $this->countForRouters('sessions', $routerIds);
            $metrics['Active sessions'] = $this->countForRouters('sessions', $routerIds, "status='active'");
        }

        if ($this->auth->can('sales.view')) {
            $metrics['Sales today'] = $this->salesTodayForRouters($routerIds);
        }

        if ($this->auth->can('users.view')) {
            $metrics['Users'] = (int) $this->db
                ->query('SELECT COUNT(*) FROM users')
                ->fetchColumn();
        }

        $recentSessions = $this->recentSessionsForRouters($routerIds);

        $content = $this->view->render('dashboard/index', [
            'user' => $user,
            'role' => $this->accountRole($user),
            'metrics' => $metrics,
            'sessions' => $recentSessions,
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
     * Returns one short RouterOS command. The registration endpoint returns a
     * RouterOS script that installs the agent, so the terminal command does not
     * need to parse /tool fetch as-value output.
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

        // All RouterOS URLs are path-only; no query strings are used.
        $registerUrl = 'https://hs.portalx.win/api/router/register/' . $key;

        return ':local identity [/system identity get name]; '
            . ':local serial [/system routerboard get serial-number]; '
            . '/tool fetch url="' . $registerUrl . '" mode=https '
            . 'http-header-field=("X-PixiePoint-Identity: " . $identity . ",X-PixiePoint-Serial: " . $serial) '
            . 'dst-path="PixiePointRegister.rsc"; '
            . '/import file-name="PixiePointRegister.rsc"; '
            . '/file remove [find name="PixiePointRegister.rsc"]';
    }

    /**
     * Returns active router IDs accessible to the current account.
     * Platform owners see every active router; other users see their memberships.
     */
    private function accessibleRouterIds(int $userId, bool $platformOwner): array
    {
        if ($platformOwner) {
            return array_map(
                'intval',
                $this->db->query('SELECT id FROM routers WHERE enabled=1 ORDER BY id')->fetchAll(PDO::FETCH_COLUMN),
            );
        }

        $stmt = $this->db->prepare(
            'SELECT r.id
             FROM routers r
             JOIN router_members rm ON rm.router_id=r.id
             WHERE r.enabled=1 AND rm.user_id=?
             ORDER BY r.id',
        );
        $stmt->execute([$userId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function countForRouters(string $table, array $routerIds, string $condition = ''): int
    {
        if ($routerIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($routerIds), '?'));
        $sql = 'SELECT COUNT(*) FROM ' . $table . ' WHERE router_id IN (' . $placeholders . ')';
        if ($condition !== '') {
            $sql .= ' AND ' . $condition;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($routerIds);

        return (int) $stmt->fetchColumn();
    }

    private function deviceCountForRouters(array $routerIds): int
    {
        if ($routerIds === []) {
            return 0;
        }

        $placeholders = implode(',', array_fill(0, count($routerIds), '?'));
        $sql = 'SELECT COUNT(DISTINCT device_id) FROM ('
            . 'SELECT s.device_id FROM sessions s '
            . 'WHERE s.router_id IN (' . $placeholders . ') AND s.device_id IS NOT NULL '
            . 'UNION '
            . 'SELECT e.device_id FROM router_login_events e '
            . 'WHERE e.router_id IN (' . $placeholders . ') AND e.device_id IS NOT NULL'
            . ') router_devices';

        $stmt = $this->db->prepare($sql);
        $stmt->execute([...$routerIds, ...$routerIds]);

        return (int) $stmt->fetchColumn();
    }

    private function salesTodayForRouters(array $routerIds): float
    {
        if ($routerIds === []) {
            return 0.0;
        }

        $placeholders = implode(',', array_fill(0, count($routerIds), '?'));
        $stmt = $this->db->prepare(
            "SELECT COALESCE(SUM(amount_pesos),0)
             FROM router_login_events
             WHERE router_id IN ($placeholders) AND created_at >= CURDATE()",
        );
        $stmt->execute($routerIds);

        return (float) $stmt->fetchColumn();
    }

    private function recentSessionsForRouters(array $routerIds): array
    {
        if ($routerIds === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($routerIds), '?'));
        $stmt = $this->db->prepare(
            'SELECT s.*,d.mac,r.name AS router_name '
            . 'FROM sessions s '
            . 'LEFT JOIN devices d ON d.id=s.device_id '
            . 'LEFT JOIN routers r ON r.id=s.router_id '
            . "WHERE s.router_id IN ($placeholders) "
            . 'ORDER BY s.updated_at DESC LIMIT 8',
        );
        $stmt->execute($routerIds);

        return $stmt->fetchAll();
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
