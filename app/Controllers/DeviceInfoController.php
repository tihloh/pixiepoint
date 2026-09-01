<?php

declare(strict_types=1);

namespace PixiePoint\App\Controllers;

use PDO;
use PixiePoint\App\Services\NetworkDeviceIdentity;
use PixiePoint\App\Services\PointWallet;
use Throwable;

final class DeviceInfoController
{
    public function __construct(
        private PDO $db,
        private NetworkDeviceIdentity $devices,
        private PointWallet $points,
    ) {}

    public function show(): never
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-store, no-cache, must-revalidate');

        $mac = client_mac((string)($_GET['mac'] ?? ''));
        $routerIdentity = trim((string)($_GET['router_identity'] ?? ''));
        $interface = trim((string)($_GET['interface'] ?? ''));
        $ip = substr(trim((string)($_GET['ip'] ?? '')), 0, 45);

        if ($mac === '') {
            $this->json(['ok' => false, 'error' => 'device.invalid_mac'], 422);
        }

        $scope = implode('|', array_filter([
            $routerIdentity,
            $interface,
        ], static fn (string $value): bool => $value !== '')) ?: 'global';

        try {
            $device = $this->devices->resolve($mac, $scope, $ip);
            if (!$device) {
                $this->json(['ok' => false, 'error' => 'device.not_found'], 404);
            }

            $deviceId = (int)$device['id'];
            $userId = !empty($device['user_id']) ? (int)$device['user_id'] : null;
            $account = null;

            if ($userId) {
                $stmt = $this->db->prepare('SELECT id,name FROM users WHERE id=? AND active=1 LIMIT 1');
                $stmt->execute([$userId]);
                if ($user = $stmt->fetch()) {
                    $account = [
                        'linked' => true,
                        'name' => (string)$user['name'],
                    ];
                }
            }

            $historyStmt = $this->db->prepare(
                'SELECT amount_pesos,duration_seconds,is_extension,points_earned,created_at '
                . 'FROM router_login_events WHERE device_id=? ORDER BY id DESC LIMIT 5'
            );
            $historyStmt->execute([$deviceId]);
            $history = array_map(static fn (array $row): array => [
                'amount' => (int)$row['amount_pesos'],
                'duration_seconds' => (int)$row['duration_seconds'],
                'extension' => (bool)$row['is_extension'],
                'points' => (int)$row['points_earned'],
                'created_at' => (string)$row['created_at'],
            ], $historyStmt->fetchAll());

            $statsStmt = $this->db->prepare(
                'SELECT COUNT(*) purchases,COALESCE(SUM(amount_pesos),0) spent,COALESCE(SUM(duration_seconds),0) purchased_seconds '
                . 'FROM router_login_events WHERE device_id=?'
            );
            $statsStmt->execute([$deviceId]);
            $stats = $statsStmt->fetch() ?: [];

            $this->json([
                'ok' => true,
                'registered' => $account !== null,
                'account' => $account,
                'points' => $this->points->balanceForDevice($deviceId, $userId),
                'device' => [
                    'uuid' => (string)($device['uuid'] ?? ''),
                    'mac' => $mac,
                    'first_seen_at' => (string)($device['first_seen_at'] ?? ''),
                    'last_seen_at' => (string)($device['last_seen_at'] ?? ''),
                ],
                'stats' => [
                    'purchases' => (int)($stats['purchases'] ?? 0),
                    'spent' => (int)($stats['spent'] ?? 0),
                    'purchased_seconds' => (int)($stats['purchased_seconds'] ?? 0),
                ],
                'history' => $history,
            ]);
        } catch (Throwable $error) {
            $this->json([
                'ok' => false,
                'error' => 'device.lookup_failed',
            ], 500);
        }
    }

    private function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        exit;
    }
}
