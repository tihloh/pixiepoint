<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\VendoGateway;

use PDO;
use PixiePoint\App\Admin\Shared\FeatureController;
use PixiePoint\App\Admin\Shared\RouterAccess;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\VendoGatewayBridge;
use PixiePoint\App\Services\View;
use Tihloh\Prefab\Logs\Services\LogManager;
use Tihloh\VendoGateway\Database\Migrator;
use Tihloh\VendoGateway\Gateway;
use Tihloh\VendoGateway\GatewayFactory;

final class Controller extends FeatureController
{
    private ?Gateway $gateway=null;
    private VendoGatewayBridge $bridge;

    public function __construct(PDO $db,AuthContext $auth,View $view,LogManager $logs,private array $config)
    {
        parent::__construct($db,$auth,$view,$logs);
        (new Migrator($db))->migrate();
        $this->bridge=new VendoGatewayBridge($db);
        $key=trim((string)($config['vendo_gateway_master_key']??''));
        if(strlen($key)>=32)$this->gateway=GatewayFactory::pdo($db,$key);
    }

    public function index(): never
    {
        $user=$this->auth->requireAccount();
        $userId=(int)$user['id'];
        $platformOwner=$this->auth->isPlatformOwner();
        $access=new RouterAccess($this->db);
        $routerId=max(0,(int)($_SESSION['pixiepoint_selected_router_id']??0));
        if($routerId<1)redirect('/admin/routers');
        if(!$access->canView($routerId,$userId,$platformOwner)){
            unset($_SESSION['pixiepoint_selected_router_id']);
            redirect('/admin/routers');
        }
        $this->cleanupPairings();
        if(!$this->isPost()&&(string)($_GET['pairing_status']??'')==='1'){
            $pairings=$this->pendingPairings($routerId);
            $this->json(['ok'=>true,'pairings'=>array_map(static fn(array $row):array=>['id'=>(string)$row['pairing_id'],'expires_at'=>(int)$row['expires_at_epoch']],$pairings),'server_time'=>time()]);
        }
        if($this->isPost()){
            $result=$this->handlePost($access,$userId,$platformOwner,$routerId);
            if($this->wantsJson())$this->json($result,$result['ok']?200:422);
            $return=trim((string)($_POST['return_to']??''));
            if($return==='/admin/routers'||preg_match('~^/admin/routers/\d+(?:#stations)?$~',$return))redirect($return);
            $device=trim((string)($_POST['device_id']??''));
            redirect('/admin/vendo-gateway'.($device!==''&&($result['action']??'')!=='delete'?'?device='.rawurlencode($device):''));
        }
        $stmt=$this->db->prepare('SELECT id,name FROM vendos WHERE router_id=? AND enabled=1 ORDER BY name');
        $stmt->execute([$routerId]);
        $stations=$stmt->fetchAll();
        $rows=[];
        $selectedDevice=trim((string)($_GET['device']??''));
        foreach($this->bridge->bindings($routerId) as $binding){
            $deviceId=(string)$binding['gateway_device_id'];
            $device=$this->gateway?->devices->get($deviceId);
            $config=[];
            $reported=[];
            $firmwareStatus=null;
            if($this->gateway&&$device){
                try{
                    $resolved=$this->gateway->configs->resolve($deviceId);
                    $config=is_array($resolved['config']??null)?$resolved['config']:[];
                    $config['firmware']=$this->gateway->firmware->settings($deviceId);
                }
                catch(\Throwable){
                }
                try{
                    $firmwareStatus=$this->gateway->firmware->deviceStatus($deviceId);
                }
                catch(\Throwable){
                }
            }
            $q=$this->db->prepare('SELECT reported_state_json,last_ip FROM vg_devices WHERE device_id=? LIMIT 1');
            $q->execute([$deviceId]);
            $raw=$q->fetch();
            if($raw){
                $reported=json_decode((string)($raw['reported_state_json']??''),true)?:[];
                $reported['ip']=$raw['last_ip']??null;
            }
            if(!$config&&$reported)$config=['hardware'=>$reported['hardware']??[],'coin'=>$reported['coin']??[]];
            $rows[]=['binding'=>$binding,'device'=>$device,'config'=>$config,'reported'=>$reported,'firmware'=>$firmwareStatus,'selected'=>$selectedDevice!==''&&hash_equals($selectedDevice,$deviceId)];
        }
        $pairings=$this->pendingPairings($routerId);
        $flash=(string)($_SESSION['admin_flash']??'');
        unset($_SESSION['admin_flash']);
        $this->page('Vendos',__DIR__.'/views/index.php',['flash'=>$flash,'routerId'=>$routerId,'stations'=>$stations,'rows'=>$rows,'pairings'=>$pairings,'selectedDevice'=>$selectedDevice,'gatewayConfigured'=>(bool)$this->gateway,'canManage'=>$access->canManage($routerId,$userId,$platformOwner)]);
    }

