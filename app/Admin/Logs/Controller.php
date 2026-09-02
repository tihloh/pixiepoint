<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Logs;

use PixiePoint\App\Admin\Shared\FeatureController;

final class Controller extends FeatureController
{
    public function index(): never
    {
        $names = [];
        $stmt = $this->db->prepare('SELECT name,email FROM users WHERE id=? LIMIT 1');
        $actorResolver = function (int|string $id) use (&$names, $stmt): ?string {
            $key = (string)$id;
            if (array_key_exists($key, $names)) return $names[$key];
            $stmt->execute([$id]);
            $user = $stmt->fetch();
            return $names[$key] = $user ? (string)($user['name'] ?: $user['email'] ?: ('User #' . $id)) : null;
        };

        $this->page('Logs', __DIR__ . '/views/index.php', [
            'logs' => $this->logs->humanRecent(200, 0, $actorResolver),
        ]);
    }
}
