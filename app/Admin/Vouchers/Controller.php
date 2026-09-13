<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Vouchers;

use PDO;
use PixiePoint\App\Admin\Shared\FeatureController;
use PixiePoint\App\Admin\Shared\RouterAccess;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\View;
use PixiePoint\App\Services\Vouchers\VoucherEngine;
use RuntimeException;
use Throwable;
use Tihloh\Prefab\Input\Input;
use Tihloh\Prefab\Logs\Services\LogManager;

final class Controller extends FeatureController
{
    public function __construct(PDO $db,AuthContext $auth,View $view,LogManager $logs,private VoucherEngine $engine){parent::__construct($db,$auth,$view,$logs);}

    public function index(): never
    {
        [$router,$routerId]=$this->selectedRouter();$message=(string)($_SESSION['admin_flash']??'');unset($_SESSION['admin_flash']);
        if($this->isPost())$this->handlePost($routerId);
        $status=in_array((string)($_GET['status']??'active'),['active','archived','all'],true)?(string)($_GET['status']??'active'):'active';
        $where=$status==='archived'?' AND v.archived_at IS NOT NULL':($status==='active'?' AND v.archived_at IS NULL':'');
        $stmt=$this->db->prepare("SELECT v.*,s.name station_name,b.batch_key,b.promo_name,b.platform FROM vouchers v LEFT JOIN vendos s ON s.id=v.station_id LEFT JOIN voucher_batches b ON b.id=v.batch_id WHERE v.router_id=?{$where} ORDER BY v.created_at DESC LIMIT 500");$stmt->execute([$routerId]);
        $stations=$this->db->prepare('SELECT id,name FROM vendos WHERE router_id=? AND enabled=1 ORDER BY name');$stations->execute([$routerId]);
        $batches=$this->db->prepare('SELECT b.*,COUNT(v.id) voucher_count FROM voucher_batches b LEFT JOIN vouchers v ON v.batch_id=b.id WHERE b.router_id=? GROUP BY b.id ORDER BY b.created_at DESC LIMIT 20');$batches->execute([$routerId]);
        $this->page('Vouchers',__DIR__.'/views/index.php',['message'=>$message,'router'=>$router,'vouchers'=>$stmt->fetchAll(),'stations'=>$stations->fetchAll(),'batches'=>$batches->fetchAll(),'platforms'=>$this->engine->platforms(),'status'=>$status,'csrf'=>csrf_token()]);
    }

