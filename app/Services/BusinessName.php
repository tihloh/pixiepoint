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
            'SELECT business_name FROM routers WHERE id=? LIMIT 1',
        );
        $stmt->execute([$routerId]);

        return trim((string) ($stmt->fetchColumn() ?: ''));
    }

    public function vendo(int $vendoId): string
    {
        $stmt = $this->db->prepare(
            'SELECT business_name,router_id FROM vendos WHERE id=? LIMIT 1',
        );
        $stmt->execute([$vendoId]);
        $vendo = $stmt->fetch();
        if (!$vendo) {
            return '';
        }

        $name = trim((string) ($vendo['business_name'] ?? ''));
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
            "ALTER TABLE routers ADD COLUMN IF NOT EXISTS business_name VARCHAR(255) NULL AFTER location",
        );
        $this->db->exec(
            "ALTER TABLE vendos ADD COLUMN IF NOT EXISTS business_name VARCHAR(255) NULL AFTER name",
        );

        // Preserve names created by the previous template-based implementation.
        $this->db->exec(
            "UPDATE routers SET business_name=business_name_template
             WHERE business_name IS NULL AND business_name_template IS NOT NULL",
        );
        $this->db->exec(
            "UPDATE vendos SET business_name=business_name_template
             WHERE business_name IS NULL AND business_name_template IS NOT NULL",
        );

        self::$schemaReady = true;
    }
}
