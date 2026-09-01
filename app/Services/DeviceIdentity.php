<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

use PDO;
use RuntimeException;

final class DeviceIdentity
{
    private const COOKIE = 'pixiepoint_device';

    public function __construct(private PDO $db) {}

    public function observe(string $mac, string $scopeKey, ?int $userId = null, string $ip = '', string $userAgent = ''): array
    {
        $mac = client_mac($mac);
        $token = $this->cookieToken();
        $cookieDevice = $token !== '' ? $this->deviceByIdentity('browser_token', hash('sha256', $token), 'global') : null;
        $macDevice = $mac !== '' ? $this->deviceByMac($mac, $scopeKey) : null;

        $device = null;
        $identityConflict = false;
        if ($cookieDevice && $macDevice && (int)$cookieDevice['id'] !== (int)$macDevice['id']) {
            $identityConflict = true;
            $_SESSION['device_identity_conflict'] = [
                'cookie_device_id' => (int)$cookieDevice['id'],
                'mac_device_id' => (int)$macDevice['id'],
                'mac' => $mac,
                'scope_key' => $scopeKey,
                'seen_at' => now(),
            ];
            // Keep the browser's established device, but never steal the MAC
            // identity from the other record. A signed-in user can resolve the
            // ambiguity explicitly from the dashboard.
            $device = $cookieDevice;
        } elseif ($cookieDevice) {
            $device = $cookieDevice;
        } elseif ($macDevice) {
            $device = $macDevice;
        }

        if (!$device) {
            $device = $this->createDevice($mac, $userId, $ip, $userAgent);
        } else {
            $device = $this->canonicalDevice($device);
            $this->touchDevice((int)$device['id'], $ip, $userAgent);
        }

        if ($mac !== '' && !$identityConflict) {
            $this->attachIdentity((int)$device['id'], 'mac', $mac, $scopeKey, 100);
        }

        if ($token === '') {
            $token = bin2hex(random_bytes(32));
            $this->setCookieToken($token);
        }
        $this->attachIdentity((int)$device['id'], 'browser_token', hash('sha256', $token), 'global', 100);

        $_SESSION['current_device_id'] = (int)$device['id'];
        return $this->findDevice((int)$device['id']) ?? $device;
    }

    public function currentDevice(): ?array
    {
        $id = (int)($_SESSION['current_device_id'] ?? 0);
        if ($id > 0) return $this->findDevice($id);

        $token = $this->cookieToken();
        if ($token === '') return null;
        return $this->deviceByIdentity('browser_token', hash('sha256', $token), 'global');
    }

