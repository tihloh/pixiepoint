<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

use PDO;
use Tihloh\Prefab\Auth\Services\AuthManager;

final class AuthContext
{
    public function __construct(private PDO $db, private AuthManager $auth) {}

    public function auth(): AuthManager
    {
        return $this->auth;
    }

    public function user(): ?array
    {
        $id = $this->auth->id();
        if ($id === null) return null;
        $stmt = $this->db->prepare('SELECT * FROM users WHERE id=? AND active=1');
        $stmt->execute([$id]);
        return $stmt->fetch() ?: null;
    }

    public function requireAccount(): array
    {
        if (!$this->auth->check()) redirect('/login');
        $user = $this->user();
        if (!$user) redirect('/logout');
        return $user;
    }

    public function isPlatformOwner(): bool
    {
        return ($this->user()['platform_role'] ?? '') === 'platform_owner';
    }

    public function requirePlatformOwner(View $view): array
    {
        $user = $this->requireAccount();
        if (($user['platform_role'] ?? '') !== 'platform_owner') {
            http_response_code(403);
            $view->page('Access denied', $view->portalCard('<h1>Access denied</h1><p class="muted">Your account does not have platform management access.</p><a class="button full" href="/dashboard">Back to dashboard</a>'));
        }
        return $user;
    }
}
