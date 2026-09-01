<?php

declare(strict_types=1);

final class App
{
    public readonly PDO $db;
    public readonly array $config;

    public function __construct(string $root)
    {
        $defaults = require $root . '/config.example.php';
        $local = is_file($root . '/config.php') ? require $root . '/config.php' : [];
        $this->config = array_replace($defaults, is_array($local) ? $local : []);
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
    password_hash VARCHAR(255) NULL,
    google_sub VARCHAR(255) NULL UNIQUE,
    avatar_url VARCHAR(1000) NULL,
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
    uuid CHAR(36) NULL UNIQUE,
    user_id BIGINT UNSIGNED NULL,
    mac CHAR(17) NULL UNIQUE,
    last_ip VARCHAR(45),
    user_agent VARCHAR(500),
    merged_into_device_id BIGINT UNSIGNED NULL,
    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_devices_user (user_id),
    INDEX idx_devices_merged (merged_into_device_id),
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS device_identities (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    device_id BIGINT UNSIGNED NOT NULL,
    identity_type VARCHAR(32) NOT NULL,
    identity_value VARCHAR(255) NOT NULL,
    scope_key VARCHAR(255) NOT NULL DEFAULT 'global',
    confidence TINYINT UNSIGNED NOT NULL DEFAULT 100,
    first_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    last_seen_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_device_identity (identity_type,identity_value,scope_key),
    INDEX idx_device_identities_device (device_id),
    INDEX idx_device_identities_lookup (identity_type,identity_value),
    FOREIGN KEY(device_id) REFERENCES devices(id) ON DELETE CASCADE
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

CREATE TABLE IF NOT EXISTS router_login_events (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    event_key VARCHAR(191) NOT NULL UNIQUE,
    router_id BIGINT UNSIGNED NOT NULL,
    device_id BIGINT UNSIGNED NULL,
    user_id BIGINT UNSIGNED NULL,
    voucher_id BIGINT UNSIGNED NULL,
    username VARCHAR(128) NOT NULL,
    mac CHAR(17) NULL,
    client_ip VARCHAR(45) NULL,
    interface_name VARCHAR(128) NULL,
    device_name VARCHAR(255) NULL,
    vendo_name VARCHAR(255) NULL,
    amount_pesos INT UNSIGNED NOT NULL DEFAULT 0,
    duration_seconds BIGINT UNSIGNED NOT NULL DEFAULT 0,
    is_extension TINYINT(1) NOT NULL DEFAULT 0,
    points_earned BIGINT UNSIGNED NOT NULL DEFAULT 0,
    points_awarded BIGINT UNSIGNED NOT NULL DEFAULT 0,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_login_events_created (created_at),
    INDEX idx_login_events_vendo (vendo_name),
    FOREIGN KEY(router_id) REFERENCES routers(id),
    FOREIGN KEY(device_id) REFERENCES devices(id) ON DELETE SET NULL,
    FOREIGN KEY(user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY(voucher_id) REFERENCES vouchers(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
SQL);

        // Upgrade existing PixiePoint databases without removing guest or legacy data.
        $this->db->exec("ALTER TABLE users MODIFY COLUMN password_hash VARCHAR(255) NULL");
        $this->db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS google_sub VARCHAR(255) NULL AFTER password_hash");
        $this->db->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(1000) NULL AFTER google_sub");
        $this->db->exec("ALTER TABLE devices ADD COLUMN IF NOT EXISTS uuid CHAR(36) NULL AFTER id");
        $this->db->exec("ALTER TABLE devices ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL AFTER uuid");
        $this->db->exec("ALTER TABLE devices ADD COLUMN IF NOT EXISTS merged_into_device_id BIGINT UNSIGNED NULL AFTER user_agent");
        $this->db->exec("ALTER TABLE devices MODIFY COLUMN mac CHAR(17) NULL");
        $this->db->exec("ALTER TABLE sessions ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL AFTER id");
        $this->db->exec("ALTER TABLE router_login_events ADD COLUMN IF NOT EXISTS points_earned BIGINT UNSIGNED NOT NULL DEFAULT 0 AFTER is_extension");
        $this->db->exec("UPDATE devices SET uuid=UUID() WHERE uuid IS NULL OR uuid=''");
        $this->db->exec("INSERT IGNORE INTO device_identities(device_id,identity_type,identity_value,scope_key,confidence,first_seen_at,last_seen_at) SELECT id,'mac',mac,'legacy',100,first_seen_at,last_seen_at FROM devices WHERE mac IS NOT NULL AND mac<>''");
        $this->db->exec("UPDATE router_login_events SET points_earned=points_awarded WHERE points_earned=0 AND points_awarded>0");
        $this->db->exec("UPDATE router_login_events SET points_awarded=0 WHERE user_id IS NULL AND points_awarded>0");

        try {
            $this->db->exec("CREATE UNIQUE INDEX idx_users_google_sub ON users (google_sub)");
        } catch (PDOException) {
            // Index already exists on upgraded installations.
        }
        try {
            $this->db->exec("CREATE UNIQUE INDEX idx_devices_uuid ON devices (uuid)");
        } catch (PDOException) {
            // Index already exists on upgraded installations.
        }
        try {
            $this->db->exec("CREATE INDEX idx_devices_merged ON devices (merged_into_device_id)");
        } catch (PDOException) {
            // Index already exists on upgraded installations.
        }

        // Carry forward an existing first-install administrator as the platform owner.
        $this->db->exec("INSERT IGNORE INTO users(name,email,password_hash,platform_role,created_at) SELECT name,email,password_hash,'platform_owner',created_at FROM admins");

        // Legacy groups/permissions tables, if present on an upgraded database,
        // are intentionally not dropped. New authorization storage is owned by
        // Prefab Users + Prefab Permissions.
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
