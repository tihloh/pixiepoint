<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

use PDO;
use Tihloh\VendoGateway\Event\DeviceEvent;
use Tihloh\VendoGateway\Event\EventHandler;
use Tihloh\VendoGateway\Gateway;

final class VendoGatewayBridge implements EventHandler
{
    public function __construct(private PDO $db)
    {
        $this->migrate();
    }

    public function register(Gateway $gateway): void
    {
        $gateway->dispatcher->listen('coin.inserted', $this);
    }

    public function handle(DeviceEvent $event): void
    {
        if($event->type !== 'coin.inserted')return;
        $binding=$this->binding($event->deviceId);
        $channel=max(1,(int)($event->payload['channel']??1));
        $pulses=max(0,(int)($event->payload['pulses']??0));
        $stmt=$this->db->prepare('INSERT IGNORE INTO vendo_gateway_coin_events(event_id,gateway_device_id,vendo_id,sequence_no,channel,pulses,occurred_at,received_at) VALUES(?,?,?,?,?,?,?,?)');
        $stmt->execute([$event->eventId,$event->deviceId,$binding['vendo_id']??null,$event->sequence,$channel,$pulses,$event->occurredAt?->format('Y-m-d H:i:s'),$event->receivedAt->format('Y-m-d H:i:s')]);
    }

    public function bind(string $deviceId,int $vendoId): void
    {
        $stmt=$this->db->prepare('INSERT INTO vendo_gateway_bindings(gateway_device_id,vendo_id,created_at,updated_at) VALUES(?,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE vendo_id=VALUES(vendo_id),updated_at=UTC_TIMESTAMP()');
        $stmt->execute([$deviceId,$vendoId]);
    }

    public function unbind(string $deviceId): void
    {
        $this->db->prepare('DELETE FROM vendo_gateway_bindings WHERE gateway_device_id=?')->execute([$deviceId]);
    }

    public function binding(string $deviceId): ?array
    {
        $stmt=$this->db->prepare('SELECT b.gateway_device_id,b.vendo_id,v.name vendo_name,v.router_id FROM vendo_gateway_bindings b LEFT JOIN vendos v ON v.id=b.vendo_id WHERE b.gateway_device_id=? LIMIT 1');
        $stmt->execute([$deviceId]);
        return $stmt->fetch()?:null;
    }

    public function bindings(?int $routerId=null): array
    {
        $sql='SELECT b.gateway_device_id,b.vendo_id,b.created_at,b.updated_at,v.name vendo_name,v.router_id FROM vendo_gateway_bindings b LEFT JOIN vendos v ON v.id=b.vendo_id';
        $params=[];
        if($routerId){$sql.=' WHERE v.router_id=?';$params[]=$routerId;}
        $sql.=' ORDER BY v.name,b.gateway_device_id';
        $stmt=$this->db->prepare($sql);$stmt->execute($params);return $stmt->fetchAll();
    }

    private function migrate(): void
    {
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS vendo_gateway_bindings (
    gateway_device_id VARCHAR(64) PRIMARY KEY,
    vendo_id BIGINT UNSIGNED NOT NULL UNIQUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vg_bindings_vendo (vendo_id),
    FOREIGN KEY(vendo_id) REFERENCES vendos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS vendo_gateway_coin_events (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    event_id VARCHAR(128) NOT NULL UNIQUE,
    gateway_device_id VARCHAR(64) NOT NULL,
    vendo_id BIGINT UNSIGNED NULL,
    sequence_no BIGINT UNSIGNED NOT NULL,
    channel INT UNSIGNED NOT NULL DEFAULT 1,
    pulses INT UNSIGNED NOT NULL DEFAULT 0,
    occurred_at DATETIME NULL,
    received_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vg_coin_device_seq (gateway_device_id,sequence_no),
    INDEX idx_vg_coin_vendo_time (vendo_id,received_at),
    FOREIGN KEY(vendo_id) REFERENCES vendos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
    }
}
