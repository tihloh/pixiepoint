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
use PixiePoint\App\Services\VendoGatewayBridge;
use Throwable;

final class HotspotController
{
    private VendoGatewayBridge $bridge;

    public function __construct(private Api $api,private View $view,private PortalThemeManager $themes,private ThemeEngine $themeEngine,private PlatformAdapter $portalAdapter,private PDO $db,private NetworkDeviceIdentity $networkDevices,private PointWallet $points){
        $this->bridge=new VendoGatewayBridge($db);
    }

    public function portal(): never{
        $this->render(null);
    }
    public function login(): never{
        $this->render('login');
    }
    public function status(): never{
        $this->render('status');
    }
    public function logout(): never{
        $this->render('logout');
    }

    private function render(?string $state): never
    {
        $context=$this->hotspotContext();
        $context['state']=$state??'login';
        if($state===null&&$this->isMikroTikHandoff()){
            if(isset($_GET['link-login-only'])||isset($_GET['disconnected'])){
                $context['logoutUrl']='';
                $context['refreshUrl']='';
                $context['sessionTimeLeft']='';
                $context['bytesIn']='';
                $context['bytesOut']='';
                $context['remainBytesTotal']='';
            }
            $_SESSION['hotspot']=$this->sessionContext($context);
            header('Location: /hotspot',true,302);
            exit;
        }
        if($state==='login'||$state==='logout'){
            $context['logoutUrl']='';
            $context['refreshUrl']='';
            $context['sessionTimeLeft']='';
            $context['bytesIn']='';
            $context['bytesOut']='';
            $context['remainBytesTotal']='';
        }
        $stations=$this->api->forHotspot($context['routerIdentity'],$context['serverAddress'],$context['ip'],$context['interfaceName']);
        $context['passwordMode']=(string)($stations[0]['passwordMode']??'blank');
        $stationId=isset($stations[0]['id'])?(int)$stations[0]['id']:null;
        $routerId=$this->routerId($context['routerIdentity']);
        $resolved=(new PortalFeatureConfig($this->db))->resolve($routerId,$stationId);
        $theme=$this->themes->resolveByRouterIdentity($context['routerIdentity'],$stationId);
        $device=$this->portalDevice($context);
        $options=$this->vendoOptions($stations);
        $auth=$state==='status'?true:($state==='login'||$state==='logout'?false:$this->hasActiveHotspotSession($context));
        $_SESSION['hotspot']=$this->sessionContext($context);
        $feature=static fn(string $key,bool $fallback=false):bool=>isset($resolved[$key])?(bool)$resolved[$key]['enabled']:$fallback;
        $html=$this->themeEngine->render($theme,'portal.html',$this->portalAdapter,['portal'=>['auth'=>$auth,'name'=>(string)($stations[0]['name']??'PixiePoint'),'vendo_options'=>$options,'device'=>$device,'debug'=>$this->debugDetails($context),'features'=>$resolved,'trial_minutes'=>(int)($resolved['trial']['config']['minutes']??10),'convert_points'=>(int)($resolved['points_convert']['config']['points']??10),'convert_minutes'=>(int)($resolved['points_convert']['config']['minutes']??5),'csrf'=>csrf_token()],'context'=>$context],['voucher_login'=>$feature('voucher_login',true),'member_login'=>$feature('member_login'),'qr_scan'=>$feature('qr_scan'),'trial'=>$feature('trial'),'points'=>$feature('points'),'points_convert'=>$feature('points_convert'),'points_play'=>$feature('points_play'),'points_share'=>$feature('points_share'),'coin_slot'=>false]);
        $html=str_replace(['<script src="/assets/juanfi-compat.js"></script>','<script src="/assets/hotspot-login.js"></script>'],'',$html);
        $this->headers('text/html; charset=utf-8');
        echo $html;
        exit;
    }

    public function coinSessionStart(): never
    {
        require_csrf();
        try{
            [$context,$device,$userId]=$this->coinOwner();
            $stationId=max(0,(int)($_POST['vendo_id']??0));
            $routerId=$this->routerId($context['routerIdentity']);
            if($stationId<1||$routerId<1||$this->bridge->routerIdForStation($stationId)!==$routerId)throw new \RuntimeException('Vendo is not available for this hotspot.');
            $session=$this->bridge->startCoinSession($stationId,$routerId,$userId,(int)$device['id']);
            $this->json(['ok'=>true,'session'=>[
                'id'=>$session['session_id'],
                'credits'=>$session['credits'],
                'coin_count'=>$session['coin_count']
            ]]);
        }
        catch(Throwable $e){
            $this->json(['ok'=>false,'error'=>$e->getMessage()],409);
        }
    }

