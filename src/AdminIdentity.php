<?php

declare(strict_types=1);

namespace PixiePoint;

use Tihloh\Prefab\Auth\Contracts\AuthenticatableUserInterface;
use Tihloh\Prefab\Users\Contracts\UserFactoryInterface;
use Tihloh\Prefab\Users\User\PrefabUser;

final class AdminUser extends PrefabUser implements AuthenticatableUserInterface
{
    public function authId(): int|string
    {
        return $this->id;
    }

    public function authPasswordHash(): ?string
    {
        $hash = $this->get('password_hash');
        return is_string($hash) && $hash !== '' ? $hash : null;
    }

    public function authIsActive(): bool
    {
        return $this->active;
    }
}

final class AdminUserFactory implements UserFactoryInterface
{
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
