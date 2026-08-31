<?php

declare(strict_types=1);

namespace PixiePoint;

use Tihloh\Prefab\Users\Contracts\UserFactoryInterface;
use Tihloh\Prefab\Users\User\PrefabUser;

final class AppUserFactory implements UserFactoryInterface
{
    public function make(
        int|string $id,
        ?string $name,
        ?string $email,
        bool $active,
        array $attributes = [],
    ): PrefabUser {
        return new AppUser($id, $name, $email, $active, $attributes);
    }
}
