<?php

declare(strict_types=1);

namespace PixiePoint\App\Models;

use Tihloh\Prefab\Permissions\Contracts\PermissionSubjectInterface;

final class PermissionUser implements PermissionSubjectInterface
{
    /** @param array<int|string> $groupIds */
    public function __construct(
        private int|string $id,
        private array $groupIds = [],
    ) {
    }

    public function permissionSubjectId(): int|string
    {
        return $this->id;
    }

    public function permissionGroupIds(): array
    {
        return $this->groupIds;
    }
}