    public function export(): never
    {
        [$router,$routerId]=$this->selectedRouter();$key=trim((string)($_GET['batch']??''));$stmt=$this->db->prepare('SELECT * FROM voucher_batches WHERE batch_key=? AND router_id=? LIMIT 1');$stmt->execute([$key,$routerId]);$batch=$stmt->fetch();if(!$batch){http_response_code(404);exit('Voucher batch not found.');}
        $station=null;if(!empty($batch['station_id'])){$stmt=$this->db->prepare('SELECT * FROM vendos WHERE id=? AND router_id=? LIMIT 1');$stmt->execute([(int)$batch['station_id'],$routerId]);$station=$stmt->fetch()?:null;}
        $stmt=$this->db->prepare('SELECT * FROM vouchers WHERE batch_id=? ORDER BY id');$stmt->execute([(int)$batch['id']);$adapter=$this->engine->adapter((string)$batch['platform']);
        header('Content-Type: text/plain; charset=utf-8');header('Content-Disposition: attachment; filename="'.$adapter->filename($batch).'"');header('X-Content-Type-Options: nosniff');echo $adapter->render($router,$station,$batch,$stmt->fetchAll());exit;
    }

    private function handlePost(int $routerId): never
    {
        require_csrf();$action=(string)($_POST['action']??'create');
        try{$message=match($action){'generate'=>$this->generate($routerId),'delete'=>$this->deleteOrArchive($routerId),'update'=>$this->save($routerId,true),default=>$this->save($routerId,false)};}
        catch(Throwable $e){$message='<div class="alert">'.e($e->getMessage()?:'The voucher operation failed.').'</div>';}
        $_SESSION['admin_flash']=$message;redirect('/admin/vouchers');
    }

    private function generate(int $routerId): string
    {
        $result=Input::fromRequest()->process(['quantity'=>'required|integer|min:1|max:1000','prefix'=>'trim|uppercase|null_if_empty|nullable|string|max:24','code_length'=>'required|integer|min:4|max:32','promo_name'=>'trim|null_if_empty|nullable|string|max:255','platform'=>'trim|required|string|max:32','platform_profile'=>'trim|null_if_empty|nullable|string|max:128','station_id'=>'default:0|integer|min:0','duration_minutes'=>'required|integer|min:1|max:525600','data_limit_mb'=>'null_if_empty|nullable|integer|min:1','max_devices'=>'required|integer|min:1|max:1000','max_uses'=>'required|integer|min:1|max:1000000','expires_at'=>'trim|null_if_empty|nullable|string|max:32']);
        if($result->fails())throw new RuntimeException(strip_tags($this->errors($result->errors())));$data=$result->validated();$stationId=(int)$data['station_id'];$this->validateStation($stationId,$routerId);$platform=(string)$data['platform'];$this->engine->adapter($platform);$key=strtoupper(bin2hex(random_bytes(8)));$codes=$this->engine->codes((int)$data['quantity'],(string)($data['prefix']??''),(int)$data['code_length'],$this->db->query('SELECT code FROM vouchers')->fetchAll(PDO::FETCH_COLUMN));
        $this->db->beginTransaction();try{$stmt=$this->db->prepare('INSERT INTO voucher_batches(batch_key,router_id,station_id,created_by,platform,platform_profile,promo_name,quantity) VALUES(?,?,?,?,?,?,?,?)');$stmt->execute([$key,$routerId,$stationId?:null,(int)$this->auth->auth()->id(),$platform,$data['platform_profile']??null,$data['promo_name']??null,count($codes)]);$batchId=(int)$this->db->lastInsertId();$stmt=$this->db->prepare('INSERT INTO vouchers(router_id,station_id,batch_id,code,password,label,duration_minutes,data_limit_mb,max_devices,max_uses,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)');foreach($codes as $code)$stmt->execute([$routerId,$stationId?:null,$batchId,$code,bin2hex(random_bytes(8)),$data['promo_name']??null,$data['duration_minutes'],$data['data_limit_mb']??null,$data['max_devices'],$data['max_uses'],$data['expires_at']??null]);$this->db->commit();}catch(Throwable $e){if($this->db->inTransaction())$this->db->rollBack();throw $e;}
        $this->audit('voucher.batch_created','voucher_batch',$batchId,'Voucher batch was generated.',['batch_key'=>$key,'router_id'=>$routerId,'station_id'=>$stationId?:null,'platform'=>$platform,'quantity'=>count($codes)]);
        return '<div class="alert ok">Generated '.count($codes).' vouchers. <a href="/admin/vouchers/export?batch='.rawurlencode($key).'">Download '.$this->engine->adapter($platform)->label().'</a></div>';
    }

    private function deleteOrArchive(int $routerId): string
    {
        $id=max(0,(int)($_POST['id']??0));$stmt=$this->db->prepare('SELECT * FROM vouchers WHERE id=? AND router_id=? LIMIT 1');$stmt->execute([$id,$routerId]);$voucher=$stmt->fetch();if(!$voucher)throw new RuntimeException('Voucher not found.');
        $stmt=$this->db->prepare('SELECT EXISTS(SELECT 1 FROM sessions WHERE voucher_id=? LIMIT 1) OR EXISTS(SELECT 1 FROM router_login_events WHERE voucher_id=? LIMIT 1)');$stmt->execute([$id,$id]);$used=(int)$voucher['uses']>0||(bool)$stmt->fetchColumn();
        if($used){$this->db->prepare('UPDATE vouchers SET enabled=0,archived_at=COALESCE(archived_at,NOW()) WHERE id=? AND router_id=?')->execute([$id,$routerId]);$action='voucher.archived';$verb='archived';}else{$this->db->prepare('DELETE FROM vouchers WHERE id=? AND router_id=?')->execute([$id,$routerId]);$action='voucher.deleted';$verb='permanently deleted';}
        $this->audit($action,'voucher',$id,'Voucher was '.$verb.'.',['code'=>$voucher['code'],'router_id'=>$routerId]);return '<div class="alert ok">Voucher <span class="code">'.e($voucher['code']).'</span> '.$verb.'.</div>';
    }

    private function save(int $routerId,bool $update): string
    {
        $result=Input::fromRequest()->process(['code'=>'trim|uppercase|null_if_empty|nullable|string|max:128','label'=>'trim|null_if_empty|nullable|string|max:255','station_id'=>'default:0|integer|min:0','duration_minutes'=>'required|integer|min:1|max:525600','data_limit_mb'=>'null_if_empty|nullable|integer|min:1','max_devices'=>'required|integer|min:1|max:1000','max_uses'=>'required|integer|min:1|max:1000000','expires_at'=>'trim|null_if_empty|nullable|string|max:32','enabled'=>'default:0|integer|min:0|max:1']);
        if($result->fails())throw new RuntimeException(strip_tags($this->errors($result->errors())));$data=$result->validated();$stationId=(int)$data['station_id'];$this->validateStation($stationId,$routerId);$code=(string)($data['code']??'');if($code==='')$code=$this->engine->codes(1,'',10,$this->db->query('SELECT code FROM vouchers')->fetchAll(PDO::FETCH_COLUMN))[0];
        if($update){$id=max(0,(int)($_POST['id']??0));$check=$this->db->prepare('SELECT id FROM vouchers WHERE id=? AND router_id=? AND archived_at IS NULL LIMIT 1');$check->execute([$id,$routerId]);if(!$check->fetchColumn())throw new RuntimeException('Voucher not found or archived.');$stmt=$this->db->prepare('UPDATE vouchers SET station_id=?,code=?,label=?,duration_minutes=?,data_limit_mb=?,max_devices=?,max_uses=?,expires_at=?,enabled=? WHERE id=? AND router_id=?');$stmt->execute([$stationId?:null,$code,$data['label']??null,$data['duration_minutes'],$data['data_limit_mb']??null,$data['max_devices'],$data['max_uses'],$data['expires_at']??null,(int)($data['enabled']??0),$id,$routerId]);$action='voucher.updated';$verb='updated';}else{$stmt=$this->db->prepare('INSERT INTO vouchers(router_id,station_id,code,password,label,duration_minutes,data_limit_mb,max_devices,max_uses,expires_at) VALUES(?,?,?,?,?,?,?,?,?,?)');$stmt->execute([$routerId,$stationId?:null,$code,bin2hex(random_bytes(8)),$data['label']??null,$data['duration_minutes'],$data['data_limit_mb']??null,$data['max_devices'],$data['max_uses'],$data['expires_at']??null]);$id=(int)$this->db->lastInsertId();$action='voucher.created';$verb='created';}
        $this->audit($action,'voucher',$id,'Voucher was '.$verb.'.',['code'=>$code,'router_id'=>$routerId,'station_id'=>$stationId?:null]);return '<div class="alert ok">Voucher <span class="code">'.e($code).'</span> '.$verb.'.</div>';
    }

    private function validateStation(int $stationId,int $routerId): void
    {
        if($stationId<1)return;$stmt=$this->db->prepare('SELECT id FROM vendos WHERE id=? AND router_id=? LIMIT 1');$stmt->execute([$stationId,$routerId]);if(!$stmt->fetchColumn())throw new RuntimeException('The selected Hotspot Station does not belong to this router.');
    }

    private function selectedRouter(): array
    {
        $user=$this->auth->requireAccount();$userId=(int)$user['id'];$owner=$this->auth->isPlatformOwner();$access=new RouterAccess($this->db);$routerId=max(0,(int)($_SESSION['pixiepoint_selected_router_id']??0));if($routerId<1||!$access->canView($routerId,$userId,$owner)){unset($_SESSION['pixiepoint_selected_router_id']);redirect('/admin/routers');}$stmt=$this->db->prepare('SELECT id,name,identity FROM routers WHERE id=? AND enabled=1 LIMIT 1');$stmt->execute([$routerId]);$router=$stmt->fetch();if(!$router){unset($_SESSION['pixiepoint_selected_router_id']);redirect('/admin/routers');}return [$router,$routerId];
    }
}
