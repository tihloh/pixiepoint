<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Routers;

use PDO;
use Throwable;

/**
 * Persistent outbound command queue for MikroTik routers.
 *
 * Command lifecycle:
 * pending -> delivered -> completed/failed.
 * Completed commands are removed immediately after acknowledgement so the queue
 * table stays small. Failed commands remain available for troubleshooting.
 */
final class CommandQueue
{
    public function __construct(private readonly PDO $db)
    {
        $this->ensureTable();
    }

    /**
     * Adds one RouterOS command to the queue.
     *
     * Higher priority values are delivered before lower priority values.
     */
    public function enqueue(
        int $routerId,
        string $command,
        int $priority = 100,
    ): int {
        $stmt = $this->db->prepare(
            'INSERT INTO router_commands (router_id, command, priority) '
            . 'VALUES (?, ?, ?)',
        );
        $stmt->execute([$routerId, $command, $priority]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Atomically reserves and returns the next command for a router.
     */
    public function deliverNext(int $routerId): ?array
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare(
                "SELECT id, command
                 FROM router_commands
                 WHERE router_id = ? AND status = 'pending'
                 ORDER BY priority DESC, id ASC
                 LIMIT 1
                 FOR UPDATE",
            );
            $stmt->execute([$routerId]);
            $row = $stmt->fetch();

            if (!$row) {
                $this->db->commit();
                return null;
            }

            $update = $this->db->prepare(
                "UPDATE router_commands
                 SET status = 'delivered', delivered_at = NOW()
                 WHERE id = ?",
            );
            $update->execute([(int) $row['id']]);

            $this->db->commit();

            return [
                'id' => (int) $row['id'],
                'command' => (string) $row['command'],
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $e;
        }
    }

    /**
     * Finalizes a delivered command from a router acknowledgement.
     *
     * Successful commands are deleted to prevent unbounded queue growth. Failed
     * rows are retained with their result for diagnosis.
     */
    public function acknowledge(
        int $routerId,
        int $commandId,
        string $status,
        string $result = '',
    ): bool {
        if (!in_array($status, ['completed', 'failed'], true)) {
            return false;
        }

        if ($status === 'completed') {
            $stmt = $this->db->prepare(
                "DELETE FROM router_commands
                 WHERE id = ? AND router_id = ? AND status = 'delivered'",
            );
            $stmt->execute([$commandId, $routerId]);

            return $stmt->rowCount() > 0;
        }

        $stmt = $this->db->prepare(
            "UPDATE router_commands
             SET status = 'failed', completed_at = NOW(), result = ?
             WHERE id = ? AND router_id = ? AND status = 'delivered'",
        );
        $stmt->execute([
            $result !== '' ? mb_substr($result, 0, 2000) : null,
            $commandId,
            $routerId,
        ]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Creates the queue table lazily so existing installations upgrade without
     * requiring a separate manual migration step.
     */
    private function ensureTable(): void
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
    INDEX idx_router_commands_poll (router_id, status, priority, id),
    FOREIGN KEY (router_id) REFERENCES routers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);
    }
}
