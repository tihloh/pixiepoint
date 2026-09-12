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
            $type=strtolower((string)$type);
            $key=$type.':'.$id;
            if(array_key_exists($key,$subjects))return $subjects[$key];

            if(in_array($type,['user','account'],true))return $subjects[$key]=$actorResolver($id);

            $map=[
                'router'=>['routers','name','Router'],
                'station'=>['vendos','name','Hotspot station'],
                'vendo'=>['vendos','name','Hotspot station'],
            ];
            if(!isset($map[$type]))return null;

            [$table,$column,$label]=$map[$type];
            $stmt=$this->db->prepare("SELECT {$column} FROM {$table} WHERE id=? LIMIT 1");
            $stmt->execute([$id]);
            $value=$stmt->fetchColumn();
            return $subjects[$key]=$value!==false?($label.' · '.(string)$value):null;
        };

        $this->page('Logs',__DIR__.'/views/index.php',[
            'logs'=>$this->logs->humanRecent(200,0,$actorResolver,$subjectResolver),
        ]);
    }
}
