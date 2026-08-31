<?php

declare(strict_types=1);

namespace PixiePoint\App\Models;

use PDO;

final class User
{
    public function __construct(private PDO $db) {}

    public function count(): int { return (int)$this->db->query('SELECT COUNT(*) FROM users')->fetchColumn(); }

    public function create(string $name, string $email, ?string $passwordHash, string $role = 'user'): int
    {
        $stmt = $this->db->prepare('INSERT INTO users(name,email,password_hash,platform_role) VALUES(?,?,?,?)');
        $stmt->execute([$name, $email, $passwordHash, $role]);
        return (int)$this->db->lastInsertId();
    }
}
