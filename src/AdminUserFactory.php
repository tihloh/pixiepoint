<?php

declare(strict_types=1);

namespace PixiePoint;

use PDO;
use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Auth\Contracts\AuthUserProviderInterface;
use Tihloh\Prefab\PrefabConfig;
use Tihloh\Prefab\Users\Contracts\UserFactoryInterface;
use Tihloh\Prefab\Users\User\PrefabUser;

final class AdminUserFactory implements UserFactoryInterface
{
    public function __construct()
    {
        $app = $GLOBALS['app'] ?? null;
        if ($app && isset($app->db) && $app->db instanceof PDO) {
            PrefabConfig::set([
                'modules' => [
                    'auth' => [
                        'provider' => new PixiePointAdminAuthProvider($app->db),
                    ],
                ],
            ]);
        }
    }

    public function make(
        int|string $id,
        ?string $name,
        ?string $email,
        bool $active,
        array $attributes = [],
    ): PrefabUser {
        return new AdminUser($id, $name, $email, $active, $attributes);
    }
}

final class PixiePointAdminAuthProvider implements AuthUserProviderInterface
{
    public function __construct(private PDO $db) {}

    public function findByIdentifier(string $identifier): ?AuthenticatableUserInterface
    {
        $stmt = $this->db->prepare('SELECT id,name,email,password_hash FROM admins WHERE email=? LIMIT 1');
        $stmt->execute([strtolower(trim($identifier))]);
        return $this->hydrate($stmt->fetch());
    }

    public function findById(int|string $id): ?AuthenticatableUserInterface
    {
        $stmt = $this->db->prepare('SELECT id,name,email,password_hash FROM admins WHERE id=? LIMIT 1');
        $stmt->execute([$id]);
        return $this->hydrate($stmt->fetch());
    }

    private function hydrate(array|false $row): ?AdminUser
    {
        if (!$row) {
            return null;
        }

        return new AdminUser(
            $row['id'],
            $row['name'] ?? null,
            $row['email'] ?? null,
            true,
            ['password_hash' => $row['password_hash'] ?? null],
        );
    }
}