    public function userDevices(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT d.*, COUNT(di.id) identity_count FROM devices d LEFT JOIN device_identities di ON di.device_id=d.id WHERE d.user_id=? AND d.merged_into_device_id IS NULL GROUP BY d.id ORDER BY d.last_seen_at DESC');
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function claimAsNew(int $deviceId, int $userId): void
    {
        $device = $this->findDevice($deviceId);
        if (!$device) throw new RuntimeException('Device not found.');
        if ($device['user_id'] !== null && (int)$device['user_id'] !== $userId) throw new RuntimeException('This device is already linked to another account.');

        $stmt = $this->db->prepare('UPDATE devices SET user_id=? WHERE id=?');
        $stmt->execute([$userId, (int)$device['id']]);
        $this->db->prepare('UPDATE sessions SET user_id=? WHERE device_id=? AND user_id IS NULL')->execute([$userId, (int)$device['id']]);
        $this->db->prepare('UPDATE router_login_events SET user_id=? WHERE device_id=? AND user_id IS NULL')->execute([$userId, (int)$device['id']]);
    }

    public function mergeInto(int $sourceId, int $targetId, int $userId): void
    {
        if ($sourceId === $targetId) {
            $this->claimAsNew($sourceId, $userId);
            return;
        }

        $source = $this->findDevice($sourceId);
        $target = $this->findDevice($targetId);
        if (!$source || !$target) throw new RuntimeException('Device not found.');
        if ((int)($target['user_id'] ?? 0) !== $userId) throw new RuntimeException('The target device does not belong to your account.');
        if ($source['user_id'] !== null && (int)$source['user_id'] !== $userId) throw new RuntimeException('The detected device belongs to another account.');

        $this->db->beginTransaction();
        try {
            $copy = $this->db->prepare('INSERT IGNORE INTO device_identities(device_id,identity_type,identity_value,scope_key,confidence,first_seen_at,last_seen_at) SELECT ?,identity_type,identity_value,scope_key,confidence,first_seen_at,last_seen_at FROM device_identities WHERE device_id=?');
            $copy->execute([(int)$target['id'], (int)$source['id']]);
            $this->db->prepare('DELETE FROM device_identities WHERE device_id=?')->execute([(int)$source['id']]);
            $this->db->prepare('UPDATE sessions SET device_id=?,user_id=COALESCE(user_id,?) WHERE device_id=?')->execute([(int)$target['id'], $userId, (int)$source['id']]);
            $this->db->prepare('UPDATE router_login_events SET device_id=?,user_id=COALESCE(user_id,?) WHERE device_id=?')->execute([(int)$target['id'], $userId, (int)$source['id']]);
            $this->db->prepare('UPDATE devices SET user_id=?,merged_into_device_id=?,last_seen_at=? WHERE id=?')->execute([$userId, (int)$target['id'], now(), (int)$source['id']]);
            $this->db->prepare('UPDATE devices SET last_seen_at=? WHERE id=?')->execute([now(), (int)$target['id']]);
            $this->db->commit();
            $_SESSION['current_device_id'] = (int)$target['id'];
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    public function findDevice(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM devices WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        $device = $stmt->fetch();
        if (!$device) return null;
        return $this->canonicalDevice($device);
    }

    private function createDevice(string $mac, ?int $userId, string $ip, string $userAgent): array
    {
        $uuid = $this->uuidV4();
        $stmt = $this->db->prepare('INSERT INTO devices(uuid,user_id,mac,last_ip,user_agent,last_seen_at) VALUES(?,?,?,?,?,?)');
        $stmt->execute([$uuid, $userId, $mac !== '' ? $mac : null, $ip !== '' ? $ip : null, substr($userAgent, 0, 500), now()]);
        return $this->findDevice((int)$this->db->lastInsertId()) ?? throw new RuntimeException('Could not create device identity.');
    }

    private function deviceByMac(string $mac, string $scopeKey): ?array
    {
        $stmt = $this->db->prepare("SELECT d.* FROM device_identities di JOIN devices d ON d.id=di.device_id WHERE di.identity_type='mac' AND di.identity_value=? AND di.scope_key=? AND d.merged_into_device_id IS NULL ORDER BY di.last_seen_at DESC LIMIT 1");
        $stmt->execute([$mac, $scopeKey]);
        $device = $stmt->fetch();
        if ($device) return $device;

        $stmt = $this->db->prepare("SELECT d.*,COUNT(*) OVER() matches FROM device_identities di JOIN devices d ON d.id=di.device_id WHERE di.identity_type='mac' AND di.identity_value=? AND d.merged_into_device_id IS NULL GROUP BY d.id ORDER BY MAX(di.last_seen_at) DESC LIMIT 1");
        $stmt->execute([$mac]);
        $device = $stmt->fetch();
        if ($device && (int)$device['matches'] === 1) return $device;

        $legacy = $this->db->prepare('SELECT * FROM devices WHERE mac=? AND merged_into_device_id IS NULL LIMIT 1');
        $legacy->execute([$mac]);
        return $legacy->fetch() ?: null;
    }

    private function deviceByIdentity(string $type, string $value, string $scope): ?array
    {
        $stmt = $this->db->prepare('SELECT d.* FROM device_identities di JOIN devices d ON d.id=di.device_id WHERE di.identity_type=? AND di.identity_value=? AND di.scope_key=? LIMIT 1');
        $stmt->execute([$type, $value, $scope]);
        $device = $stmt->fetch();
        return $device ? $this->canonicalDevice($device) : null;
    }

    private function attachIdentity(int $deviceId, string $type, string $value, string $scope, int $confidence): void
    {
        $stmt = $this->db->prepare('INSERT INTO device_identities(device_id,identity_type,identity_value,scope_key,confidence,first_seen_at,last_seen_at) VALUES(?,?,?,?,?,?,?) ON DUPLICATE KEY UPDATE confidence=GREATEST(confidence,VALUES(confidence)),last_seen_at=VALUES(last_seen_at)');
        $stmt->execute([$deviceId, $type, $value, $scope, $confidence, now(), now()]);
    }

    private function touchDevice(int $deviceId, string $ip, string $userAgent): void
    {
        $stmt = $this->db->prepare('UPDATE devices SET last_ip=?,user_agent=?,last_seen_at=? WHERE id=?');
        $stmt->execute([$ip !== '' ? $ip : null, substr($userAgent, 0, 500), now(), $deviceId]);
    }

    private function canonicalDevice(array $device): array
    {
        $seen = [];
        while (!empty($device['merged_into_device_id'])) {
            $id = (int)$device['merged_into_device_id'];
            if (isset($seen[$id])) break;
            $seen[$id] = true;
            $stmt = $this->db->prepare('SELECT * FROM devices WHERE id=? LIMIT 1');
            $stmt->execute([$id]);
            $next = $stmt->fetch();
            if (!$next) break;
            $device = $next;
        }
        return $device;
    }

    private function cookieToken(): string
    {
        $token = (string)($_COOKIE[self::COOKIE] ?? '');
        return preg_match('/^[a-f0-9]{64}$/', $token) ? $token : '';
    }

    private function setCookieToken(string $token): void
    {
        setcookie(self::COOKIE, $token, [
            'expires' => time() + 31536000,
            'path' => '/',
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        $_COOKIE[self::COOKIE] = $token;
    }

    private function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        $hex = bin2hex($bytes);
        return substr($hex, 0, 8) . '-' . substr($hex, 8, 4) . '-' . substr($hex, 12, 4) . '-' . substr($hex, 16, 4) . '-' . substr($hex, 20);
    }
}
