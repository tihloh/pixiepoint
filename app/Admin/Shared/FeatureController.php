<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Shared;

use PDO;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\View;
use Tihloh\Prefab\Logs\Services\LogManager;

abstract class FeatureController
{
    public function __construct(protected PDO $db,protected AuthContext $auth,protected View $view,protected LogManager $logs){
    }

    protected function page(string $title,string $viewFile,array $data=[]):never
    {
        $content=$this->view->renderFile($viewFile,$data);
        if($title!=='Logs'&&$this->auth->can('logs.view')){
            $activity=$this->activityPanel($title);
            if($activity!=='')$content=$this->placeActivityBelowLastPanel($content,$activity);
        }
        $this->view->page($title,$content,true,$this->auth->navigation());
    }

    private function placeActivityBelowLastPanel(string $content,string $activity):string
    {
        $start=strrpos($content,'<section class="panel"');
        if($start===false)return$content.$activity;
        $end=strpos($content,'</section>',$start);
        if($end===false)return$content.$activity;
        $end+=strlen('</section>');
        return substr($content,0,$end).$activity.substr($content,$end);
    }

    private function activityPanel(string $title):string
    {
        $prefixes=match($title){
            'Routers','Router Dashboard','Gateways','Gateway Dashboard'=>['router.','station.','vendo.'],
            'Vendos'=>['vendo_gateway.','vendo.'],
            'Vouchers'=>['voucher.'],
            'Sessions'=>['session.','hotspot.'],
            'Devices'=>['device.','dhcp.'],
            'Sales'=>['sale.','sales.','payment.','coin.','vendo.'],
            'Users','Edit user'=>['user.','account.','group.','permission.'],
            default=>[],
        };
        if(!$prefixes)return'';
        $routerId=in_array($title,['Routers','Gateways'],true)?0:max(0,(int)($_SESSION['pixiepoint_selected_router_id']??0));
        $logs=[];
        foreach($this->logs->recent(250) as $log){
            $action=strtolower((string)($log['action']??''));
            $matches=false;
            foreach($prefixes as $prefix)if(str_starts_with($action,$prefix)){
                $matches=true;
                break;
            }
            if(!$matches)continue;
            if($routerId>0&&!$this->logBelongsToRouter($log,$routerId))continue;
            $logs[]=$log;
            if(count($logs)>=5)break;
        }
        $names=[];
        $stmt=$this->db->prepare('SELECT name,email FROM users WHERE id=? LIMIT 1');
        $actor=function(int|string $id)use(&$names,$stmt):?string{
            $key=(string)$id;
            if(array_key_exists($key,$names))return$names[$key];
            $stmt->execute([$id]);
            $user=$stmt->fetch();
            return$names[$key]=$user?(string)($user['name']?:$user['email']):null;
        };
        $human=array_map(fn(array $log):array=>$this->logs->human($log,$actor),$logs);
        return$this->view->render('partials/activity-log',['logs'=>$human,'scope'=>$title]);
    }

    private function logBelongsToRouter(array $log,int $routerId):bool
    {
        if(($log['subject_type']??null)==='router'&&(int)($log['subject_id']??0)===$routerId)return true;
        $metadata=is_array($log['metadata']??null)?$log['metadata']:[];
        if((int)($metadata['router_id']??0)===$routerId)return true;
        $subjectType=(string)($log['subject_type']??'');
        $subjectId=max(0,(int)($log['subject_id']??0));
        if($subjectId<1)return false;
        $lookup=match($subjectType){
            'voucher','voucher_batch','session','router_login_event'=>'SELECT router_id FROM '.($subjectType==='voucher_batch'?'voucher_batches':($subjectType==='router_login_event'?'router_login_events':$subjectType.'s')).' WHERE id=? LIMIT 1','station','vendo'=>'SELECT router_id FROM vendos WHERE id=? LIMIT 1',default=>null
        };
        if($lookup===null)return false;
        $stmt=$this->db->prepare($lookup);
        $stmt->execute([$subjectId]);
        return(int)$stmt->fetchColumn()===$routerId;
    }

    protected function isPost():bool{
        return strtoupper($_SERVER['REQUEST_METHOD']??'GET')==='POST';
    }
    protected function audit(string $action,string $subjectType,int|string|null $subjectId,string $message,array $metadata=[],array $changes=[]):void{
        $this->logs->record(['action'=>$action,'subject_type'=>$subjectType,'subject_id'=>$subjectId,'actor_id'=>$this->auth->auth()->id(),'message'=>$message,'changes'=>$changes,'metadata'=>$metadata,'ip_address'=>$_SERVER['REMOTE_ADDR']??null,'user_agent'=>$_SERVER['HTTP_USER_AGENT']??null]);
    }
    protected function errors(array $errors):string{
        $messages=[];
        foreach($errors as $fieldErrors)foreach((array)$fieldErrors as $message)$messages[]=e($message);
        return'<div class="alert">'.implode('<br>',$messages?:['Please check the form.']).'</div>';
    }
}
