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
        if($this->isPost()){$this->handlePost($access,$userId,$platformOwner,$routerId);redirect('/admin/vendo-gateway');}
        $stations=[];$stmt=$this->db->prepare('SELECT id,name FROM vendos WHERE router_id=? AND enabled=1 ORDER BY name');$stmt->execute([$routerId]);$stations=$stmt->fetchAll();
        $rows=[];
        foreach($this->bridge->bindings($routerId) as $binding){$device=$this->gateway?->devices->get((string)$binding['gateway_device_id']);$rows[]=['binding'=>$binding,'device'=>$device,'capabilities'=>$device&&$this->gateway?$this->gateway->devices->capabilities($device->deviceId):[]];}
        $flash=$_SESSION['admin_flash']??null;unset($_SESSION['admin_flash']);
        if(!$this->gateway)$flash=($flash??'').'<div class="alert">VENDO_GATEWAY_MASTER_KEY is not configured. Set a random key of at least 32 characters before pairing devices.</div>';
        $this->page('Vendo Gateway',__DIR__.'/views/index.php',['rows'=>$rows,'stations'=>$stations,'routerId'=>$routerId,'flash'=>$flash,'configured'=>(bool)$this->gateway]);
    }

    private function handlePost(RouterAccess $access,int $userId,bool $platformOwner,int $routerId): void
    {
        require_csrf();$action=(string)($_POST['action']??'');
        try{
            if(!$this->gateway)throw new \RuntimeException('Vendo Gateway is not configured.');
            if(!$access->canManage($routerId,$userId,$platformOwner))throw new \RuntimeException('You do not have access to manage this router.');
            if($action==='claim'){
                $code=trim((string)($_POST['pairing_code']??''));$vendoId=max(0,(int)($_POST['vendo_id']??0));
                if(!preg_match('/^\d{6}$/',$code)||$vendoId<1)throw new \RuntimeException('Enter the 6-digit pairing code and select a station.');
                $stmt=$this->db->prepare('SELECT id,name,router_id FROM vendos WHERE id=? LIMIT 1');$stmt->execute([$vendoId]);$station=$stmt->fetch();
                if(!$station||(int)$station['router_id']!==$routerId)throw new \RuntimeException('Station not found for the selected router.');
                $result=$this->gateway->pairings->claim($code,(string)$userId,['vendo_id'=>$vendoId,'router_id'=>$routerId]);$deviceId=(string)($result['device_id']??'');if($deviceId==='')throw new \RuntimeException('Pairing did not return a device ID.');
                $this->bridge->bind($deviceId,$vendoId);$this->audit('vendo_gateway.device.claimed','vendo_gateway_device',$deviceId,'Vendo Gateway device paired to station.',['vendo_id'=>$vendoId,'router_id'=>$routerId]);
                $_SESSION['admin_flash']='<div class="alert ok">Device paired to '.e($station['name']).'.</div>';return;
            }
            $deviceId=trim((string)($_POST['device_id']??''));if($deviceId==='')throw new \RuntimeException('Device is required.');
            $binding=$this->bridge->binding($deviceId);if(!$binding||(int)($binding['router_id']??0)!==$routerId)throw new \RuntimeException('Device is not bound to the selected router.');
            if($action==='activate')$this->gateway->devices->activate($deviceId);
            elseif($action==='suspend')$this->gateway->devices->suspend($deviceId);
            elseif($action==='revoke')$this->gateway->devices->revoke($deviceId);
            elseif($action==='unbind')$this->bridge->unbind($deviceId);
            elseif($action==='coin_enable')$this->gateway->commands->queue($deviceId,'coin.enable');
            elseif($action==='coin_disable')$this->gateway->commands->queue($deviceId,'coin.disable');
            else throw new \RuntimeException('Unknown action.');
            $this->audit('vendo_gateway.device.'.$action,'vendo_gateway_device',$deviceId,'Vendo Gateway device action executed.',['router_id'=>$routerId,'vendo_id'=>$binding['vendo_id']??null]);
            $_SESSION['admin_flash']='<div class="alert ok">Device action queued/applied.</div>';
        }catch(\Throwable $e){$_SESSION['admin_flash']='<div class="alert">'.e($e->getMessage()).'</div>';}
    }
}
