<?php

declare(strict_types=1);

namespace PixiePoint\App\Controllers;

use PDO;
use PixiePoint\App\Portal\Adapters\PlatformAdapter;
use PixiePoint\App\Portal\ThemeEngine;
use PixiePoint\App\Services\NetworkDeviceIdentity;
use PixiePoint\App\Services\PointWallet;
use PixiePoint\App\Services\PortalFeatureConfig;
use PixiePoint\App\Services\PortalThemeManager;
use Throwable;

final class HostedPortalController
{
    public function __construct(
        private PDO $db,
        private PortalThemeManager $themes,
        private ThemeEngine $themeEngine,
        private PlatformAdapter $portalAdapter,
        private NetworkDeviceIdentity $networkDevices,
        private PointWallet $points,
    ) {
    }

    public function portal(): never
    {
        $context=$this->hotspotContext();
        $router=$this->router($context['routerIdentity']);
        $routerId=(int)($router['id']??0);
        $features=(new PortalFeatureConfig($this->db))->resolve($routerId);
        $theme=$this->themes->resolveByRouterIdentity($context['routerIdentity']);
        $device=$this->portalDevice($context);
        $auth=$this->hasActiveHotspotSession();
        $feature=static fn(string $key,bool $fallback=false):bool=>isset($features[$key])?(bool)$features[$key]['enabled']:$fallback;

        $html=$this->themeEngine->render($theme,'portal.html',$this->portalAdapter,[
            'portal'=>[
                'auth'=>$auth,
                'name'=>(string)($router['name']??($context['routerIdentity']?:'PixiePoint')),
                'device'=>$device,
                'features'=>$features,
                'trial_minutes'=>(int)($features['trial']['config']['minutes']??10),
                'convert_points'=>(int)($features['points_convert']['config']['points']??10),
                'convert_minutes'=>(int)($features['points_convert']['config']['minutes']??5),
            ],
            'context'=>$context,
        ],[
            'voucher_login'=>$feature('voucher_login',true),
            'member_login'=>$feature('member_login'),
            'qr_scan'=>$feature('qr_scan'),
            'trial'=>$feature('trial'),
            'points'=>$feature('points'),
            'points_convert'=>$feature('points_convert'),
            'points_play'=>$feature('points_play'),
            'points_share'=>$feature('points_share'),
        ]);

        $this->headers();
        echo $html;
        exit;
    }

    public function status(): never{$this->portal();}

    private function router(string $identity): array
    {
        if($identity==='')return[];
        $stmt=$this->db->prepare('SELECT id,name,identity FROM routers WHERE identity=? AND enabled=1 LIMIT 1');
        $stmt->execute([$identity]);
        return $stmt->fetch()?:[];
    }

    private function portalDevice(array $context): array
    {
        $mac=$this->normalizeMac($context['mac']);
        $fallback=['account'=>'Guest device','points'=>0,'ip'=>$context['ip']!==''?$context['ip']:'—','mac'=>$mac?:'—','last_voucher'=>'','uuid'=>''];
        if($mac==='')return$fallback;
        try{
            $device=$this->networkDevices->resolve($mac,implode('|',array_filter([$context['routerIdentity'],$context['interfaceName']]))?:'global',$context['ip']);
            if(!$device)return$fallback;
            $deviceId=(int)$device['id'];$userId=!empty($device['user_id'])?(int)$device['user_id']:null;$account='Guest device';
            if($userId){$stmt=$this->db->prepare('SELECT name FROM users WHERE id=? AND active=1 LIMIT 1');$stmt->execute([$userId]);$account=(string)($stmt->fetchColumn()?:$account);}
            return['account'=>$account,'points'=>$this->points->balanceForDevice($deviceId,$userId),'ip'=>$context['ip']!==''?$context['ip']:(string)($device['last_ip']??'—'),'mac'=>$mac,'last_voucher'=>trim((string)($device['last_voucher']??'')),'uuid'=>(string)($device['uuid']??'')];
        }catch(Throwable){return$fallback;}
    }

    private function hotspotContext(): array
    {
        return[
            'routerIdentity'=>$this->clean((string)($_GET['router_identity']??''),160),
            'serverAddress'=>$this->clean((string)($_GET['server_address']??''),45),
            'ip'=>$this->clean((string)($_GET['client_ip']??''),45),
            'interfaceName'=>$this->clean((string)($_GET['interface']??''),128),
            'mac'=>$this->normalizeMac((string)($_GET['mac']??'')),
        ];
    }

    private function clean(string $value,int $max): string
    {
        $value=trim($value);
        return str_contains($value,'$(')?'':substr($value,0,$max);
    }

    private function normalizeMac(string $value): string
    {
        $value=trim($value);
        if($value===''||str_contains($value,'$('))return'';
        $hex=preg_replace('/[^0-9a-fA-F]/','',$value)??'';
        return strlen($hex)===12?strtoupper(implode(':',str_split($hex,2))):'';
    }

    private function hasActiveHotspotSession(): bool
    {
        foreach(['status_url','logout_url'] as $key){$value=trim((string)($_GET[$key]??''));if($value!==''&&!str_contains($value,'$('))return true;}
        return false;
    }

    private function headers(): void
    {
        header('Content-Type: text/html; charset=utf-8');
        header('Access-Control-Allow-Origin: *');
        header('Cache-Control: no-store');
    }
}