    private function handlePost(RouterAccess $access,int $userId,bool $platformOwner,int $routerId): array
    {
        require_csrf();
        $action=(string)($_POST['action']??'');
        try{
            if(!$this->gateway)throw new \RuntimeException('Vendo Gateway is not configured.');
            if(!$access->canManage($routerId,$userId,$platformOwner))throw new \RuntimeException('You do not have access to manage this gateway.');
            if($action==='setup_code'){
                $vendoName=trim((string)($_POST['vendo_name']??''))?:'Vendo';
                if(strlen($vendoName)>160)throw new \RuntimeException('Vendo name is too long.');
                $result=$this->gateway->pairings->prepare((string)$userId,['router_id'=>$routerId,'vendo_name'=>$vendoName]);
                $code=(string)($result['setup_code']??'');
                if($code==='')throw new \RuntimeException('Setup code was not generated.');
                $this->audit('vendo_gateway.setup_code.created','vendo_gateway_enrollment',(string)($result['enrollment_id']??''),'Vendo setup code generated.',['router_id'=>$routerId,'vendo_name'=>$vendoName]);
                $_SESSION['admin_flash']='<div class="alert alert-success">Setup code created. Waiting for the ESP to enroll.</div>';
                return['ok'=>true,'message'=>'Setup code created. Waiting for the ESP to enroll.','action'=>$action,'setup_code'=>$code,'enrollment_id'=>(string)($result['enrollment_id']??''),'expires_in'=>(int)($result['expires_in']??600),'reload'=>true];
            }
            if($action==='link_station'){
                $stationId=max(0,(int)($_POST['station_id']??0));
                if($stationId<1||$this->bridge->routerIdForStation($stationId)!==$routerId)throw new \RuntimeException('Station not found on this gateway.');
                $deviceId=trim((string)($_POST['device_id']??''));
                if($deviceId===''){
                    $this->bridge->unlinkStation($stationId);
                    $message='Station Vendo link removed.';
                }
                else{
                    $binding=$this->bridge->binding($deviceId);
                    if(!$binding||(int)$binding['router_id']!==$routerId)throw new \RuntimeException('Vendo not found on this gateway.');
                    $this->bridge->link($deviceId,$stationId);
                    $message='Vendo linked to station.';
                }
                $this->audit('vendo_gateway.device.linked','station',$stationId,$message,['router_id'=>$routerId,'gateway_device_id'=>$deviceId?:null]);
                $_SESSION['admin_flash']='<div class="alert ok">'.e($message).'</div>';
                return['ok'=>true,'message'=>$message,'action'=>$action,'device_id'=>$deviceId,'reload'=>true];
            }
            $deviceId=trim((string)($_POST['device_id']??''));
            if($deviceId==='')throw new \RuntimeException('Device is required.');
            $binding=$this->bridge->binding($deviceId);
            if(!$binding||(int)($binding['router_id']??0)!==$routerId)throw new \RuntimeException('Vendo is not registered on the selected gateway.');
            if($action==='delete'){
                $this->db->beginTransaction();
                try{
                    $this->bridge->removeInventory($deviceId);
                    $stmt=$this->db->prepare('DELETE FROM vg_devices WHERE device_id=?');
                    $stmt->execute([$deviceId]);
                    $this->db->commit();
                }
                catch(\Throwable $e){
                    if($this->db->inTransaction())$this->db->rollBack();
                    throw$e;
                }
                $this->audit('vendo_gateway.device.deleted','vendo_gateway_device',$deviceId,'Vendo device was removed from PixiePoint.',['router_id'=>$routerId,'station_id'=>$binding['station_id']??null]);
                $_SESSION['admin_flash']='<div class="alert ok">Vendo removed. The ESP must be enrolled again before it can return.</div>';
                return['ok'=>true,'message'=>'Vendo removed. Re-enrollment is required.','action'=>$action,'device_id'=>$deviceId,'reload'=>true];
            }
            if($action==='unlink'){
                $this->bridge->unlink($deviceId);
                $message='Vendo unlinked from station.';
                $this->audit('vendo_gateway.device.unlinked','vendo_gateway_device',$deviceId,$message,['router_id'=>$routerId,'station_id'=>$binding['station_id']??null]);
                return['ok'=>true,'message'=>$message,'action'=>$action,'device_id'=>$deviceId,'reload'=>true];
            }
            if($action==='rename'){
                $name=trim((string)($_POST['vendo_name']??''));
                $this->bridge->rename($deviceId,$name);
                return['ok'=>true,'message'=>'Vendo renamed.','action'=>$action,'device_id'=>$deviceId,'vendo_name'=>$name?:'Vendo'];
            }
            if($action==='save_config'){
                $coin=(int)($_POST['coin_pin']??0);
                $relay=(int)($_POST['relay_pin']??0);
                $settle=(int)($_POST['coin_settle_ms']??350);
                $platform=$this->devicePlatform($deviceId);
                if($platform===null)throw new \RuntimeException('Device firmware target is unknown. Wait for the device to report its platform before changing GPIO configuration.');
                $coinPins=$platform==='esp32'?[4,13,14,16,17,18,19,21,22,23,25,26,27,32,33]:[4,5,12,13,14];
                $outputPins=$platform==='esp32'?[4,13,14,16,17,18,19,21,22,23,25,26,27,32,33]:[4,5,12,13,14,16];
                if(!in_array($coin,$coinPins,true))throw new \RuntimeException('Selected coin GPIO is not supported by '.$platform.'.');
                if(!in_array($relay,$outputPins,true))throw new \RuntimeException('Selected relay GPIO is not supported by '.$platform.'.');
                if($coin===$relay)throw new \RuntimeException('Coin and relay GPIOs must be different.');
                if($settle<50||$settle>2000)throw new \RuntimeException('Coin settle time must be between 50 and 2000 ms.');
                $resolved=$this->gateway->configs->resolve($deviceId);
                $config=$resolved['config']??[];
                $config['hardware']['pins']['coin']=$coin;
                $config['hardware']['pins']['relay']=$relay;
                unset($config['hardware']['pins']['status_led']);
                $config['coin']['settle_ms']=$settle;
                $this->gateway->configs->setDeviceConfig($deviceId,$config);
                $this->gateway->commands->queue($deviceId,'config.refresh');
                $this->audit('vendo_gateway.device.config','vendo_gateway_device',$deviceId,'Vendo hardware configuration updated.',['router_id'=>$routerId,'station_id'=>$binding['station_id']??null,'platform'=>$platform]);
                return['ok'=>true,'message'=>'Hardware configuration saved and queued for sync.','action'=>$action,'device_id'=>$deviceId];
            }
            if($action==='save_firmware'){
                $settings=$this->gateway->firmware->saveSettings($deviceId,['auto_check'=>isset($_POST['auto_check']),'auto_update'=>isset($_POST['auto_update']),'check_interval_hours'=>(int)($_POST['check_interval_hours']??2),'channel'=>(string)($_POST['channel']??'stable')]);
                $this->audit('vendo_gateway.device.firmware_config','vendo_gateway_device',$deviceId,'Vendo firmware policy updated.',['router_id'=>$routerId,'station_id'=>$binding['station_id']??null]+$settings);
                return['ok'=>true,'message'=>'Firmware settings saved and queued for sync.','action'=>$action,'device_id'=>$deviceId,'firmware_settings'=>$settings];
            }
            if($action==='activate'){
                $this->gateway->devices->activate($deviceId);
                $message='Vendo activated.';
            }
            elseif($action==='suspend'){
                $this->gateway->devices->suspend($deviceId);
                $message='Vendo suspended.';
            }
            elseif($action==='coin_enable'){
                $this->gateway->commands->queue($deviceId,'coin.enable');
                $message='Coin input enable queued.';
            }
            elseif($action==='coin_disable'){
                $this->gateway->commands->queue($deviceId,'coin.disable');
                $message='Coin input disable queued.';
            }
            elseif($action==='restart'){
                $this->gateway->commands->queue($deviceId,'system.restart');
                $message='Restart queued.';
            }
            elseif($action==='firmware_check'){
                $firmwareStatus=$this->gateway->firmware->requestCheck($deviceId);
                $message='Release check completed by Vendo Gateway.';
            }
            elseif($action==='firmware_update'){
                $this->gateway->firmware->requestUpdate($deviceId);
                $message='Firmware update queued. The ESP will install it when safe.';
            }
            else throw new \RuntimeException('Unknown action.');
            $this->audit('vendo_gateway.device.'.$action,'vendo_gateway_device',$deviceId,'Vendo device action executed.',['router_id'=>$routerId,'station_id'=>$binding['station_id']??null]);
            return['ok'=>true,'message'=>$message,'action'=>$action,'device_id'=>$deviceId,'firmware'=>$firmwareStatus??null];
        }
        catch(\Throwable $e){
            return['ok'=>false,'message'=>$e->getMessage(),'action'=>$action];
        }
    }

