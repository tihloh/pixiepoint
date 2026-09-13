<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Vendos;

use PDO;
use PixiePoint\App\Portal\Adapters\PlatformAdapter;
use PixiePoint\App\Portal\ThemeEngine;
use PixiePoint\App\Services\NetworkDeviceIdentity;
use PixiePoint\App\Services\PointWallet;
use PixiePoint\App\Services\PortalFeatureConfig;
use PixiePoint\App\Services\PortalThemeManager;
use PixiePoint\App\Services\View;
use Throwable;

final class HotspotController
{
    public function __construct(private Api $api,private View $view,private PortalThemeManager $themes,private ThemeEngine $themeEngine,private PlatformAdapter $portalAdapter,private PDO $db,private NetworkDeviceIdentity $networkDevices,private PointWallet $points){}

    public function portal(): never
    {
        $context=$this->hotspotContext();$stations=$this->api->forHotspot($context['routerIdentity'],$context['serverAddress'],$context['ip'],$context['interfaceName']);$stationId=isset($stations[0]['id'])?(int)$stations[0]['id']:null;$routerId=$this->routerId($context['routerIdentity']);$resolved=(new PortalFeatureConfig($this->db))->resolve($routerId,$stationId);$theme=$this->themes->resolveByRouterIdentity($context['routerIdentity'],$stationId);$device=$this->portalDevice($context);$options=$this->vendoOptions($stations);$auth=$this->hasActiveHotspotSession();
        $feature=static fn(string $key,bool $fallback=false):bool=>isset($resolved[$key])?(bool)$resolved[$key]['enabled']:$fallback;$hasVendo=!empty(array_filter($stations,static fn(array $v):bool=>trim((string)($v['baseUrl']??''))!==''));
        $this->headers('text/html; charset=utf-8');echo $this->themeEngine->render($theme,'portal.html',$this->portalAdapter,[
            'portal'=>['auth'=>$auth,'name'=>(string)($stations[0]['name']??'PixiePoint'),'vendo_options'=>$options,'device'=>$device,'debug'=>$this->debugDetails($context),'features'=>$resolved,'trial_minutes'=>(int)($resolved['trial']['config']['minutes']??10),'convert_points'=>(int)($resolved['points_convert']['config']['points']??10),'convert_minutes'=>(int)($resolved['points_convert']['config']['minutes']??5)],'context'=>$context,
        ],['voucher_login'=>$feature('voucher_login',true),'member_login'=>$feature('member_login'),'qr_scan'=>$feature('qr_scan'),'trial'=>$feature('trial'),'points'=>$feature('points'),'points_convert'=>$feature('points_convert'),'points_play'=>$feature('points_play'),'points_share'=>$feature('points_share'),'coin_slot'=>$hasVendo&&$feature('coin_slot',true)]);exit;
    }

    public function status(): never{$this->portal();}

    public function index(): never
    {
        $raw=['router_identity'=>(string)($_GET['router_identity']??''),'server_address'=>(string)($_GET['server_address']??''),'client_ip'=>(string)($_GET['client_ip']??''),'interface'=>(string)($_GET['interface']??''),'mac'=>(string)($_GET['mac']??'')];[$d,$errors]=$this->validateQuery($raw);if($errors){http_response_code(422);echo json_encode(['ok'=>false,'errors'=>$errors,'vendos'=>[]]);exit;}$this->headers('application/json; charset=utf-8');echo json_encode(['ok'=>true,'vendos'=>$this->api->forHotspot($d['router_identity'],$d['server_address'],$d['client_ip'],$d['interface'])],JSON_UNESCAPED_SLASHES);exit;
    }

    private function routerId(string $identity): int
    {
        if($identity==='')return 0;$stmt=$this->db->prepare('SELECT id FROM routers WHERE identity=? AND enabled=1 LIMIT 1');$stmt->execute([$identity]);return (int)($stmt->fetchColumn()?:0);
    }

    private function hasActiveHotspotSession(): bool
    {
        foreach(['status_url','logout_url'] as $key){$value=trim((string)($_GET[$key]??''));if($value!==''&&!str_contains($value,'$('))return true;}return false;
    }