    public function coinSessionStatus(): never
    {
        try{
            [, $device,$userId]=$this->coinOwner();
            $sessionId=strtolower(trim((string)($_GET['session']??'')));
            $session=$this->bridge->coinSessionStatus($sessionId,$userId,(int)$device['id']);
            if(!$session)$this->json(['ok'=>false,'error'=>'Coin session not found.'],404);
            $this->json(['ok'=>true,'session'=>[
                'id'=>$session['session_id'],
                'active'=>$session['active'],
                'credits'=>$session['credits'],
                'coin_count'=>$session['coin_count'],
                'last_coin_credits'=>$session['last_coin_credits']
            ]]);
        }
        catch(Throwable $e){
            $this->json(['ok'=>false,'error'=>$e->getMessage()],422);
        }
    }

    public function coinSessionFinish(): never
    {
        require_csrf();
        try{
            [, $device,$userId]=$this->coinOwner();
            $sessionId=strtolower(trim((string)($_POST['session']??'')));
            if(!$this->bridge->finishCoinSession($sessionId,$userId,(int)$device['id']))throw new \RuntimeException('Coin session not found.');
            $this->json(['ok'=>true]);
        }
        catch(Throwable $e){
            $this->json(['ok'=>false,'error'=>$e->getMessage()],422);
        }
    }

