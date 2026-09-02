<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Vendos;

use RuntimeException;
use Throwable;
use PixiePoint\App\Admin\Shared\FeatureController;
use Tihloh\Prefab\Input\Input;

final class Controller extends FeatureController
{
    public function index(): never
    {
        $user=$this->auth->requireAccount(); $userId=(int)$user['id']; $platformOwner=$this->auth->isPlatformOwner(); $message='';
        if($this->isPost()){
            require_csrf(); $action=(string)($_POST['action']??'create');
            $result=Input::fromRequest()->process([
                'name'=>'trim|required|string|max:160','router_id'=>'required|integer|min:1','base_url'=>'trim|required|string|max:255',
                'server_ip'=>'trim|required|string|max:45','client_subnet'=>'trim|null_if_empty|nullable|string|max:64','interface_name'=>'trim|null_if_empty|nullable|string|max:128',
                'password_mode'=>'trim|required|string|max:32','charging_enabled'=>'default:0|integer|min:0|max:1','eload_enabled'=>'default:0|integer|min:0|max:1','enabled'=>'default:0|integer|min:0|max:1'
            ]);
            if($result->fails()) $message=$this->errors($result->errors()); else {
                $data=$result->validated(); $serverIp=trim((string)$data['server_ip']); $subnet=trim((string)($data['client_subnet']??''));
                $baseUrl=$this->normalizeBaseUrl((string)$data['base_url']);
                if($baseUrl===null) $message='<div class="alert">Vendo address must be a valid IP address, hostname, or http:// / https:// URL.</div>';
                elseif(filter_var($serverIp,FILTER_VALIDATE_IP)===false) $message='<div class="alert">Server IP must be a valid IPv4 or IPv6 address.</div>';
                elseif($subnet!==''&&!$this->validCidr($subnet)) $message='<div class="alert">Client subnet must be valid CIDR, for example 10.0.3.0/24.</div>';
                elseif(!in_array((string)$data['password_mode'],['blank','voucher'],true)) $message='<div class="alert">Invalid password mode.</div>';
                else try{
                    $routerCheck=$this->db->prepare('SELECT id FROM routers WHERE id=? AND enabled=1'); $routerCheck->execute([(int)$data['router_id']]); if(!$routerCheck->fetchColumn()) throw new RuntimeException('Router unavailable.');
                    if($action==='update'){
                        $id=max(0,(int)($_POST['id']??0)); if($id<1) throw new RuntimeException('Vendo not found.');
                        $sql='UPDATE vendos SET router_id=?,name=?,base_url=?,server_ip=?,client_subnet=?,interface_name=?,password_mode=?,charging_enabled=?,eload_enabled=?,enabled=? WHERE id=?';
                        $params=[(int)$data['router_id'],$data['name'],$baseUrl,$serverIp,$subnet?:null,$data['interface_name']??null,$data['password_mode'],(int)($data['charging_enabled']??0),(int)($data['eload_enabled']??0),(int)($data['enabled']??0),$id];
                        if(!$platformOwner){$sql.=' AND owner_user_id=?';$params[]=$userId;} $stmt=$this->db->prepare($sql);$stmt->execute($params);
                        if($stmt->rowCount()<1){$exists=$this->db->prepare('SELECT id FROM vendos WHERE id=?'.($platformOwner?'':' AND owner_user_id=?'));$exists->execute($platformOwner?[$id]:[$id,$userId]);if(!$exists->fetchColumn())throw new RuntimeException('Vendo not found.');}
                        $this->audit('vendo.updated','vendo',$id,'PisoWiFi vendo was updated.',['router_id'=>(int)$data['router_id'],'server_ip'=>$serverIp]); $message='<div class="alert ok">Vendo updated.</div>';
                    }else{
                        $stmt=$this->db->prepare('INSERT INTO vendos(owner_user_id,router_id,name,base_url,server_ip,client_subnet,interface_name,password_mode,charging_enabled,eload_enabled) VALUES(?,?,?,?,?,?,?,?,?,?)');
                        $stmt->execute([$userId,(int)$data['router_id'],$data['name'],$baseUrl,$serverIp,$subnet?:null,$data['interface_name']??null,$data['password_mode'],(int)($data['charging_enabled']??0),(int)($data['eload_enabled']??0)]);
                        $id=(int)$this->db->lastInsertId(); $this->audit('vendo.created','vendo',$id,'PisoWiFi vendo was registered.',['router_id'=>(int)$data['router_id'],'server_ip'=>$serverIp]); $message='<div class="alert ok">Vendo added.</div>';
                    }
                }catch(Throwable $e){$message='<div class="alert">The vendo could not be saved. '.e($e->getMessage()).'</div>';}
            }
        }
        $sql='SELECT v.*,r.name router_name,r.identity router_identity,u.email owner_email FROM vendos v JOIN routers r ON r.id=v.router_id LEFT JOIN users u ON u.id=v.owner_user_id';$params=[];if(!$platformOwner){$sql.=' WHERE v.owner_user_id=?';$params[]=$userId;}$sql.=' ORDER BY v.created_at DESC';$stmt=$this->db->prepare($sql);$stmt->execute($params);
        $this->page('Vendos',__DIR__.'/views/index.php',['message'=>$message,'vendos'=>$stmt->fetchAll(),'routers'=>$this->db->query('SELECT id,name,identity FROM routers WHERE enabled=1 ORDER BY name')->fetchAll(),'canManageVendos'=>$this->auth->can('vendos.manage'),'isPlatformOwner'=>$platformOwner,'csrf'=>csrf_token()]);
    }
    private function normalizeBaseUrl(string $value):?string
    {
        $value=trim($value); if($value==='')return null; if(!preg_match('~^https?://~i',$value))$value='http://'.$value;
        $parts=parse_url($value); if(!$parts||!in_array(strtolower((string)($parts['scheme']??'')),['http','https'],true)||trim((string)($parts['host']??''))==='')return null;
        return rtrim($value,'/');
    }
    private function validCidr(string $cidr):bool
    {
        if(!str_contains($cidr,'/'))return false;[$ip,$prefix]=array_pad(explode('/',$cidr,2),2,'');$bin=@inet_pton($ip);if($bin===false)return false;$bits=strlen($bin)*8;
        return filter_var($prefix,FILTER_VALIDATE_INT,['options'=>['min_range'=>0,'max_range'=>$bits]])!==false;
    }
}