    private function debugDetails(array $context): array
    {
        if(!$this->api->hasDebugTarget($context['routerIdentity']))return [];return ['raw'=>['router_identity'=>$context['routerIdentity'],'server_address'=>$context['serverAddress'],'client_ip'=>$context['ip'],'interface'=>$context['interfaceName'],'mac'=>$context['mac']],'processed'=>$context,'matching'=>$this->api->debugForHotspot($context['routerIdentity'],$context['serverAddress'],$context['ip'],$context['interfaceName'])];
    }

    private function portalDevice(array $context): array
    {
        $mac=$this->normalizeMac($context['mac']);$fallback=['account'=>'Guest device','points'=>0,'ip'=>$context['ip']!==''?$context['ip']:'—','mac'=>$mac?:'—','last_voucher'=>'','uuid'=>''];if($mac==='')return $fallback;
        try{$device=$this->networkDevices->resolve($mac,implode('|',array_filter([$context['routerIdentity'],$context['interfaceName']]))?:'global',$context['ip']);if(!$device)return $fallback;$deviceId=(int)$device['id'];$userId=!empty($device['user_id'])?(int)$device['user_id']:null;$account='Guest device';if($userId){$stmt=$this->db->prepare('SELECT name FROM users WHERE id=? AND active=1 LIMIT 1');$stmt->execute([$userId]);$account=(string)($stmt->fetchColumn()?:$account);}return ['account'=>$account,'points'=>$this->points->balanceForDevice($deviceId,$userId),'ip'=>$context['ip']!==''?$context['ip']:(string)($device['last_ip']??'—'),'mac'=>$mac,'last_voucher'=>trim((string)($device['last_voucher']??'')),'uuid'=>(string)($device['uuid']??'')];}catch(Throwable){return $fallback;}
    }

    private function normalizeMac(string $value): string
    {
        $value=trim($value);if($value===''||str_contains($value,'$('))return '';$hex=preg_replace('/[^0-9a-fA-F]/','',$value)??'';return strlen($hex)===12?strtoupper(implode(':',str_split($hex,2))):'';
    }

    private function vendoOptions(array $stations): string
    {
        $options='';$selected=false;foreach($stations as $station){if(trim((string)($station['baseUrl']??''))==='')continue;$options.='<option value="'.e($station['id']).'" data-base-url="'.e($station['baseUrl']).'" data-password-mode="'.e($station['passwordMode']).'" data-charging="'.($station['chargingEnabled']?'1':'0').'" data-eload="'.($station['eloadEnabled']?'1':'0').'"'.(!$selected?' selected':'').'>'.e($station['name']).'</option>';$selected=true;}return $options;
    }

    private function hotspotContext(): array
    {
        $raw=['router_identity'=>(string)($_GET['router_identity']??''),'server_address'=>(string)($_GET['server_address']??''),'client_ip'=>(string)($_GET['client_ip']??''),'interface'=>(string)($_GET['interface']??''),'mac'=>(string)($_GET['mac']??'')];[$data]=$this->validateQuery($raw);return ['routerIdentity'=>$data['router_identity'],'serverAddress'=>$data['server_address'],'ip'=>$data['client_ip'],'interfaceName'=>$data['interface'],'mac'=>$this->normalizeMac($data['mac'])];
    }

    private function headers(string $contentType): void{header('Content-Type: '.$contentType);header('Access-Control-Allow-Origin: *');header('Cache-Control: no-store');}

    private function validateQuery(array $raw): array
    {
        $data=[];$errors=[];foreach(['router_identity'=>160,'server_address'=>45,'client_ip'=>45,'interface'=>128,'mac'=>32] as $key=>$max){$value=trim((string)($raw[$key]??''));if(strlen($value)>$max){$errors[$key]=['The '.$key.' field is too long.'];$value=substr($value,0,$max);}$data[$key]=$value;}if($data['router_identity']==='')$errors['router_identity']=['The router identity field is required.'];return [$data,$errors];
    }
}
