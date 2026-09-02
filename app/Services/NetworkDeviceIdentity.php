<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

use PDO;

final class NetworkDeviceIdentity
{
    public function __construct(private PDO $db)
    {
    }

    public function resolve(string $mac, string $scopeKey, string $ip = ''): ?array
    {
        $mac = client_mac($mac);
        if ($mac === '') {
            return null;
        }

        $device = $this->byMac($mac, $scopeKey);
        if (!$device) {
            $uuid = $this->uuidV4();
            $stmt = $this->db->prepare('INSERT INTO devices(uuid,mac,last_ip,last_seen_at) VALUES(?,?,?,?)');
            $stmt->execute([$uuid, $mac, $ip !== '' ? $ip : null, now()]);
            $device = $this->find((int) $this->db->lastInsertId());
        } else {
            $this->db->prepare('UPDATE devices SET last_ip=?,last_seen_at=? WHERE id=?')->execute([$ip !== '' ? $ip : null, now(), (int) $device['id']]);
        }

        if (!$device) {
            return null;
        }
        $this->db->prepare('INSERT INTO device_identities(device_id,identity_type,identity_value,scope_key,confidence,first_seen_at,last_seen_at) VALUES(?,?,?,?,100,?,?) ON DUPLICATE KEY UPDATE last_seen_at=VALUES(last_seen_at)')
            ->execute([(int) $device['id'], 'mac', $mac, $scopeKey, now(), now()]);

        return $device;
    }

    private function byMac(string $mac, string $scopeKey): ?array
    {
        $stmt = $this->db->prepare("SELECT d.* FROM device_identities di JOIN devices d ON d.id=di.device_id WHERE di.identity_type='mac' AND di.identity_value=? AND di.scope_key=? AND d.merged_into_device_id IS NULL LIMIT 1");
        $stmt->execute([$mac, $scopeKey]);
        if ($device = $stmt->fetch()) {
            return $device;
        }

        $stmt = $this->db->prepare("SELECT d.id FROM device_identities di JOIN devices d ON d.id=di.device_id WHERE di.identity_type='mac' AND di.identity_value=? AND d.merged_into_device_id IS NULL GROUP BY d.id LIMIT 2");
        $stmt->execute([$mac]);
        $ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
        if (count($ids) === 1) {
            return $this->find((int) $ids[0]);
        }

        $stmt = $this->db->prepare('SELECT * FROM devices WHERE mac=? AND merged_into_device_id IS NULL LIMIT 1');
        $stmt->execute([$mac]);

        return $stmt->fetch() ?: null;
    }

    private function find(int $id): ?array
    {
        $seen = [];
        while ($id > 0 && !isset($seen[$id])) {
            $seen[$id] = true;
            $stmt = $this->db->prepare('SELECT * FROM devices WHERE id=? LIMIT 1');
            $stmt->execute([$id]);
            $device = $stmt->fetch();
            if (!$device) {
                return null;
            }
            if (empty($device['merged_into_device_id'])) {
                return $device;
            }
            $id = (int) $device['merged_into_device_id'];
        }

        return null;
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
