<?php

declare(strict_types=1);

final class App
{
    public readonly PDO $db;
    public readonly array $config;

    public function __construct(string $root)
    {
        $configFile = is_file($root . '/config.php') ? $root . '/config.php' : $root . '/config.example.php';
        $this->config = require $configFile;
        date_default_timezone_set($this->config['timezone'] ?? 'UTC');

        $dsn = (string)($this->config['database_dsn'] ?? '');
        if (!str_starts_with($dsn, 'mysql:')) {
            throw new RuntimeException('PixiePoint requires a MariaDB/MySQL PDO DSN.');
        }
        $this->db = new PDO($dsn, (string)($this->config['database_user'] ?? ''), (string)($this->config['database_password'] ?? ''), [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $this->migrate();
    }

    private function migrate(): void
    {
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    active TINYINT(1) NOT NULL DEFAULT 1,
    platform_role VARCHAR(32) NOT NULL DEFAULT 'user',
    points BIGINT NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS admins (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    email VARCHAR(254) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS groups (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS permissions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(120) NOT NULL UNIQUE,
    description VARCHAR(255),
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS user_groups (
    user_id BIGINT UNSIGNED NOT NULL,
    group_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY(user_id, group_id),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY(group_id) REFERENCES groups(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS group_permissions (
    group_id BIGINT UNSIGNED NOT NULL,
    permission_id BIGINT UNSIGNED NOT NULL,
    PRIMARY KEY(group_id, permission_id),
    FOREIGN KEY(group_id) REFERENCES groups(id) ON DELETE CASCADE,
    FOREIGN KEY(permission_id) REFERENCES permissions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS routers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(160) NOT NULL,
    identity VARCHAR(160) NOT NULL UNIQUE,
    public_host VARCHAR(255),
    location VARCHAR(255),
    api_key CHAR(48) NOT NULL UNIQUE,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    last_seen_at DATETIME,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS vouchers (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    code VARCHAR(128) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    label VARCHAR(255),
    duration_minutes INT UNSIGNED NOT NULL DEFAULT 60,
    data_limit_mb BIGINT UNSIGNED,
    max_devices INT UNSIGNED NOT NULL DEFAULT 1,
    max_uses INT UNSIGNED NOT NULL DEFAULT 1,
    uses INT UNSIGNED NOT NULL DEFAULT 0,
    expires_at DATETIME,
    enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS devices (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    mac CHAR(17) NOT NULL UNIQUE,
    last_ip VARCHAR(45),
    user_agent VARCHAR(500),
    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_devices_user (user_id),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sessions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NULL,
    radius_session_id VARCHAR(128) UNIQUE,
    voucher_id BIGINT UNSIGNED,
    router_id BIGINT UNSIGNED,
    device_id BIGINT UNSIGNED,
    username VARCHAR(128),
    client_ip VARCHAR(45),
    status VARCHAR(32) NOT NULL DEFAULT 'pending',
    started_at DATETIME,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at DATETIME,
    uptime_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
    bytes_in BIGINT UNSIGNED NOT NULL DEFAULT 0,
    bytes_out BIGINT UNSIGNED NOT NULL DEFAULT 0,
    terminate_cause VARCHAR(128),
    INDEX idx_sessions_user (user_id),
    INDEX idx_sessions_status (status),
    INDEX idx_sessions_updated (updated_at),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY(voucher_id) REFERENCES vouchers(id),
    FOREIGN KEY(router_id) REFERENCES routers(id),
    FOREIGN KEY(device_id) REFERENCES devices(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        // Upgrade existing PixiePoint v1 databases without removing guest data.
        $this->db->exec("ALTER TABLE devices ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL AFTER id");
        $this->db->exec("ALTER TABLE sessions ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL AFTER id");

        // Carry forward an existing first-install administrator as the platform owner.
        $this->db->exec("INSERT IGNORE INTO users(name,email,password_hash,platform_role,created_at) SELECT name,email,password_hash,'platform_owner',created_at FROM admins");
    }
}

function e(mixed $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(24));
    }
    return $_SESSION['csrf'];
}

function require_csrf(): void
{
    $provided = (string)($_POST['_csrf'] ?? '');
    if (!hash_equals((string)($_SESSION['csrf'] ?? ''), $provided)) {
        http_response_code(419);
        exit('The form expired. Go back and try again.');
    }
}

function redirect(string $path): never
{
    header('Location: ' . $path, true, 303);
    exit;
}

function client_mac(string $value): string
{
    $hex = strtoupper(preg_replace('/[^0-9A-Fa-f]/', '', $value));
    return strlen($hex) === 12 ? implode(':', str_split($hex, 2)) : '';
}

function bytes_nice(int $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $size = max(0, $bytes);
    $unit = 0;
    while ($size >= 1024 && $unit < count($units) - 1) {
        $size /= 1024;
        $unit++;
    }
    return number_format($size, $unit === 0 ? 0 : 1) . ' ' . $units[$unit];
}

function duration_nice(int $seconds): string
{
    $seconds = max(0, $seconds);
    $hours = intdiv($seconds, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return $hours > 0 ? "{$hours}h {$minutes}m" : "{$minutes}m";
}

function admin_required(): void
{
    global $adminAuth;
    if (!isset($adminAuth) || !$adminAuth->check()) {
        redirect('/login');
    }
}