    private function cleanupPairings(): void
    {
        if(!$this->gateway)return;
        if(method_exists($this->gateway->pairings,'cleanupExpired')){
            $this->gateway->pairings->cleanupExpired();
            return;
        }
        $this->db->exec("DELETE FROM vg_pairings WHERE status='pending' AND expires_at<=UTC_TIMESTAMP()");
    }

    private function pendingPairings(int $routerId): array
    {
        if($this->gateway&&method_exists($this->gateway->pairings,'pending')){
            $out=[];
            foreach($this->gateway->pairings->pending(['router_id'=>$routerId]) as $row){
                $context=is_array($row['context']??null)?$row['context']:[];
                $out[]=['pairing_id'=>(string)($row['enrollment_id']??''),'pairing_code'=>(string)($row['setup_code']??''),'vendo_name'=>trim((string)($context['vendo_name']??''))?:'Vendo','expires_at_epoch'=>(int)($row['expires_at']??time())];
            }
            return$out;
        }
        $rows=$this->db->query("SELECT pairing_id,pairing_code,claimed_context_json,expires_at,created_at FROM vg_pairings WHERE status='pending' AND expires_at>UTC_TIMESTAMP() ORDER BY created_at DESC")->fetchAll();
        $out=[];
        foreach($rows as $row){
            $context=json_decode((string)($row['claimed_context_json']??''),true)?:[];
            if((int)($context['router_id']??0)!==$routerId)continue;
            $row['vendo_name']=trim((string)($context['vendo_name']??''))?:'Vendo';
            $row['expires_at_epoch']=strtotime((string)$row['expires_at'].' UTC')?:time();
            $out[]=$row;
        }
        return$out;
    }

    private function devicePlatform(string $deviceId): ?string
    {
        $capabilities=$this->gateway?->devices->capabilities($deviceId)??[];
        $platform=strtolower((string)($capabilities['platform']??''));
        if(!in_array($platform,['esp32','esp8266'],true)){
            $q=$this->db->prepare('SELECT reported_state_json FROM vg_devices WHERE device_id=? LIMIT 1');
            $q->execute([$deviceId]);
            $reported=json_decode((string)$q->fetchColumn(),true);
            $platform=strtolower((string)($reported['hardware']['platform']??$reported['platform']??''));
        }
        if(!in_array($platform,['esp32','esp8266'],true)){
            $status=$this->gateway?->firmware->deviceStatus($deviceId)??[];
            $platform=strtolower((string)($status['target']??''));
        }
        return in_array($platform,['esp32','esp8266'],true)?$platform:null;
    }

    private function wantsJson(): bool{
        return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest'||str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json');
    }
    private function json(array $payload,int $status=200): never{
        unset($_SESSION['admin_flash']);
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($payload,JSON_UNESCAPED_SLASHES);
        exit;
    }
}
