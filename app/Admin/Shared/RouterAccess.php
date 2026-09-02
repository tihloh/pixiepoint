<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Shared;

use PDO;

/**
 * Controls which staff members can work with a router and its related vendos.
 *
 * Access is attached to the router because vendos, sessions, devices and sales
 * are operational children of that router. A router can therefore be shared by
 * several staff members instead of being owned by one account.
 */
final class RouterAccess
{
    private const WRITE_ROLES = ['owner', 'manager', 'operator'];

    public function __construct(private PDO $db)
    {
        $this->ensureSchema();
    }

    public function addOwner(int $routerId, int $userId): void
    {
        $stmt = $this->db->prepare(
            "INSERT INTO router_members(router_id,user_id,role)
             VALUES(?,?,'owner')
             ON DUPLICATE KEY UPDATE role=IF(role='owner',role,VALUES(role))",
        );
        $stmt->execute([$routerId, $userId]);
    }

    public function canView(int $routerId, int $userId, bool $platformOwner): bool
    {
        return $platformOwner || $this->hasMembership($routerId, $userId);
    }

    public function canManage(int $routerId, int $userId, bool $platformOwner): bool
    {
        if ($platformOwner) {
            return true;
        }

        $stmt = $this->db->prepare(
            'SELECT role FROM router_members WHERE router_id=? AND user_id=? LIMIT 1',
        );
        $stmt->execute([$routerId, $userId]);

        return in_array((string) $stmt->fetchColumn(), self::WRITE_ROLES, true);
    }

    public function routerIdsFor(int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT router_id FROM router_members WHERE user_id=? ORDER BY router_id',
        );
        $stmt->execute([$userId]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    private function hasMembership(int $routerId, int $userId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM router_members WHERE router_id=? AND user_id=? LIMIT 1',
        );
        $stmt->execute([$routerId, $userId]);

        return (bool) $stmt->fetchColumn();
    }

    private function ensureSchema(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS router_members (
                router_id BIGINT UNSIGNED NOT NULL,
                user_id BIGINT UNSIGNED NOT NULL,
                role VARCHAR(32) NOT NULL DEFAULT 'operator',
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY (router_id, user_id),
                INDEX idx_router_members_user (user_id),
                FOREIGN KEY (router_id) REFERENCES routers(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",
        );

        // Preserve access for existing installations: the old vendo owner is
        // promoted to owner of that router's shared staff team.
        $this->db->exec(
            "INSERT IGNORE INTO router_members(router_id,user_id,role)
             SELECT DISTINCT router_id,owner_user_id,'owner'
             FROM vendos
             WHERE owner_user_id IS NOT NULL",
        );
    }
}
