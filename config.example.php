<?php

return [
    'app_name' => getenv('APP_NAME') ?: 'PixiePoint Wi-Fi',
    'base_url' => getenv('APP_URL') ?: 'https://hs.portalx.win',
    'database_dsn' => getenv('DB_DSN') ?: 'mysql:host=mariadb;port=3306;dbname=pixiepoint;charset=utf8mb4',
    'database_user' => getenv('DB_USER') ?: 'pixiepoint',
    'database_password' => getenv('DB_PASS') ?: '',
    'session_name' => getenv('SESSION_NAME') ?: 'pixiepoint_session',
    'cookie_secure' => filter_var(getenv('COOKIE_SECURE') ?: 'true', FILTER_VALIDATE_BOOL),
    'timezone' => getenv('APP_TIMEZONE') ?: 'Asia/Manila',
    'accounting_key' => getenv('ACCOUNTING_KEY') ?: '',
];
