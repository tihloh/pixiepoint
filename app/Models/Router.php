<?php

declare(strict_types=1);

namespace PixiePoint\App\Models;

use PDO;

final class Router
{
    public function __construct(private PDO $db)
    {
    }

    public function enabledByIdentity(string $identity): array|false
    {
        $stmt = $this->db->prepare('SELECT * FROM routers WHERE identity=? AND enabled=1');
        $stmt->execute([$identity]);

        return $stmt->fetch();
    }
}
