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
        parent::__construct($db,$auth,$view,$logs);(new Migrator($db))->migrate();$this->bridge=new VendoGatewayBridge($db);$key=trim((string)($config['vendo_gateway_master_key']??''));if(strlen($key)>=32)$this->gateway=GatewayFactory::pdo($db,$key);
    }

    public function index(): never
    {
        $user=$this->auth->requireAccount();$userId=(int)$user['id'];$platformOwner=$this->auth->isPlatformOwner();$access=new RouterAccess($this->db);$routerId=max(0,(int)($_SESSION['pixiepoint_selected_router_id']??0));if($routerId<1)redirect('/admin/routers');if(!$access->canView($routerId,$userId,$platformOwner)){unset($_SESSION['pixiepoint_selected_router_id']);redirect('/admin/routers');}
        if($this->isPost()){$result=$this->handlePost($access,$userId,$platformOwner,$routerId);if($this->wantsJson())$this->json($result,$result['ok']?200:422);$return=trim((string)($_POST['return_to']??''));if($return==='/admin/routers'||preg_match('~^/admin/routers/\d+(?:#stations)?$~',$return))redirect($return);$device=trim((string)($_POST['device_id']??''));redirect('/admin/vendo-gateway'.($device!==''?'?device='.rawurlencode($device):''));}
        $stmt=$this->db->prepare('SELECT id,name FROM vendos WHERE router_id=? AND enabled=1 ORDER BY name');$stmt->execute([$routerId]);$stations=$stmt->fetchAll();$rows=[];$selectedDevice=trim((string)($_GET['device']??''));
        foreach($this->bridge->bindings($routerId) as $binding){$deviceId=(string)$binding['gateway_device_id'];$device=$this->gateway?->devices->get($deviceId);$config=[];$reported=[];if($this->gateway){try{$resolved=$this->gateway->configs->resolve($deviceId);$config=is_array($resolved['config']??null)?$resolved['config']:[];}catch(\Throwable){}}$q=$this->db->prepare('SELECT reported_state_json,last_ip FROM vg_devices WHERE device_id=? LIMIT 1');$q->execute([$deviceId]);$raw=$q->fetch();if($raw){$reported=json_decode((string)($raw['reported_state_json']??''),true)?:[];$reported['ip']=$raw['last_ip']??null;}if(!$config&&$reported)$config=['hardware'=>$reported['hardware']??[],'coin'=>$reported['coin']??[]];$rows[]=['binding'=>$binding,'device'=>$device,'config'=>$config,'reported'=>$reported,'selected'=>$selectedDevice!==''&&hash_equals($selectedDevice,$deviceId)];}
        $flash=(string)($_SESSION['admin_flash']??'');unset($_SESSION['admin_flash']);$this->page('Manage Vendo',__DIR__.'/views/index.php',['flash'=>$flash,'routerId'=>$routerId,'stations'=>$stations,'rows'=>$rows,'selectedDevice'=>$selectedDevice,'gatewayConfigured'=>(bool)$this->gateway]);
    }

    private function handlePost(RouterAccess $access,int $userId,bool $platformOwner,int $routerId): array
    {
        require_csrf();$action=(string)($_POST['action']??'');
        try{
            if(!$this->gateway)throw new \RuntimeException('Vendo Gateway is not configured.');if(!$access->canManage($routerId,$userId,$platformOwner))throw new \RuntimeException('You do not have access to manage this router.');
            if($action==='setup_code'){
                $stationId=max(0,(int)($_POST['station_id']??$_POST['vendo_id']??0));$vendoName=trim((string)($_POST['vendo_name']??''));if($stationId<1)throw new \RuntimeException('Select a station.');if($vendoName==='')$vendoName='Vendo';if(strlen($vendoName)>160)throw new \RuntimeException('Vendo name is too long.');
                $stmt=$this->db->prepare('SELECT id,name,router_id FROM vendos WHERE id=? LIMIT 1');$stmt->execute([$stationId]);$station=$stmt->fetch();if(!$station||(int)$station['router_id']!==$routerId)throw new \RuntimeException('Station not found for the selected router.');
                $result=$this->gateway->pairings->prepare((string)$userId,['station_id'=>$stationId,'vendo_id'=>$stationId,'router_id'=>$routerId,'vendo_name'=>$vendoName]);$code=(string)($result['setup_code']??'');if($code==='')throw new \RuntimeException('Setup code was not generated.');
                $this->audit('vendo_gateway.setup_code.created','vendo_gateway_enrollment',(string)($result['enrollment_id']??''),'Vendo setup code generated.',['station_id'=>$stationId,'router_id'=>$routerId,'vendo_name'=>$vendoName]);
                $message=$vendoName.' setup code: '.$code.' (Station: '.$station['name'].')';$_SESSION['admin_flash']='<div class="alert alert-success"><strong>'.e($vendoName).' setup code: <span class="font-monospace fs-5">'.e($code).'</span></strong><br>Station: '.e($station['name']).'<br><small>Valid for about 10 minutes and can be used once. Enter it during ESP setup. After enrollment this becomes another Vendo under the same station.</small></div>';return['ok'=>true,'message'=>$message,'setup_code'=>$code];
            }
            $deviceId=trim((string)($_POST['device_id']??''));if($deviceId==='')throw new \RuntimeException('Device is required.');$binding=$this->bridge->binding($deviceId);if(!$binding||(int)($binding['router_id']??0)!==$routerId)throw new \RuntimeException('Vendo is not bound to the selected router.');
            if($action==='rename'){$name=trim((string)($_POST['vendo_name']??''));$this->bridge->rename($deviceId,$name);$_SESSION['admin_flash']='<div class="alert ok">Vendo renamed.</div>';return['ok'=>true,'message'=>'Vendo renamed.','action'=>$action,'device_id'=>$deviceId,'vendo_name'=>$name?:'Vendo'];}
            if($action==='save_config'){
                $coin=(int)($_POST['coin_pin']??0);$relay=(int)($_POST['relay_pin']??0);$led=(int)($_POST['status_led_pin']??0);$settle=(int)($_POST['coin_settle_ms']??350);if($coin<1||$coin>39||$relay<1||$relay>39||$led<1||$led>39)throw new \RuntimeException('GPIO values must be between 1 and 39. GPIO0 is reserved for recovery.');if(count(array_unique([$coin,$relay,$led]))!==3)throw new \RuntimeException('Coin, relay and status LED GPIOs must be different.');if($settle<50||$settle>2000)throw new \RuntimeException('Coin settle time must be between 50 and 2000 ms.');
                $resolved=$this->gateway->configs->resolve($deviceId);$config=$resolved['config']??[];$config['hardware']['pins']=['coin'=>$coin,'relay'=>$relay,'status_led'=>$led];$config['coin']['settle_ms']=$settle;$this->gateway->configs->setDeviceConfig($deviceId,$config);$this->gateway->commands->queue($deviceId,'config.refresh');$this->audit('vendo_gateway.device.config','vendo_gateway_device',$deviceId,'Vendo hardware configuration updated.',['router_id'=>$routerId,'station_id'=>$binding['station_id']??null]);$_SESSION['admin_flash']='<div class="alert alert-success">Vendo hardware configuration saved and queued for sync.</div>';return['ok'=>true,'message'=>'Hardware configuration saved and queued for sync.','action'=>$action,'device_id'=>$deviceId];
            }
            if($action==='activate'){$this->gateway->devices->activate($deviceId);$message='Vendo activated.';}elseif($action==='suspend'){$this->gateway->devices->suspend($deviceId);$message='Vendo suspended.';}elseif($action==='revoke'){$this->gateway->devices->revoke($deviceId);$message='Vendo revoked.';}elseif($action==='unbind'){$this->bridge->unbind($deviceId);$message='Vendo unbound.';}elseif($action==='coin_enable'){$this->gateway->commands->queue($deviceId,'coin.enable');$message='Coin input enable queued.';}elseif($action==='coin_disable'){$this->gateway->commands->queue($deviceId,'coin.disable');$message='Coin input disable queued.';}elseif($action==='restart'){$this->gateway->commands->queue($deviceId,'system.restart');$message='Restart queued.';}elseif($action==='firmware_check'){$this->gateway->commands->queue($deviceId,'firmware.check');$message='Firmware check queued.';}else throw new \RuntimeException('Unknown action.');
            $this->audit('vendo_gateway.device.'.$action,'vendo_gateway_device',$deviceId,'Vendo device action executed.',['router_id'=>$routerId,'station_id'=>$binding['station_id']??null]);$_SESSION['admin_flash']='<div class="alert ok">'.e($message).'</div>';return['ok'=>true,'message'=>$message,'action'=>$action,'device_id'=>$deviceId];
        }catch(\Throwable $e){$_SESSION['admin_flash']='<div class="alert">'.e($e->getMessage()).'</div>';return['ok'=>false,'message'=>$e->getMessage(),'action'=>$action];}
    }

    private function wantsJson(): bool{return strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH']??''))==='xmlhttprequest'||str_contains(strtolower((string)($_SERVER['HTTP_ACCEPT']??'')),'application/json');}
    private function json(array $payload,int $status=200): never{unset($_SESSION['admin_flash']);http_response_code($status);header('Content-Type: application/json; charset=utf-8');header('Cache-Control: no-store');echo json_encode($payload,JSON_UNESCAPED_SLASHES);exit;}
}
