<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

use PDO;

final class BusinessName
{
    public function __construct(private PDO $db)
    {
    }

    public function router(int $routerId): string
    {
        $stmt = $this->db->prepare(
            'SELECT name FROM routers WHERE id=? LIMIT 1',
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
}
