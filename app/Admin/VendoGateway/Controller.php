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
        (new Migrator($db))->migrate();$this->bridge=new VendoGatewayBridge($db);
        $key=trim((string)($config['vendo_gateway_master_key']??''));if(strlen($key)>=32)$this->gateway=GatewayFactory::pdo($db,$key);
    }

    public function index(): never
    {
        $user=$this->auth->requireAccount();$userId=(int)$user['id'];$platformOwner=$this->auth->isPlatformOwner();$access=new RouterAccess($this->db);
        $routerId=max(0,(int)($_SESSION['pixiepoint_selected_router_id']??0));if($routerId<1)redirect('/admin/routers');
        if(!$access->canView($routerId,$userId,$platformOwner)){unset($_SESSION['pixiepoint_selected_router_id']);redirect('/admin/routers');}
        if($this->isPost()){
            $this->handlePost($access,$userId,$platformOwner,$routerId);$return=trim((string)($_POST['return_to']??''));
            if($return==='/admin/routers'||preg_match('~^/admin/routers/\d+$~',$return))redirect($return);redirect('/admin/vendo-gateway');
        }
        $stmt=$this->db->prepare('SELECT id,name FROM vendos WHERE router_id=? AND enabled=1 ORDER BY name');$stmt->execute([$routerId]);$stations=$stmt->fetchAll();$rows=[];
        foreach($this->bridge->bindings($routerId) as $binding){$device=$this->gateway?->devices->get((string)$binding['gateway_device_id']);$resolved=$device&&$this->gateway?$this->gateway->configs->resolve($device->deviceId):null;$rows[]=['binding'=>$binding,'device'=>$device,'capabilities'=>$device&&$this->gateway?$this->gateway->devices->capabilities($device->deviceId):[],'config'=>$resolved['config']??[]];}
        $flash=$_SESSION['admin_flash']??null;unset($_SESSION['admin_flash']);if(!$this->gateway)$flash=($flash??'').'<div class="alert">VENDO_GATEWAY_MASTER_KEY is not configured. Set a random key of at least 32 characters before enrolling devices.</div>';
        $this->page('Vendo Gateway',__DIR__.'/views/index.php',['rows'=>$rows,'stations'=>$stations,'routerId'=>$routerId,'flash'=>$flash,'configured'=>(bool)$this->gateway]);
    }

    private function handlePost(RouterAccess $access,int $userId,bool $platformOwner,int $routerId): void
    {
        require_csrf();$action=(string)($_POST['action']??'');
        try{
            if(!$this->gateway)throw new \RuntimeException('Vendo Gateway is not configured.');
            if(!$access->canManage($routerId,$userId,$platformOwner))throw new \RuntimeException('You do not have access to manage this router.');
            if($action==='setup_code'){
                $vendoId=max(0,(int)($_POST['vendo_id']??0));if($vendoId<1)throw new \RuntimeException('Select a station.');
                $stmt=$this->db->prepare('SELECT id,name,router_id FROM vendos WHERE id=? LIMIT 1');$stmt->execute([$vendoId]);$station=$stmt->fetch();if(!$station||(int)$station['router_id']!==$routerId)throw new \RuntimeException('Station not found for the selected router.');
                $result=$this->gateway->pairings->prepare((string)$userId,['vendo_id'=>$vendoId,'router_id'=>$routerId]);$code=(string)($result['setup_code']??'');if($code==='')throw new \RuntimeException('Setup code was not generated.');
                $this->audit('vendo_gateway.setup_code.created','vendo_gateway_enrollment',(string)($result['enrollment_id']??''),'Vendo Gateway setup code generated.',['vendo_id'=>$vendoId,'router_id'=>$routerId]);
                $_SESSION['admin_flash']='<div class="alert alert-success"><strong>Setup code: <span class="font-monospace fs-5">'.e($code).'</span></strong><br>Station: '.e($station['name']).'<br><small>Valid for about 10 minutes and can be used once. Enter it in the ESP initial setup page, then save. No second pairing step is required.</small></div>';return;
            }
            $deviceId=trim((string)($_POST['device_id']??''));if($deviceId==='')throw new \RuntimeException('Device is required.');$binding=$this->bridge->binding($deviceId);if(!$binding||(int)($binding['router_id']??0)!==$routerId)throw new \RuntimeException('Device is not bound to the selected router.');
            if($action==='save_config'){
                $coin=(int)($_POST['coin_pin']??0);$relay=(int)($_POST['relay_pin']??0);$led=(int)($_POST['status_led_pin']??0);$settle=(int)($_POST['coin_settle_ms']??350);
                if($coin<1||$coin>39||$relay<1||$relay>39||$led<1||$led>39)throw new \RuntimeException('GPIO values must be between 1 and 39. GPIO0 is reserved for recovery.');
                if(count(array_unique([$coin,$relay,$led]))!==3)throw new \RuntimeException('Coin, relay and status LED GPIOs must be different.');if($settle<50||$settle>2000)throw new \RuntimeException('Coin settle time must be between 50 and 2000 ms.');
                $resolved=$this->gateway->configs->resolve($deviceId);$config=$resolved['config']??[];$config['hardware']['pins']=['coin'=>$coin,'relay'=>$relay,'status_led'=>$led];$config['coin']['settle_ms']=$settle;$this->gateway->configs->setDeviceConfig($deviceId,$config);$this->gateway->commands->queue($deviceId,'config.refresh');
                $this->audit('vendo_gateway.device.config','vendo_gateway_device',$deviceId,'Vendo Gateway hardware configuration updated.',['router_id'=>$routerId,'vendo_id'=>$binding['vendo_id']??null,'coin_pin'=>$coin,'relay_pin'=>$relay,'status_led_pin'=>$led,'coin_settle_ms'=>$settle]);$_SESSION['admin_flash']='<div class="alert alert-success">Hardware configuration saved. The device will validate it, sync, and reboot if its pin mapping changes.</div>';return;
            }
            if($action==='activate')$this->gateway->devices->activate($deviceId);elseif($action==='suspend')$this->gateway->devices->suspend($deviceId);elseif($action==='revoke')$this->gateway->devices->revoke($deviceId);elseif($action==='unbind')$this->bridge->unbind($deviceId);elseif($action==='coin_enable')$this->gateway->commands->queue($deviceId,'coin.enable');elseif($action==='coin_disable')$this->gateway->commands->queue($deviceId,'coin.disable');else throw new \RuntimeException('Unknown action.');
            $this->audit('vendo_gateway.device.'.$action,'vendo_gateway_device',$deviceId,'Vendo Gateway device action executed.',['router_id'=>$routerId,'vendo_id'=>$binding['vendo_id']??null]);$_SESSION['admin_flash']='<div class="alert ok">Device action queued/applied.</div>';
        }catch(\Throwable $e){$_SESSION['admin_flash']='<div class="alert">'.e($e->getMessage()).'</div>';}
    }
}
