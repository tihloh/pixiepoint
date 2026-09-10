<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Vendos;

use PDO;
use PixiePoint\App\Admin\Shared\FeatureController;
use PixiePoint\App\Admin\Shared\RouterAccess;
use PixiePoint\App\Services\PortalFeatureConfig;
use PixiePoint\App\Services\PortalThemeManager;
use PixiePoint\App\Services\View;
use RuntimeException;
use Throwable;
use Tihloh\Prefab\Input\Input;
use Tihloh\Prefab\Logs\Services\LogManager;

final class Controller extends FeatureController
{
    public function __construct(PDO $db, \PixiePoint\App\Services\AuthContext $auth, View $view, LogManager $logs, private PortalThemeManager $themes)
    {
        parent::__construct($db,$auth,$view,$logs);
    }

    public function index(): never
    {
        $user=$this->auth->requireAccount();$userId=(int)$user['id'];$platformOwner=$this->auth->isPlatformOwner();$access=new RouterAccess($this->db);$features=new PortalFeatureConfig($this->db);$message=(string)($_SESSION['admin_flash']??'');unset($_SESSION['admin_flash']);
        $routerId=max(0,(int)($_SESSION['pixiepoint_selected_router_id']??0));if($routerId<1)redirect('/admin/routers');if(!$access->canView($routerId,$userId,$platformOwner)){unset($_SESSION['pixiepoint_selected_router_id']);redirect('/admin/routers');}
        if($this->isPost()){
            require_csrf();$action=(string)($_POST['action']??'create');
            if($action==='save_router_features')$message=$this->saveRouterFeatures($features,$access,$userId,$platformOwner,$routerId);
            elseif($action==='save_station_features')$message=$this->saveStationFeatures($features,$access,$userId,$platformOwner,$routerId);
            elseif($action==='toggle_debug')$message=$this->toggleDebug($access,$userId,$platformOwner,$routerId);
            else $message=$this->saveStation($access,$userId,$platformOwner,$action,$routerId);
            $_SESSION['admin_flash']=$message;redirect('/admin/stations');
        }
        $stmt=$this->db->prepare('SELECT v.*,r.name router_name,r.identity router_identity FROM vendos v JOIN routers r ON r.id=v.router_id WHERE v.router_id=? ORDER BY v.created_at DESC');$stmt->execute([$routerId]);$stations=$stmt->fetchAll();
        foreach($stations as &$station){$station['station_type']=trim((string)($station['base_url']??''))!==''?'vendo':'voucher';$station['feature_settings']=$features->raw('station',(int)$station['id']);$station['resolved_features']=$features->resolve($routerId,(int)$station['id']);}unset($station);
        $routers=$this->db->query('SELECT id,name,identity FROM routers WHERE enabled=1 AND id='.$routerId)->fetchAll();
        $this->page('Hotspot Stations',__DIR__.'/views/index.php',['message'=>$message,'vendos'=>$stations,'routers'=>$routers,'routerBusinessName'=>$this->routerName($routerId),'themes'=>$this->themes->all(),'routerFeatures'=>$features->raw('router',$routerId),'resolvedRouterFeatures'=>$features->resolve($routerId),'canManageVendos'=>$this->auth->can('vendos.manage'),'isPlatformOwner'=>$platformOwner,'csrf'=>csrf_token()]);
    }

    private function saveRouterFeatures(PortalFeatureConfig $features,RouterAccess $access,int $userId,bool $platformOwner,int $routerId): string
    {
        if(!$access->canManage($routerId,$userId,$platformOwner))return '<div class="alert">You cannot manage this router.</div>';$features->saveRouter($routerId,$_POST);$this->audit('router.portal_features.updated','router',$routerId,'Router portal features were updated.');return '<div class="alert ok">Router portal features updated.</div>';
    }

    private function saveStationFeatures(PortalFeatureConfig $features,RouterAccess $access,int $userId,bool $platformOwner,int $routerId): string
    {
        $id=max(0,(int)($_POST['id']??0));$station=$this->findStation($id);if(!$station||(int)$station['router_id']!==$routerId||!$access->canManage($routerId,$userId,$platformOwner))return '<div class="alert">Station not found or access denied.</div>';$features->saveStation($id,$_POST);$this->audit('station.portal_features.updated','station',$id,'Station portal feature overrides were updated.');return '<div class="alert ok">Station portal features updated.</div>';
    }

    private function toggleDebug(RouterAccess $access,int $userId,bool $platformOwner,int $routerId): string
    {
        $id=max(0,(int)($_POST['id']??0));$enabled=(string)($_POST['debug_enabled']??'0')==='1';$station=$this->findStation($id);if(!$station||(int)$station['router_id']!==$routerId||!$access->canManage($routerId,$userId,$platformOwner))return '<div class="alert">Station not found or access denied.</div>';$stmt=$this->db->prepare('UPDATE vendos SET debug_enabled=? WHERE id=?');$stmt->execute([$enabled?1:0,$id]);return '<div class="alert ok">Debug '.($enabled?'enabled':'disabled').' for '.e($station['name']).'.</div>';
    }

