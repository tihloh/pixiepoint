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
        $binding=$this->binding($event->deviceId);
        $channel=max(1,(int)($event->payload['channel']??1));
        $pulses=max(0,(int)($event->payload['pulses']??0));
        $credits=max(0,(int)($event->payload['credits']??$pulses));
        $sessionId=strtolower(trim((string)($event->payload['session_id']??'')));
        if(!preg_match('/^[0-9a-f]{32}$/D',$sessionId))$sessionId='';
        $session=$sessionId!==''?$this->session($sessionId,$event->deviceId):null;
        $userId=$session&&!empty($session['user_id'])?(int)$session['user_id']:null;
        $deviceId=$session&&!empty($session['device_id'])?(int)$session['device_id']:null;

        $stmt=$this->db->prepare('INSERT IGNORE INTO vendo_gateway_coin_events(event_id,gateway_device_id,vendo_id,session_id,user_id,device_id,sequence_no,channel,pulses,credits,occurred_at,received_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$event->eventId,$event->deviceId,$binding['station_id']??null,$sessionId?:null,$userId,$deviceId,$event->sequence,$channel,$pulses,$credits,$event->occurredAt?->format('Y-m-d H:i:s'),$event->receivedAt->format('Y-m-d H:i:s')]);
        if($stmt->rowCount()>0&&$session&&$session['status']==='active'){
            $q=$this->db->prepare('UPDATE vendo_gateway_coin_sessions SET coin_count=coin_count+1,credits=credits+?,last_event_id=?,last_sequence_no=?,last_coin_credits=?,last_activity_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE session_id=? AND gateway_device_id=? AND status="active"');
            $q->execute([$credits,$event->eventId,$event->sequence,$credits,$sessionId,$event->deviceId]);
        }
    }

    public function startCoinSession(int $stationId,int $routerId,?int $userId,?int $deviceId): array
    {
        if($stationId<1||$routerId<1||($userId===null&&$deviceId===null))throw new \RuntimeException('A user or device is required for a coin session.');
        $binding=$this->bindingForStation($stationId);
        if(!$binding||(int)$binding['router_id']!==$routerId)throw new \RuntimeException('Vendo Gateway device is not linked to this station.');
        $gatewayDeviceId=(string)$binding['gateway_device_id'];
        $this->expireSessions($gatewayDeviceId);

        $active=$this->activeCoinSession($gatewayDeviceId);
        if($active){
            $sameOwner=$userId!==null?(int)($active['user_id']??0)===$userId:(int)($active['device_id']??0)===$deviceId;
            if(!$sameOwner)throw new \RuntimeException('Coin slot is currently in use.');
            return$active;
        }

        $sessionId=bin2hex(random_bytes(16));
        $maxSeconds=$this->recoveryWindowSeconds($gatewayDeviceId);
        $stmt=$this->db->prepare('INSERT INTO vendo_gateway_coin_sessions(session_id,gateway_device_id,router_id,vendo_id,user_id,device_id,status,coin_count,credits,started_at,last_activity_at,expires_at,created_at,updated_at) VALUES(?,?,?,?,?,?,"active",0,0,UTC_TIMESTAMP(),UTC_TIMESTAMP(),DATE_ADD(UTC_TIMESTAMP(),INTERVAL ? SECOND),UTC_TIMESTAMP(),UTC_TIMESTAMP())');
        $stmt->execute([$sessionId,$gatewayDeviceId,$routerId,$stationId,$userId,$deviceId,$maxSeconds]);
        return$this->session($sessionId,$gatewayDeviceId)??throw new \RuntimeException('Could not create coin session.');
    }

    public function activeCoinSession(string $gatewayDeviceId): ?array
    {
        $this->expireSessions($gatewayDeviceId);
        $stmt=$this->db->prepare('SELECT * FROM vendo_gateway_coin_sessions WHERE gateway_device_id=? AND status="active" AND expires_at>UTC_TIMESTAMP() ORDER BY id DESC LIMIT 1');
        $stmt->execute([$gatewayDeviceId]);
        $row=$stmt->fetch();
        if(!$row)return null;
        return$this->sessionPayload($row);
    }

    public function coinSessionStatus(string $sessionId,?int $userId,?int $deviceId): ?array
    {
        $row=$this->session($sessionId);
        if(!$row||!$this->sameOwner($row,$userId,$deviceId))return null;
        return$this->sessionPayload($row);
    }

    public function finishCoinSession(string $sessionId,?int $userId,?int $deviceId): bool
    {
        $row=$this->session($sessionId);
        if(!$row||!$this->sameOwner($row,$userId,$deviceId))return false;
        $stmt=$this->db->prepare('UPDATE vendo_gateway_coin_sessions SET status="finished",finished_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE session_id=? AND status="active"');
        $stmt->execute([$sessionId]);
        return true;
    }

    public function registerVendo(string $deviceId,int $routerId,string $name='Vendo',?int $stationId=null): void
    {
        $deviceId=trim($deviceId);
        $name=trim($name)?:'Vendo';
        if($deviceId===''||$routerId<1)return;
        $this->db->beginTransaction();
        try{
            $stmt=$this->db->prepare('INSERT INTO vendo_gateway_vendos(gateway_device_id,router_id,station_id,name,created_at,updated_at) VALUES(?,?,NULL,?,UTC_TIMESTAMP(),UTC_TIMESTAMP()) ON DUPLICATE KEY UPDATE router_id=VALUES(router_id),name=VALUES(name),updated_at=UTC_TIMESTAMP()');
            $stmt->execute([$deviceId,$routerId,$name]);
            if($stationId!==null&&$stationId>0)$this->linkInTransaction($deviceId,$stationId,$routerId);
            $this->db->commit();
        }
        catch(\Throwable $e){
            if($this->db->inTransaction())$this->db->rollBack();
            throw $e;
        }
    }

    public function link(string $deviceId,int $stationId): void
    {
        $binding=$this->binding($deviceId);
        if(!$binding)throw new \RuntimeException('Vendo not found.');
        $routerId=(int)$binding['router_id'];
        $this->db->beginTransaction();
        try{
            $this->linkInTransaction($deviceId,$stationId,$routerId);
            $this->db->commit();
        }
        catch(\Throwable $e){
            if($this->db->inTransaction())$this->db->rollBack();
            throw $e;
        }
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
        $name=trim($name)?:'Vendo';
        $this->db->prepare('UPDATE vendo_gateway_vendos SET name=?,updated_at=UTC_TIMESTAMP() WHERE gateway_device_id=?')->execute([$name,$deviceId]);
    }

    public function removeInventory(string $deviceId): void
    {
        $this->db->prepare('DELETE FROM vendo_gateway_vendos WHERE gateway_device_id=?')->execute([$deviceId]);
        try{
            $this->db->prepare('DELETE FROM vendo_gateway_bindings WHERE gateway_device_id=?')->execute([$deviceId]);
        }
        catch(\Throwable){
        }
    }

    public function binding(string $deviceId): ?array
    {
        $stmt=$this->db->prepare('SELECT x.gateway_device_id,x.router_id,x.station_id,x.name vendo_name,v.name station_name FROM vendo_gateway_vendos x LEFT JOIN vendos v ON v.id=x.station_id WHERE x.gateway_device_id=? LIMIT 1');
        $stmt->execute([$deviceId]);
        return$stmt->fetch()?:null;
    }

    public function bindings(?int $routerId=null): array
    {
        $sql='SELECT x.gateway_device_id,x.router_id,x.station_id,x.name vendo_name,x.created_at,x.updated_at,v.name station_name FROM vendo_gateway_vendos x LEFT JOIN vendos v ON v.id=x.station_id';
        $params=[];
        if($routerId){
            $sql.=' WHERE x.router_id=?';
            $params[]=$routerId;
        }
        $sql.=' ORDER BY (x.station_id IS NULL) DESC,x.name,x.gateway_device_id';
        $stmt=$this->db->prepare($sql);
        $stmt->execute($params);
        return$stmt->fetchAll();
    }

    public function routerIdForStation(int $stationId): int
    {
        $stmt=$this->db->prepare('SELECT router_id FROM vendos WHERE id=? LIMIT 1');
        $stmt->execute([$stationId]);
        return(int)$stmt->fetchColumn();
    }

    private function bindingForStation(int $stationId): ?array
    {
        $stmt=$this->db->prepare('SELECT gateway_device_id,router_id,station_id,name vendo_name FROM vendo_gateway_vendos WHERE station_id=? LIMIT 1');
        $stmt->execute([$stationId]);
        return$stmt->fetch()?:null;
    }

    private function session(string $sessionId,?string $gatewayDeviceId=null): ?array
    {
        if(!preg_match('/^[0-9a-f]{32}$/D',$sessionId))return null;
        $sql='SELECT * FROM vendo_gateway_coin_sessions WHERE session_id=?';
        $params=[$sessionId];
        if($gatewayDeviceId!==null){$sql.=' AND gateway_device_id=?';$params[]=$gatewayDeviceId;}
        $sql.=' LIMIT 1';
        $stmt=$this->db->prepare($sql);
        $stmt->execute($params);
        return$stmt->fetch()?:null;
    }

    private function sameOwner(array $row,?int $userId,?int $deviceId): bool
    {
        if($userId!==null&&!empty($row['user_id']))return(int)$row['user_id']===$userId;
        return$deviceId!==null&&!empty($row['device_id'])&&(int)$row['device_id']===$deviceId;
    }

    private function sessionPayload(array $row): array
    {
        return[
            'active'=>$row['status']==='active',
            'session_id'=>(string)$row['session_id'],
            'coin_count'=>(int)$row['coin_count'],
            'credits'=>(int)$row['credits'],
            'last_event_id'=>(string)($row['last_event_id']??''),
            'last_sequence'=>(string)($row['last_sequence_no']??''),
            'last_coin_credits'=>(int)($row['last_coin_credits']??0),
            'user_id'=>!empty($row['user_id'])?(int)$row['user_id']:null,
            'device_id'=>!empty($row['device_id'])?(int)$row['device_id']:null,
            'vendo_id'=>(int)$row['vendo_id'],
            'gateway_device_id'=>(string)$row['gateway_device_id']
        ];
    }

    private function expireSessions(string $gatewayDeviceId): void
    {
        $stmt=$this->db->prepare('UPDATE vendo_gateway_coin_sessions SET status="expired",finished_at=UTC_TIMESTAMP(),updated_at=UTC_TIMESTAMP() WHERE gateway_device_id=? AND status="active" AND expires_at<=UTC_TIMESTAMP()');
        $stmt->execute([$gatewayDeviceId]);
    }

    private function recoveryWindowSeconds(string $gatewayDeviceId): int
    {
        try{
            $stmt=$this->db->prepare('SELECT reported_state_json FROM vg_devices WHERE device_id=? LIMIT 1');
            $stmt->execute([$gatewayDeviceId]);
            $state=json_decode((string)$stmt->fetchColumn(),true);
            $ms=(int)($state['coin']['session_max_ms']??300000);
            return max(30,min(3600,(int)ceil($ms/1000)));
        }
        catch(\Throwable){
            return 300;
        }
    }

    private function linkInTransaction(string $deviceId,int $stationId,int $routerId): void
    {
        $stmt=$this->db->prepare('SELECT router_id FROM vendos WHERE id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$stationId]);
        $stationRouterId=(int)$stmt->fetchColumn();
        if($stationRouterId<1||$stationRouterId!==$routerId)throw new \RuntimeException('Station is not available on this gateway.');
        $stmt=$this->db->prepare('SELECT router_id FROM vendo_gateway_vendos WHERE gateway_device_id=? LIMIT 1 FOR UPDATE');
        $stmt->execute([$deviceId]);
        if((int)$stmt->fetchColumn()!==$routerId)throw new \RuntimeException('Vendo is not available on this gateway.');
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
        try{
            $this->db->exec("INSERT IGNORE INTO vendo_gateway_vendos(gateway_device_id,router_id,station_id,name,created_at,updated_at) SELECT b.gateway_device_id,v.router_id,NULL,b.name,b.created_at,b.updated_at FROM vendo_gateway_bindings b JOIN vendos v ON v.id=b.vendo_id");
        }
        catch(\Throwable){
        }
        try{
            $this->db->exec("UPDATE vendo_gateway_vendos x JOIN vendo_gateway_bindings b ON b.gateway_device_id=x.gateway_device_id JOIN (SELECT vendo_id,MIN(gateway_device_id) keep_device FROM vendo_gateway_bindings GROUP BY vendo_id) k ON k.vendo_id=b.vendo_id AND k.keep_device=b.gateway_device_id SET x.station_id=b.vendo_id WHERE x.station_id IS NULL");
        }
        catch(\Throwable){
        }
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS vendo_gateway_coin_sessions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    session_id CHAR(32) NOT NULL UNIQUE,
    gateway_device_id VARCHAR(64) NOT NULL,
    router_id BIGINT UNSIGNED NOT NULL,
    vendo_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    device_id BIGINT UNSIGNED NULL,
    status VARCHAR(16) NOT NULL DEFAULT 'active',
    coin_count INT UNSIGNED NOT NULL DEFAULT 0,
    credits BIGINT UNSIGNED NOT NULL DEFAULT 0,
    last_event_id VARCHAR(128) NULL,
    last_sequence_no BIGINT UNSIGNED NULL,
    last_coin_credits INT UNSIGNED NOT NULL DEFAULT 0,
    started_at DATETIME NOT NULL,
    last_activity_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    finished_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_vg_coin_session_device (gateway_device_id,status,expires_at),
    INDEX idx_vg_coin_session_owner_user (user_id,status),
    INDEX idx_vg_coin_session_owner_device (device_id,status),
    FOREIGN KEY(vendo_id) REFERENCES vendos(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        $this->db->exec(<<<'SQL'
CREATE TABLE IF NOT EXISTS vendo_gateway_coin_events (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    event_id VARCHAR(128) NOT NULL UNIQUE,
    gateway_device_id VARCHAR(64) NOT NULL,
    vendo_id BIGINT UNSIGNED NULL,
    session_id CHAR(32) NULL,
    user_id BIGINT UNSIGNED NULL,
    device_id BIGINT UNSIGNED NULL,
    sequence_no BIGINT UNSIGNED NOT NULL,
    channel INT UNSIGNED NOT NULL DEFAULT 1,
    pulses INT UNSIGNED NOT NULL DEFAULT 0,
    credits INT UNSIGNED NOT NULL DEFAULT 0,
    occurred_at DATETIME NULL,
    received_at DATETIME NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_vg_coin_device_seq (gateway_device_id,sequence_no),
    INDEX idx_vg_coin_vendo_time (vendo_id,received_at),
    INDEX idx_vg_coin_session (session_id),
    FOREIGN KEY(vendo_id) REFERENCES vendos(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
SQL);
        foreach([
            'ADD COLUMN IF NOT EXISTS session_id CHAR(32) NULL AFTER vendo_id',
            'ADD COLUMN IF NOT EXISTS user_id BIGINT UNSIGNED NULL AFTER session_id',
            'ADD COLUMN IF NOT EXISTS device_id BIGINT UNSIGNED NULL AFTER user_id',
            'ADD COLUMN IF NOT EXISTS credits INT UNSIGNED NOT NULL DEFAULT 0 AFTER pulses'
        ] as $alter){
            try{$this->db->exec('ALTER TABLE vendo_gateway_coin_events '.$alter);}
            catch(\Throwable){}
        }
    }
}