    public function index(): never
    {
        $raw=['router_identity'=>(string)($_GET['router_identity']??''),'server_address'=>(string)($_GET['server_address']??''),'client_ip'=>(string)($_GET['client_ip']??''),'interface'=>(string)($_GET['interface']??''),'mac'=>(string)($_GET['mac']??'')];
        [$d,$errors]=$this->validateQuery($raw);
        if($errors){
            http_response_code(422);
            echo json_encode(['ok'=>false,'errors'=>$errors,'vendos'=>[]]);
            exit;
        }
        $this->headers('application/json; charset=utf-8');
        echo json_encode(['ok'=>true,'vendos'=>$this->api->forHotspot($d['router_identity'],$d['server_address'],$d['client_ip'],$d['interface'])],JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function coinOwner(): array
    {
        $context=$this->hotspotContext();
        $mac=$this->normalizeMac($context['mac']);
        if($mac==='')throw new \RuntimeException('Hotspot device identity is unavailable.');
        $scope=implode('|',array_filter([$context['routerIdentity'],$context['interfaceName']]))?:'global';
        $device=$this->networkDevices->resolve($mac,$scope,$context['ip']);
        if(!$device)throw new \RuntimeException('Hotspot device could not be resolved.');
        $userId=!empty($device['user_id'])?(int)$device['user_id']:null;
        return[$context,$device,$userId];
    }

    private function json(array $payload,int $status=200): never
    {
        http_response_code($status);
        $this->headers('application/json; charset=utf-8');
        echo json_encode($payload,JSON_UNESCAPED_SLASHES);
        exit;
    }

    private function routerId(string $identity): int{
        if($identity==='')return 0;
        $stmt=$this->db->prepare('SELECT id FROM routers WHERE identity=? AND enabled=1 LIMIT 1');
        $stmt->execute([$identity]);
        return (int)($stmt->fetchColumn()?:0);
    }
    private function hasActiveHotspotSession(array $context): bool{
        return trim((string)($context['logoutUrl']??''))!==''||trim((string)($context['refreshUrl']??''))!=='';
    }
    private function debugDetails(array $context): array{
        if(!$this->api->hasDebugTarget($context['routerIdentity']))return [];
        return ['raw'=>$_GET,'processed'=>$context,'matching'=>$this->api->debugForHotspot($context['routerIdentity'],$context['serverAddress'],$context['ip'],$context['interfaceName'])];
    }

    private function portalDevice(array $context): array
    {
        $mac=$this->normalizeMac($context['mac']);
        $fallback=['account'=>'Guest device','points'=>0,'ip'=>$context['ip']!==''?$context['ip']:'—','mac'=>$mac?:'—','last_voucher'=>'','uuid'=>''];
        if($mac==='')return $fallback;
        try{
            $device=$this->networkDevices->resolve($mac,implode('|',array_filter([$context['routerIdentity'],$context['interfaceName']]))?:'global',$context['ip']);
            if(!$device)return $fallback;
            $deviceId=(int)$device['id'];
            $userId=!empty($device['user_id'])?(int)$device['user_id']:null;
            $account='Guest device';
            if($userId){
                $stmt=$this->db->prepare('SELECT name FROM users WHERE id=? AND active=1 LIMIT 1');
                $stmt->execute([$userId]);
                $account=(string)($stmt->fetchColumn()?:$account);
            }
            return ['account'=>$account,'points'=>$this->points->balanceForDevice($deviceId,$userId),'ip'=>$context['ip']!==''?$context['ip']:(string)($device['last_ip']??'—'),'mac'=>$mac,'last_voucher'=>trim((string)($device['last_voucher']??'')),'uuid'=>(string)($device['uuid']??'')];
        }
        catch(Throwable){
            return $fallback;
        }
    }

    private function normalizeMac(string $value): string{
        $value=trim($value);
        if($value===''||str_contains($value,'$('))return '';
        $hex=preg_replace('/[^0-9a-fA-F]/','',$value)??'';
        return strlen($hex)===12?strtoupper(implode(':',str_split($hex,2))):'';
    }
    private function vendoOptions(array $stations): string{
        $options='';
        $selected=false;
        foreach($stations as $station){
            if(trim((string)($station['baseUrl']??''))==='')continue;
            $options.='<option value="'.e($station['id']).'" data-base-url="'.e($station['baseUrl']).'" data-password-mode="'.e($station['passwordMode']).'" data-charging="'.($station['chargingEnabled']?'1':'0').'" data-eload="'.($station['eloadEnabled']?'1':'0').'"'.(!$selected?' selected':'').'>'.e($station['name']).'</option>';
            $selected=true;
        }
        return $options;
    }

    private function hotspotContext(): array
    {
        $session=$this->isMikroTikHandoff()?[]:(is_array($_SESSION['hotspot']??null)?$_SESSION['hotspot']:[]);
        $get=static function(array $session,string ...$keys):string{
            foreach($keys as $key){
                $value=trim((string)($_GET[$key]??''));
                if($value!=='')return $value;
            }
            foreach($keys as $key){
                $value=trim((string)($session[$key]??''));
                if($value!=='')return $value;
            }
            return '';
        };
        $routerIdentity=$get($session,'router_identity','router-identity','identity');
        $serverAddress=$get($session,'server_address','server-address');
        $ip=$get($session,'client_ip','ip');
        $interface=$get($session,'interface','interface_name','interface-name');
        $mac=$get($session,'mac');
        return ['routerIdentity'=>$routerIdentity,'serverAddress'=>$serverAddress,'ip'=>$ip,'interfaceName'=>$interface,'mac'=>$this->normalizeMac($mac),'username'=>$get($session,'username'),'loginUrl'=>$get($session,'login_url','link-login-only'),'originalUrl'=>$get($session,'original_url','link-orig'),'originalUrlEsc'=>$get($session,'original_url_esc','link-orig-esc'),'chapId'=>$get($session,'chap_id','chap-id'),'chapChallenge'=>$get($session,'chap_challenge','chap-challenge'),'error'=>$get($session,'error'),'trial'=>$get($session,'trial'),'macEsc'=>$get($session,'mac_esc','mac-esc'),'logoutUrl'=>$get($session,'logout_url','link-logout'),'refreshUrl'=>$get($session,'status_url','link-status'),'sessionTimeLeft'=>$get($session,'session_time_left','session-time-left'),'bytesIn'=>$get($session,'bytes_in','bytes-in'),'bytesOut'=>$get($session,'bytes_out','bytes-out'),'remainBytesTotal'=>$get($session,'remain_bytes_total','remain-bytes-total')];
    }

    private function isMikroTikHandoff(): bool{
        return isset($_GET['link-login-only'])||isset($_GET['login_url'])||isset($_GET['status_url'])||isset($_GET['logout_url'])||isset($_GET['disconnected']);
    }
    private function sessionContext(array $context): array{
        return ['mac'=>$context['mac'],'mac_esc'=>$context['macEsc']??'','ip'=>$context['ip'],'username'=>$context['username'],'router_identity'=>$context['routerIdentity'],'interface'=>$context['interfaceName'],'ssid'=>'','server_address'=>$context['serverAddress'],'login_url'=>$context['loginUrl'],'original_url'=>$context['originalUrl'],'original_url_esc'=>$context['originalUrlEsc']??'','chap_id'=>$context['chapId'],'chap_challenge'=>$context['chapChallenge'],'error'=>$context['error'],'trial'=>$context['trial']??'','logout_url'=>$context['logoutUrl']??'','status_url'=>$context['refreshUrl']??'','session_time_left'=>$context['sessionTimeLeft']??'','bytes_in'=>$context['bytesIn']??'','bytes_out'=>$context['bytesOut']??'','remain_bytes_total'=>$context['remainBytesTotal']??''];
    }
    private function headers(string $contentType): void{
        header('Content-Type: '.$contentType);
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-store');
    }
    private function validateQuery(array $raw): array{
        $data=[];
        $errors=[];
        foreach(['router_identity'=>160,'server_address'=>45,'client_ip'=>45,'interface'=>128,'mac'=>32] as $key=>$max){
            $value=trim((string)($raw[$key]??''));
            if(strlen($value)>$max){
                $errors[$key]=['The '.$key.' field is too long.'];
                $value=substr($value,0,$max);
            }
            $data[$key]=$value;
        }
        if($data['router_identity']==='')$errors['router_identity']=['The router identity field is required.'];
        return [$data,$errors];
    }
}
