<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Users;

use PixiePoint\App\Admin\Shared\FeatureController;

final class Controller extends FeatureController
{
    public function index(): never
    {
        $this->auth->requireAccount();

        $users = $this->db
            ->query(
                'SELECT id,name,email,active,platform_role,created_at,updated_at '
                . 'FROM users ORDER BY created_at DESC,id DESC',
            )
            ->fetchAll();

        $this->page('Users', __DIR__ . '/views/index.php', [
            'users' => $users,
            'canManagePermissions' => $this->auth->can('permissions.manage'),
        ]);
    }
}
