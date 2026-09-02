<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Routers;

use PDO;

final class CommandQueue
{
    public function __construct(private readonly PDO $db)
    {
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS router_commands (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    router_id BIGINT UNSIGNED NOT NULL,
    command LONGTEXT NOT NULL,
    priority INT NOT NULL DEFAULT 100,
    status VARCHAR(16) NOT NULL DEFAULT 'pending',
    delivered_at DATETIME NULL,
    completed_at DATETIME NULL,
    result TEXT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_router_commands_poll (router_id,status,priority,id),
    FOREIGN KEY(router_id) REFERENCES routers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }

    public function enqueue(int $routerId, string $command, int $priority = 100): int
    {
        $stmt = $this->db->prepare('INSERT INTO router_commands(router_id,command,priority) VALUES(?,?,?)');
        $stmt->execute([$routerId, $command, $priority]);
        return (int)$this->db->lastInsertId();
    }

    public function deliverNext(int $routerId): ?array
    {
        $this->db->beginTransaction();
        try {
            $stmt = $this->db->prepare("SELECT id,command FROM router_commands WHERE router_id=? AND status='pending' ORDER BY priority DESC,id ASC LIMIT 1 FOR UPDATE");
            $stmt->execute([$routerId]);
            $row = $stmt->fetch();
            if (!$row) {
                $this->db->commit();
                return null;
            }
            $update = $this->db->prepare("UPDATE router_commands SET status='delivered',delivered_at=NOW() WHERE id=?");
            $update->execute([(int)$row['id']]);
            $this->db->commit();
            return ['id'=>(int)$row['id'],'command'=>(string)$row['command']];
        } catch (\Throwable $e) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            throw $e;
        }
    }

    public function acknowledge(int $routerId, int $commandId, string $status, string $result = ''): bool
    {
        if (!in_array($status, ['completed','failed'], true)) return false;
        $stmt = $this->db->prepare('UPDATE router_commands SET status=?,completed_at=NOW(),result=? WHERE id=? AND router_id=? AND status=\'delivered\'');
        $stmt->execute([$status, $result !== '' ? mb_substr($result, 0, 2000) : null, $commandId, $routerId]);
        return $stmt->rowCount() > 0;
    }
}
