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
        $gateway->dispatcher->listen('coin.inserted',$this);
    }

    public function handle(DeviceEvent $event): void
    {
        if($event->type!=='coin.inserted')return;
        $binding=$this->binding($event->deviceId);$channel=max(1,(int)($event->payload['channel']??1));$pulses=max(0,(int)($event->payload['pulses']??0));
        $stmt=$this->db->prepare('INSERT IGNORE INTO vendo_gateway_coin_events(event_id,gateway_device_id,vendo_id,sequence_no,channel,pulses,occurred_at,received_at) VALUES(?,?,?,?,?,?,?,?)');
        $stmt->execute([$event->eventId,$event->deviceId,$binding['station_id']??null,$event->sequence,$channel,$pulses,$event->occurredAt?->format('Y-m-d H:i:s'),$event->receivedAt->format('Y-m-d H:i:s')]);
    }

    public function registerVendo(string $deviceId,int $routerId,string $name='Vendo',?int $stationId=null): void
    {
        $deviceId=trim($deviceId);$name=trim($name)?:'Vendo';if($deviceId===''||$routerId<1)return;
        $this->db->beginTransaction();
        try{
            $stmt=$this->db->prepare('INSERT INTO vendo_gateway_vendos(gateway_device_id,router_id,station_id,name,created_at,updated_at) VALUES(?,?,NULL,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE router_id=VALUES(router_id),name=VALUES(name),updated_at=UTC_TIMESTAMP()');$stmt->execute([$deviceId,$routerId,$name]);
            if($stationId!==null&&$stationId>0)$this->linkInTransaction($deviceId,$stationId,$routerId);
            $this->db->commit();
        }catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function link(string $deviceId,int $stationId): void
    {
        $binding=$this->binding($deviceId);if(!$binding)throw new \RuntimeException('Vendo not found.');$routerId=(int)$binding['router_id'];
        $this->db->beginTransaction();try{$this->linkInTransaction($deviceId,$stationId,$routerId);$this->db->commit();}catch(\Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
    }

    public function unlink(string $deviceId): void
    {
        $this->db->prepare('UPDATE vendo_gateway_vendos SET station_id=NULL,updated_at=UTC_TIMESTAMP() WHERE gateway_device_id=?')->execute([$deviceId]);
    }

    public function unlinkStation(int $stationId): void
    {
        $this->db->prepare('UPDATE vendo_gateway_vendos SET station_id=NULL,updated_at=UTC_TIMESTAMP() WHERE station_id=?')->execute([$stationId]);
    }

    public function rename(string $deviceId,string $name): void
    {
        $name=trim($name)?:'Vendo';$this->db->prepare('UPDATE vendo_gateway_vendos SET name=?,updated_at=UTC_TIMESTAMP() WHERE gateway_device_id=?')->execute([$name,$deviceId]);
    }

    public function removeInventory(string $deviceId): void
    {
        $this->db->prepare('DELETE FROM vendo_gateway_vendos WHERE gateway_device_id=?')->execute([$deviceId]);
        try{$this->db->prepare('DELETE FROM vendo_gateway_bindings WHERE gateway_device_id=?')->execute([$deviceId]);}catch(\Throwable){}
    }

    public function binding(string $deviceId): ?array
    {
        $stmt=$this->db->prepare('SELECT x.gateway_device_id,x.router_id,x.station_id,x.name vendo_name,v.name station_name FROM vendo_gateway_vendos x LEFT JOIN vendos v ON v.id=x.station_id WHERE x.gateway_device_id=? LIMIT 1');$stmt->execute([$deviceId]);return$stmt->fetch()?:null;
    }

    public function bindings(?int $routerId=null): array
    {
        $sql='SELECT x.gateway_device_id,x.router_id,x.station_id,x.name vendo_name,x.created_at,x.updated_at,v.name station_name FROM vendo_gateway_vendos x LEFT JOIN vendos v ON v.id=x.station_id';$params=[];
        if($routerId){$sql.=' WHERE x.router_id=?';$params[]=$routerId;}$sql.=' ORDER BY (x.station_id IS NULL) DESC,x.name,x.gateway_device_id';$stmt=$this->db->prepare($sql);$stmt->execute($params);return$stmt->fetchAll();
    }

    public function routerIdForStation(int $stationId): int
    {
        $stmt=$this->db->prepare('SELECT router_id FROM vendos WHERE id=? LIMIT 1');$stmt->execute([$stationId]);return(int)$stmt->fetchColumn();
    }

    private function linkInTransaction(string $deviceId,int $stationId,int $routerId): void
    {
        $stmt=$this->db->prepare('SELECT router_id FROM vendos WHERE id=? LIMIT 1 FOR UPDATE');$stmt->execute([$stationId]);$stationRouterId=(int)$stmt->fetchColumn();if($stationRouterId<1||$stationRouterId!==$routerId)throw new \RuntimeException('Station is not available on this gateway.');
        $stmt=$this->db->prepare('SELECT router_id FROM vendo_gateway_vendos WHERE gateway_device_id=? LIMIT 1 FOR UPDATE');$stmt->execute([$deviceId]);if((int)$stmt->fetchColumn()!==$routerId)throw new \RuntimeException('Vendo is not available on this gateway.');
        $this->db->prepare('UPDATE vendo_gateway_vendos SET station_id=NULL,updated_at=UTC_TIMESTAMP() WHERE station_id=? AND gateway_device_id<>?')->execute([$stationId,$deviceId]);
        $this->db->prepare('UPDATE vendo_gateway_vendos SET station_id=?,updated_at=UTC_TIMESTAMP() WHERE gateway_device_id=?')->execute([$stationId,$deviceId]);
    }

    private function migrate(): void
    {
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS vendo_gateway_vendos (
    gateway_device_id VARCHAR(64) PRIMARY KEY,
    router_id BIGINT UNSIGNED NOT NULL,
    station_id BIGINT UNSIGNED NULL,
    name VARCHAR(160) NOT NULL DEFAULT 'Vendo',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uq_vg_vendos_station (station_id),
    INDEX idx_vg_vendos_router (router_id),
    CONSTRAINT fk_vg_vendos_router FOREIGN KEY(router_id) REFERENCES routers(id) ON DELETE CASCADE,
    CONSTRAINT fk_vg_vendos_station FOREIGN KEY(station_id) REFERENCES vendos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        try{$this->db->exec("INSERT IGNORE INTO vendo_gateway_vendos(gateway_device_id,router_id,station_id,name,created_at,updated_at) SELECT b.gateway_device_id,v.router_id,NULL,b.name,b.created_at,b.updated_at FROM vendo_gateway_bindings b JOIN vendos v ON v.id=b.vendo_id");}catch(\Throwable){}
        try{$this->db->exec("UPDATE vendo_gateway_vendos x JOIN vendo_gateway_bindings b ON b.gateway_device_id=x.gateway_device_id JOIN (SELECT vendo_id,MIN(gateway_device_id) keep_device FROM vendo_gateway_bindings GROUP BY vendo_id) k ON k.vendo_id=b.vendo_id AND k.keep_device=b.gateway_device_id SET x.station_id=b.vendo_id WHERE x.station_id IS NULL");}catch(\Throwable){}
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
