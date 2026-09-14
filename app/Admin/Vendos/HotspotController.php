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
        $context=$this->hotspotContext();$stations=$this->api->forHotspot($context['routerIdentity'],$context['serverAddress'],$context['ip'],$context['interfaceName']);$stationId=isset($stations[0]['id'])?(int)$stations[0]['id']:null;$routerId=$this->routerId($context['routerIdentity']);$resolved=(new PortalFeatureConfig($this->db))->resolve($routerId,$stationId);$theme=$this->themes->resolveByRouterIdentity($context['routerIdentity'],$stationId);$device=$this->portalDevice($context);$options=$this->vendoOptions($stations);$auth=$this->hasActiveHotspotSession($context);
        $_SESSION['hotspot']=['mac'=>$context['mac'],'ip'=>$context['ip'],'username'=>$context['username'],'router_identity'=>$context['routerIdentity'],'interface'=>$context['interfaceName'],'ssid'=>'','server_address'=>$context['serverAddress'],'login_url'=>$context['loginUrl'],'original_url'=>$context['originalUrl'],'chap_id'=>$context['chapId'],'chap_challenge'=>$context['chapChallenge']];
        $feature=static fn(string $key,bool $fallback=false):bool=>isset($resolved[$key])?(bool)$resolved[$key]['enabled']:$fallback;
        $html=$this->themeEngine->render($theme,'portal.html',$this->portalAdapter,[
            'portal'=>['auth'=>$auth,'name'=>(string)($stations[0]['name']??'PixiePoint'),'vendo_options'=>$options,'device'=>$device,'debug'=>$this->debugDetails($context),'features'=>$resolved,'trial_minutes'=>(int)($resolved['trial']['config']['minutes']??10),'convert_points'=>(int)($resolved['points_convert']['config']['points']??10),'convert_minutes'=>(int)($resolved['points_convert']['config']['minutes']??5),'csrf'=>csrf_token()],
            'context'=>$context,
        ],['voucher_login'=>$feature('voucher_login',true),'member_login'=>$feature('member_login'),'qr_scan'=>$feature('qr_scan'),'trial'=>$feature('trial'),'points'=>$feature('points'),'points_convert'=>$feature('points_convert'),'points_play'=>$feature('points_play'),'points_share'=>$feature('points_share'),'coin_slot'=>false]);
        $html=str_replace('/assets/juanfi-compat.js','/assets/hotspot-login.js',$html);
        $this->headers('text/html; charset=utf-8');echo $html;exit;
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

    private function hasActiveHotspotSession(array $context): bool{return trim((string)($context['logoutUrl']??''))!==''||trim((string)($context['refreshUrl']??''))!=='';}

    private function debugDetails(array $context): array
    {
        if(!$this->api->hasDebugTarget($context['routerIdentity']))return [];return ['raw'=>$_GET,'processed'=>$context,'matching'=>$this->api->debugForHotspot($context['routerIdentity'],$context['serverAddress'],$context['ip'],$context['interfaceName'])];
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
        $get=static function(string ...$keys):string{foreach($keys as $key){$value=trim((string)($_GET[$key]??''));if($value!=='')return $value;}return '';};
        $routerIdentity=$get('router_identity','router-identity','identity');$serverAddress=$get('server_address','server-address');$ip=$get('client_ip','ip');$interface=$get('interface','interface_name','interface-name');$mac=$get('mac');
        return ['routerIdentity'=>$routerIdentity,'serverAddress'=>$serverAddress,'ip'=>$ip,'interfaceName'=>$interface,'mac'=>$this->normalizeMac($mac),'username'=>$get('username'),'loginUrl'=>$get('login_url','link-login-only'),'originalUrl'=>$get('original_url','link-orig'),'chapId'=>$get('chap_id','chap-id'),'chapChallenge'=>$get('chap_challenge','chap-challenge'),'error'=>$get('error'),'logoutUrl'=>$get('logout_url','link-logout'),'refreshUrl'=>$get('status_url','link-status'),'sessionTimeLeft'=>$get('session_time_left','session-time-left'),'bytesIn'=>$get('bytes_in','bytes-in'),'bytesOut'=>$get('bytes_out','bytes-out'),'remainBytesTotal'=>$get('remain_bytes_total','remain-bytes-total')];
    }

    private function headers(string $contentType): void{header('Content-Type: '.$contentType);header('Access-Control-Allow-Origin: *');header('Cache-Control: no-store');}

    private function validateQuery(array $raw): array
    {
        $data=[];$errors=[];foreach(['router_identity'=>160,'server_address'=>45,'client_ip'=>45,'interface'=>128,'mac'=>32] as $key=>$max){$value=trim((string)($raw[$key]??''));if(strlen($value)>$max){$errors[$key]=['The '.$key.' field is too long.'];$value=substr($value,0,$max);}$data[$key]=$value;}if($data['router_identity']==='')$errors['router_identity']=['The router identity field is required.'];return [$data,$errors];
    }
}
