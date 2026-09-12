<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Logs;

use PixiePoint\App\Admin\Shared\FeatureController;

final class Controller extends FeatureController
{
    public function index(): never
    {
        $names=[];
        $userStmt=$this->db->prepare('SELECT name,email FROM users WHERE id=? LIMIT 1');
        $actorResolver=function(int|string $id)use(&$names,$userStmt):?string{
            $key=(string)$id;
            if(array_key_exists($key,$names))return $names[$key];
            $userStmt->execute([$id]);
            $user=$userStmt->fetch();
            return $names[$key]=$user?(string)($user['name']?:$user['email']?:('User #'.$id)):null;
        };

        $subjects=[];
        $subjectResolver=function(?string $type,int|string|null $id,array $log)use(&$subjects,$actorResolver):?string{
            if($id===null||$id==='')return null;
            $type=strtolower((string)$type);$key=$type.':'.$id;
            if(array_key_exists($key,$subjects))return $subjects[$key];
            if(in_array($type,['user','account'],true))return $subjects[$key]=$actorResolver($id);
            $map=['router'=>['routers','name'],'station'=>['vendos','name'],'vendo'=>['vendos','name']];
            if(!isset($map[$type]))return null;
            [$table,$column]=$map[$type];
            $stmt=$this->db->prepare("SELECT {$column} FROM {$table} WHERE id=? LIMIT 1");$stmt->execute([$id]);$value=$stmt->fetchColumn();
            return $subjects[$key]=$value!==false?(string)$value:null;
        };

        $logs=$this->logs->humanRecent(200,0,$actorResolver,$subjectResolver);
        foreach($logs as &$log)$log['activity']=$this->activity($log);
        unset($log);

        $this->page('Logs',__DIR__.'/views/index.php',['logs'=>$logs]);
    }

    private function activity(array $log): string
    {
        $who=(string)($log['who']??'Someone');
        $what=trim((string)($log['what']??''));
        $technical=is_array($log['technical']??null)?$log['technical']:[];
        $action=strtolower((string)($technical['action']??''));
        $message=strtolower((string)($technical['message']??''));

        if(str_contains($action,'logout'))return $who.' signed out.';
        if(str_contains($action,'login'))return $who.' signed in.';
        if(str_contains($action,'router')&&str_contains($action,'register'))return $who.' registered '.($what?:'a').' router from RouterOS.';
        if(str_contains($action,'router')&&(str_contains($action,'portal_features')||str_contains($message,'portal feature')))return $who.' updated MikroTik router and '.($what?:'its').' portal features.';
        if(str_contains($action,'router')&&str_contains($action,'updated'))return $who.' updated '.($what?:'the').' MikroTik router.';
        if((str_contains($action,'station')||str_contains($action,'vendo'))&&str_contains($action,'portal_features'))return $who.' updated '.($what?:'the').' hotspot station portal features.';
        if((str_contains($action,'station')||str_contains($action,'vendo'))&&str_contains($action,'updated'))return $who.' updated '.($what?:'the').' hotspot station.';
        if(str_contains($action,'created'))return $who.' created '.($what?:'an item').'.';
        if(str_contains($action,'deleted'))return $who.' deleted '.($what?:'an item').'.';

        $event=trim((string)($log['event']??$log['summary']??''));
        if($event==='')return $who.' performed an activity.';
        if(str_starts_with(strtolower($event),strtolower($who)))return $event;
        return $who.' '.lcfirst($event);
    }
}
