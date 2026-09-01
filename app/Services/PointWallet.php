<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

use PDO;
use RuntimeException;

final class PointWallet
{
    public function __construct(private PDO $db) {}

    public function walletForDevice(int $deviceId, ?int $userId = null): array
    {
        if ($userId) return $this->walletForUser($userId);

        $stmt = $this->db->prepare("SELECT * FROM point_wallets WHERE device_id=? AND status='active' LIMIT 1");
        $stmt->execute([$deviceId]);
        if ($wallet = $stmt->fetch()) return $wallet;

        $this->db->prepare("INSERT INTO point_wallets(device_id,status,created_at,updated_at) VALUES(?,'active',?,?)")
            ->execute([$deviceId, now(), now()]);
        return $this->find((int)$this->db->lastInsertId());
    }

    public function walletForUser(int $userId): array
    {
        $stmt = $this->db->prepare("SELECT * FROM point_wallets WHERE user_id=? AND status='active' LIMIT 1");
        $stmt->execute([$userId]);
        if ($wallet = $stmt->fetch()) return $wallet;

        $this->db->prepare("INSERT INTO point_wallets(user_id,status,created_at,updated_at) VALUES(?,'active',?,?)")
            ->execute([$userId, now(), now()]);
        return $this->find((int)$this->db->lastInsertId());
    }

    public function earn(int $deviceId, ?int $userId, int $points, string $eventKey): array
    {
        $wallet = $this->walletForDevice($deviceId, $userId);
        $points = max(0, $points);
        if ($points === 0) return $wallet;

        $stmt = $this->db->prepare("INSERT IGNORE INTO point_ledger(wallet_id,points,entry_type,source_type,source_key,description,created_at) VALUES(?,?,'earn','router_login',?,'PisoWiFi purchase',?)");
        $stmt->execute([(int)$wallet['id'], $points, $eventKey, now()]);
        if ($stmt->rowCount() > 0) {
            $this->db->prepare('UPDATE point_wallets SET balance=balance+?,updated_at=? WHERE id=?')
                ->execute([$points, now(), $wallet['id']]);
            if ($userId) {
                $this->db->prepare('UPDATE users SET points=points+? WHERE id=?')->execute([$points, $userId]);
            }
        }

        return $this->find((int)$wallet['id']);
    }

    /** Convert old unawarded event points into a real device wallet before claiming. */
    public function importLegacyGuestPoints(int $deviceId): int
    {
        $wallet = $this->walletForDevice($deviceId, null);
        $stmt = $this->db->prepare('SELECT id,event_key,GREATEST(points_earned-points_awarded,0) points FROM router_login_events WHERE device_id=? AND points_earned>points_awarded ORDER BY id FOR UPDATE');
        $stmt->execute([$deviceId]);
        $imported = 0;

        foreach ($stmt->fetchAll() as $event) {
            $points = max(0, (int)$event['points']);
            if ($points === 0) continue;
            $insert = $this->db->prepare("INSERT IGNORE INTO point_ledger(wallet_id,points,entry_type,source_type,source_key,description,created_at) VALUES(?,?,'earn','router_login',?,'Imported guest points',?)");
            $insert->execute([(int)$wallet['id'], $points, (string)$event['event_key'], now()]);
            if ($insert->rowCount() > 0) {
                $imported += $points;
                $this->db->prepare('UPDATE point_wallets SET balance=balance+?,updated_at=? WHERE id=?')->execute([$points, now(), $wallet['id']]);
            }
            $this->db->prepare('UPDATE router_login_events SET points_awarded=points_earned,point_wallet_id=? WHERE id=?')
                ->execute([$wallet['id'], $event['id']]);
        }

        return $imported;
    }

    public function claimDeviceWallet(int $deviceId, int $userId): int
    {
        $this->importLegacyGuestPoints($deviceId);

        $guestStmt = $this->db->prepare("SELECT * FROM point_wallets WHERE device_id=? AND status='active' LIMIT 1 FOR UPDATE");
        $guestStmt->execute([$deviceId]);
        $guest = $guestStmt->fetch();
        if (!$guest) return 0;

        $points = max(0, (int)$guest['balance']);
        $user = $this->walletForUser($userId);
        if ($points > 0) {
            $claimKey = 'wallet-claim:' . (int)$guest['id'];
            $insert = $this->db->prepare("INSERT IGNORE INTO point_ledger(wallet_id,points,entry_type,source_type,source_key,description,created_at) VALUES(?,?,'claim','device_wallet',?,'Claimed guest points',?)");
            $insert->execute([(int)$user['id'], $points, $claimKey, now()]);
            if ($insert->rowCount() > 0) {
                $this->db->prepare('UPDATE point_wallets SET balance=balance+?,updated_at=? WHERE id=?')->execute([$points, now(), $user['id']]);
                $this->db->prepare('UPDATE users SET points=points+? WHERE id=?')->execute([$points, $userId]);
            } else {
                $points = 0;
            }
        }

        $this->db->prepare("UPDATE point_wallets SET balance=0,status='claimed',claimed_by_wallet_id=?,claimed_at=?,updated_at=? WHERE id=?")
            ->execute([$user['id'], now(), now(), $guest['id']]);

        return $points;
    }

    public function balanceForDevice(int $deviceId, ?int $userId = null): int
    {
        if ($userId) return (int)$this->walletForUser($userId)['balance'];
        return (int)$this->walletForDevice($deviceId, null)['balance'];
    }

    private function find(int $walletId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM point_wallets WHERE id=? LIMIT 1');
        $stmt->execute([$walletId]);
        $wallet = $stmt->fetch();
        if (!$wallet) throw new RuntimeException('Point wallet could not be loaded.');
        return $wallet;
    }
}