    private function saveStation(RouterAccess $access,int $userId,bool $platformOwner,string $action,int $selectedRouterId): string
    {
        $result=Input::fromRequest()->process(['name'=>'trim|required|string|max:160','station_type'=>'trim|required|string|max:32','portal_theme_id'=>'default:0|integer|min:0','router_id'=>'required|integer|min:1','base_url'=>'trim|null_if_empty|nullable|string|max:255','server_ip'=>'trim|required|string|max:45','client_subnet'=>'trim|null_if_empty|nullable|string|max:64','interface_name'=>'trim|null_if_empty|nullable|string|max:128','password_mode'=>'trim|required|string|max:32','charging_enabled'=>'default:0|integer|min:0|max:1','eload_enabled'=>'default:0|integer|min:0|max:1','enabled'=>'default:0|integer|min:0|max:1']);if($result->fails())return $this->errors($result->errors());
        $data=$result->validated();$routerId=(int)$data['router_id'];$type=(string)$data['station_type'];$serverIp=trim((string)$data['server_ip']);$subnet=trim((string)($data['client_subnet']??''));$themeId=$this->validThemeId((int)($data['portal_theme_id']??0));
        if(!in_array($type,['vendo','voucher'],true))return '<div class="alert">Invalid station type.</div>';if($routerId!==$selectedRouterId)return '<div class="alert">Select the router before managing its hotspot stations.</div>';if(filter_var($serverIp,FILTER_VALIDATE_IP)===false)return '<div class="alert">Server IP must be a valid IPv4 or IPv6 address.</div>';if($subnet!==''&&!$this->validCidr($subnet))return '<div class="alert">Client subnet must be valid CIDR.</div>';if(!in_array((string)$data['password_mode'],['blank','voucher'],true))return '<div class="alert">Invalid password mode.</div>';if(!$access->canManage($routerId,$userId,$platformOwner))return '<div class="alert">You do not have access to manage that router.</div>';
        $baseUrl='';$charging=0;$eload=0;if($type==='vendo'){$baseUrl=$this->normalizeBaseUrl((string)($data['base_url']??''))??'';if($baseUrl==='')return '<div class="alert">Vendo controller address is required.</div>';$charging=(int)($data['charging_enabled']??0);$eload=(int)($data['eload_enabled']??0);}
        try{$check=$this->db->prepare('SELECT id FROM routers WHERE id=? AND enabled=1');$check->execute([$routerId]);if(!$check->fetchColumn())throw new RuntimeException('Router unavailable.');$params=[$routerId,$data['name'],$baseUrl,$serverIp,$subnet?:null,$data['interface_name']??null,$themeId?:null,$data['password_mode'],$charging,$eload,(int)($data['enabled']??0)];
            if($action==='update'){$id=max(0,(int)($_POST['id']??0));$existing=$this->findStation($id);if(!$existing||(int)$existing['router_id']!==$selectedRouterId)throw new RuntimeException('Station not found.');$stmt=$this->db->prepare('UPDATE vendos SET router_id=?,name=?,base_url=?,server_ip=?,client_subnet=?,interface_name=?,portal_theme_id=?,password_mode=?,charging_enabled=?,eload_enabled=?,enabled=? WHERE id=?');$stmt->execute([...$params,$id]);return '<div class="alert ok">Station updated.</div>';}
            $stmt=$this->db->prepare('INSERT INTO vendos(owner_user_id,router_id,name,base_url,server_ip,client_subnet,interface_name,portal_theme_id,password_mode,charging_enabled,eload_enabled,enabled) VALUES(NULL,?,?,?,?,?,?,?,?,?,?,?)');$stmt->execute($params);return '<div class="alert ok">Station added.</div>';
        }catch(Throwable $e){return '<div class="alert">The station could not be saved. '.e($e->getMessage()).'</div>';}
    }

    private function findStation(int $id): ?array{if($id<1)return null;$stmt=$this->db->prepare('SELECT id,router_id,name FROM vendos WHERE id=? LIMIT 1');$stmt->execute([$id]);$row=$stmt->fetch();return $row?:null;}
    private function routerName(int $id): string{$stmt=$this->db->prepare('SELECT name FROM routers WHERE id=? LIMIT 1');$stmt->execute([$id]);return trim((string)($stmt->fetchColumn()?:''));}
    private function validThemeId(int $id): ?int{if($id<1)return null;$stmt=$this->db->prepare('SELECT id FROM portal_themes WHERE id=? AND enabled=1 LIMIT 1');$stmt->execute([$id]);return($found=$stmt->fetchColumn())?(int)$found:null;}
    private function normalizeBaseUrl(string $value): ?string{$value=trim($value);if($value==='')return null;if(!preg_match('~^https?://~i',$value))$value='http://'.$value;$parts=parse_url($value);if(!$parts||!in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true)||trim((string)($parts['host']??''))==='')return null;return rtrim($value,'/');}
    private function validCidr(string $cidr): bool{if(!str_contains($cidr,'/'))return false;[$ip,$prefix]=array_pad(explode('/',$cidr,2),2,'');$bin=@inet_pton($ip);if($bin===false)return false;$bits=strlen($bin)*8;return filter_var($prefix,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>$bits]])!==false;}
}
