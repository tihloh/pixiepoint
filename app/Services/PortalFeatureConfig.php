<?php

declare(strict_types=1);

namespace PixiePoint\App\Services;

use PDO;

final class PortalFeatureConfig
{
    public const KEYS=['coin_slot','voucher_login','member_login','qr_scan','trial','points','points_convert','points_play','points_share'];
    private const DEFAULTS=['coin_slot'=>true,'voucher_login'=>true,'member_login'=>false,'qr_scan'=>false,'trial'=>false,'points'=>false,'points_convert'=>false,'points_play'=>false,'points_share'=>false];

    public function __construct(private PDO $db){$this->db->exec("CREATE TABLE IF NOT EXISTS portal_feature_settings (scope_type VARCHAR(16) NOT NULL,scope_id BIGINT UNSIGNED NOT NULL,feature_key VARCHAR(64) NOT NULL,feature_value TINYINT(1) NULL,config_json JSON NULL,updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,PRIMARY KEY(scope_type,scope_id,feature_key),INDEX idx_portal_features_scope(scope_type,scope_id)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");}

    public function resolve(int $routerId,?int $stationId=null): array
    {
        $router=$this->read('router',$routerId);$station=$stationId?$this->read('station',$stationId):[];$out=[];
        foreach(self::KEYS as $key){$base=$router[$key]??['value'=>self::DEFAULTS[$key]??false,'config'=>[]];$over=$station[$key]??null;$custom=$over!==null&&$over['value']!==null;$out[$key]=['enabled'=>(bool)($custom?$over['value']:$base['value']),'source'=>$custom?'station':'router','config'=>array_replace($base['config'],$custom?($over['config']??[]):[])];}
        if(!$out['points']['enabled'])foreach(['points_convert','points_play','points_share'] as $key)$out[$key]['enabled']=false;
        return $out;
    }

    public function saveRouter(int $routerId,array $features): void{foreach(self::KEYS as $key)$this->write('router',$routerId,$key,isset($features[$key]),$this->configFor($key,$features));}
    public function saveStation(int $stationId,array $features): void{foreach(self::KEYS as $key){$raw=(string)($features[$key]??'inherit');$value=$raw==='on'?true:($raw==='off'?false:null);$this->write('station',$stationId,$key,$value,$value===null?[]:$this->configFor($key,$features));}}
    public function raw(string $scope,int $id): array{return $this->read($scope,$id);}
    private function read(string $scope,int $id): array{$stmt=$this->db->prepare('SELECT feature_key,feature_value,config_json FROM portal_feature_settings WHERE scope_type=? AND scope_id=?');$stmt->execute([$scope,$id]);$out=[];foreach($stmt->fetchAll() as $row)$out[(string)$row['feature_key']]=['value'=>$row['feature_value']===null?null:(bool)$row['feature_value'],'config'=>is_array($json=json_decode((string)($row['config_json']??''),true))?$json:[]];return $out;}
    private function write(string $scope,int $id,string $key,?bool $value,array $config): void{$stmt=$this->db->prepare('INSERT INTO portal_feature_settings(scope_type,scope_id,feature_key,feature_value,config_json) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE feature_value=VALUES(feature_value),config_json=VALUES(config_json)');$stmt->execute([$scope,$id,$key,$value===null?null:($value?1:0),$config?json_encode($config,JSON_UNESCAPED_SLASHES):null]);}
    private function configFor(string $key,array $features): array{if($key==='trial')return['minutes'=>max(1,min(1440,(int)($features['trial_minutes']??10)))];if($key==='points_convert')return['points'=>max(1,(int)($features['convert_points']??10)),'minutes'=>max(1,(int)($features['convert_minutes']??5))];return[];}
}
