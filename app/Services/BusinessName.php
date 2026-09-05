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

    public function isCertifiedOwner(int $userId): bool
    {
        return $this->ownerRouterId($userId) !== null;
    }

    public function owner(int $userId): string
    {
        if (!$this->isCertifiedOwner($userId)) {
            return '';
        }

        $stmt = $this->db->prepare('SELECT name,business_name_template FROM users WHERE id=? LIMIT 1');
        $stmt->execute([$userId]);
        $user = $stmt->fetch();
        if (!$user) {
            return '';
        }

        $fallback = trim((string) ($user['name'] ?? ''));
        $template = trim((string) ($user['business_name_template'] ?? ''));

        return $template === '' ? $fallback : $this->render($template, '');
    }

    public function router(int $routerId): string
    {
        $stmt = $this->db->prepare(
            "SELECT r.business_name_template,rm.user_id,u.name owner_name,u.business_name_template owner_business_name
             FROM routers r
             JOIN router_members rm ON rm.router_id=r.id AND rm.role='owner'
             JOIN users u ON u.id=rm.user_id
             WHERE r.id=?
             ORDER BY rm.user_id
             LIMIT 1",
        );
        $stmt->execute([$routerId]);
        $router = $stmt->fetch();
        if (!$router) {
            return '';
        }

        $ownerName = trim((string) ($router['owner_business_name'] ?? ''));
        if ($ownerName === '') {
            $ownerName = trim((string) ($router['owner_name'] ?? ''));
        }

        $template = trim((string) ($router['business_name_template'] ?? ''));

        return $template === '' ? $ownerName : $this->render($template, $ownerName);
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

        $parent = $this->router((int) $vendo['router_id']);
        $template = trim((string) ($vendo['business_name_template'] ?? ''));

        return $template === '' ? $parent : $this->render($template, $parent);
    }

    private function ownerRouterId(int $userId): ?int
    {
        $stmt = $this->db->prepare(
            "SELECT router_id
             FROM router_members
             WHERE user_id=? AND role='owner'
             ORDER BY router_id
             LIMIT 1",
        );
        $stmt->execute([$userId]);
        $routerId = $stmt->fetchColumn();

        return $routerId === false ? null : (int) $routerId;
    }

    private function render(string $template, string $parent): string
    {
        return trim(str_replace('{parent}', $parent, $template));
    }

    private function ensureSchema(): void
    {
        if (self::$schemaReady) {
            return;
        }

        $this->db->exec(
            "ALTER TABLE users ADD COLUMN IF NOT EXISTS business_name_template VARCHAR(255) NULL AFTER points",
        );
        $this->db->exec(
            "ALTER TABLE routers ADD COLUMN IF NOT EXISTS business_name_template VARCHAR(255) NULL AFTER location",
        );
        $this->db->exec(
            "ALTER TABLE vendos ADD COLUMN IF NOT EXISTS business_name_template VARCHAR(255) NULL AFTER name",
        );

        self::$schemaReady = true;
    }
}
