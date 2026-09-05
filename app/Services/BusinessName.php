<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

use PDO;

final class BusinessName
{
    private static bool $schemaReady = false;

    public function __construct(private PDO $db)
    {
        $this->ensureSchema();
    }

    public function router(int $routerId): string
    {
        $stmt = $this->db->prepare(
            'SELECT business_name_template FROM routers WHERE id=? LIMIT 1',
        );
        $stmt->execute([$routerId]);

        return trim((string) ($stmt->fetchColumn() ?: ''));
    }

    public function vendo(int $vendoId): string
    {
        $stmt = $this->db->prepare(
            'SELECT business_name_template,router_id FROM vendos WHERE id=? LIMIT 1',
        );
        $stmt->execute([$vendoId]);
        $vendo = $stmt->fetch();
        if (!$vendo) {
            return '';
        }

        $name = trim((string) ($vendo['business_name_template'] ?? ''));
        if ($name !== '') {
            return $name;
        }

        return $this->router((int) $vendo['router_id']);
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        $this->db->exec(
            "ALTER TABLE routers ADD COLUMN IF NOT EXISTS business_name_template VARCHAR(255) NULL AFTER location",
        );
        $this->db->exec(
            "ALTER TABLE vendos ADD COLUMN IF NOT EXISTS business_name_template VARCHAR(255) NULL AFTER name",
        );

        self::$schemaReady = true;
    }
}
