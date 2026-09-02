<?php

declare(strict_types=1);

return [
    'routers.view' => [
        'name' => 'View routers',
        'description' => 'View MikroTik routers registered in PixiePoint.',
        'default' => false,
    ],
    'routers.manage' => [
        'name' => 'Manage routers',
        'description' => 'Register and configure MikroTik routers.',
        'default' => false,
    ],
    'vendos.view' => [
        'name' => 'View vendos',
        'description' => 'View PixiePoint coin-slot/vendo configurations.',
        'default' => false,
    ],
    'vendos.manage' => [
        'name' => 'Manage vendos',
        'description' => 'Create and manage owned PixiePoint vendos.',
        'default' => false,
    ],
    'vouchers.view' => [
        'name' => 'View vouchers',
        'description' => 'View PisoWiFi vouchers.',
        'default' => false,
    ],
    'vouchers.manage' => [
        'name' => 'Manage vouchers',
        'description' => 'Create and manage PisoWiFi vouchers.',
        'default' => false,
    ],
    'devices.view' => [
        'name' => 'View devices',
        'description' => 'View hotspot customer devices.',
        'default' => false,
    ],
    'sessions.view' => [
        'name' => 'View sessions',
        'description' => 'View MikroTik hotspot sessions and accounting data.',
        'default' => false,
    ],
    'sales.view' => [
        'name' => 'View sales',
        'description' => 'View idempotent RouterOS login and vendo sales events.',
        'default' => false,
    ],
    'users.view' => [
        'name' => 'View users',
        'description' => 'View PixiePoint accounts.',
        'default' => false,
    ],
    'groups.manage' => [
        'name' => 'Manage groups',
        'description' => 'Manage user groups and membership.',
        'default' => false,
    ],
    'permissions.manage' => [
        'name' => 'Manage permissions',
        'description' => 'Manage Prefab permission overrides.',
        'default' => false,
    ],
    'logs.view' => [
        'name' => 'View logs',
        'description' => 'View Prefab audit and activity logs.',
        'default' => false,
    ],
    'platform.settings.manage' => [
        'name' => 'Manage platform settings',
        'description' => 'Manage PixiePoint platform-level settings.',
        'default' => false,
    ],
];
