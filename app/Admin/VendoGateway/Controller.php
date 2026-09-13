<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\VendoGateway;

use PDO;
use PixiePoint\App\Admin\Shared\FeatureController;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\VendoGatewayBridge;
use PixiePoint\App\Services\View;
use Tihloh\Prefab\Logs\Services\LogManager;
use Tihloh\VendoGateway\Database\Migrator;
use Tihloh\VendoGateway\Gateway;
use Tihloh\VendoGateway\GatewayFactory;

final class Controller extends FeatureController
{
    private Gateway $gateway;
    private VendoGatewayBridge $bridge;

    public function __construct(PDO $db,AuthContext $auth,View $view,LogManager $logs,private array $config)
    {
        parent::__construct($db,$auth,$view,$logs);
        $key=trim((string)($config['vendo_gateway_master_key']??''));
        if(strlen($key)<32)throw new \RuntimeException('VENDO_GATEWAY_MASTER_KEY must be at least 32 characters.');
        (new Migrator($db))->migrate();
        $this->gateway=GatewayFactory::pdo($db,$key);
        $this->bridge=new VendoGatewayBridge($db);
    }

    public function index(): never
    {
        $user=$this->auth->requireAccount();
        if($this->isPost()){$this->handlePost((string)$user['id']);redirect('/admin/vendo-gateway');}
        $routerId=max(0,(int)($_SESSION['pixiepoint_selected_router_id']??0));
        $stations=[];
        if($routerId>0){$stmt=$this->db->prepare('SELECT id,name FROM vendos WHERE router_id=? AND enabled=1 ORDER BY name');$stmt->execute([$routerId]);$stations=$stmt->fetchAll();}
        $rows=[];
        foreach($this->bridge->bindings($routerId?:null) as $binding){$device=$this->gateway->devices->get((string)$binding['gateway_device_id']);$rows[]=['binding'=>$binding,'device'=>$device,'capabilities'=>$device?$this->gateway->devices->capabilities($device->deviceId):[]];}
        $flash=$_SESSION['admin_flash']??null;unset($_SESSION['admin_flash']);
        $this->page('Vendo Gateway',__DIR__.'/views/index.php',['rows'=>$rows,'stations'=>$stations,'routerId'=>$routerId,'flash'=>$flash]);
    }

    private function handlePost(string $userId): void
    {
        require_csrf();$action=(string)($_POST['action']??'');
        try{
            if($action==='claim'){
                $code=trim((string)($_POST['pairing_code']??''));$vendoId=max(0,(int)($_POST['vendo_id']??0));
                if(!preg_match('/^\d{6}$/',$code)||$vendoId<1)throw new \RuntimeException('Enter the 6-digit pairing code and select a station.');
                $stmt=$this->db->prepare('SELECT id,name FROM vendos WHERE id=? LIMIT 1');$stmt->execute([$vendoId]);$station=$stmt->fetch();if(!$station)throw new \RuntimeException('Station not found.');
                $result=$this->gateway->pairings->claim($code,$userId,['vendo_id'=>$vendoId]);$deviceId=(string)($result['device_id']??'');if($deviceId==='')throw new \RuntimeException('Pairing did not return a device ID.');
                $this->bridge->bind($deviceId,$vendoId);$this->audit('vendo_gateway.device.claimed','vendo_gateway_device',$deviceId,'Vendo Gateway device paired to station.',['vendo_id'=>$vendoId]);
                $_SESSION['admin_flash']='<div class="alert ok">Device paired to '.e($station['name']).'.</div>';return;
            }
            $deviceId=trim((string)($_POST['device_id']??''));if($deviceId==='')throw new \RuntimeException('Device is required.');
            if($action==='activate')$this->gateway->devices->activate($deviceId);
            elseif($action==='suspend')$this->gateway->devices->suspend($deviceId);
            elseif($action==='revoke')$this->gateway->devices->revoke($deviceId);
            elseif($action==='unbind')$this->bridge->unbind($deviceId);
            elseif($action==='coin_enable')$this->gateway->commands->queue($deviceId,'coin.enable');
            elseif($action==='coin_disable')$this->gateway->commands->queue($deviceId,'coin.disable');
            else throw new \RuntimeException('Unknown action.');
            $this->audit('vendo_gateway.device.'.$action,'vendo_gateway_device',$deviceId,'Vendo Gateway device action executed.');
            $_SESSION['admin_flash']='<div class="alert ok">Device action queued/applied.</div>';
        }catch(\Throwable $e){$_SESSION['admin_flash']='<div class="alert">'.e($e->getMessage()).'</div>';}
    }
}
