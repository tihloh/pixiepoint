<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Permissions;

use PixiePoint\App\Admin\Shared\FeatureController;
use RuntimeException;
use Tihloh\Prefab\Permissions\Services\PermissionManager;
use Tihloh\Prefab\Users\Services\UserManager;

final class Controller extends FeatureController
{
    public function __construct(\PDO $db, \PixiePoint\App\Services\AuthContext $auth, \PixiePoint\App\Services\View $view, \Tihloh\Prefab\Logs\Services\LogManager $logs, private PermissionManager $permissions, private UserManager $users, private string $root)
    { parent::__construct($db, $auth, $view, $logs); }

    public function index(string $id): never
    {
        $actor = $this->auth->requireAccount(); $userId = max(0, (int)$id); $target = $this->users->find($userId);
        if (!$target) { http_response_code(404); exit('User not found.'); }
        $canManageUsers = $this->auth->can('users.manage');
        $canEditUser = $canManageUsers || (int)$actor['id'] === $userId;
        $canPermissions = $this->auth->can('permissions.manage'); $canGroups = $this->auth->can('groups.manage');
        $message = (string)($_SESSION['admin_flash'] ?? ''); unset($_SESSION['admin_flash']);

        if ($this->isPost()) {
            require_csrf();
            try {
                $action = (string)($_POST['action'] ?? 'save');
                if ($action === 'avatar') { if (!$canEditUser) throw new RuntimeException('You cannot change this profile picture.'); $this->saveAvatar($userId, (string)($_POST['avatar_data'] ?? '')); }
                else {
                    if ($canEditUser) $this->saveProfile($userId, $target->toArray(), $canManageUsers);
                    if ($canGroups) $this->users->groups()->syncUserGroups($userId, array_map('intval', (array)($_POST['groups'] ?? [])));
                    if ($canPermissions && ($target->toArray()['platform_role'] ?? '') !== 'platform_owner') $this->savePermissions($userId);
                }
                $_SESSION['admin_flash'] = '<div class="alert ok">Changes saved.</div>';
            } catch (\Throwable $e) { $_SESSION['admin_flash'] = '<div class="alert">' . e($e->getMessage()) . '</div>'; }
            redirect('/admin/users/' . $userId);
        }

        $user = $this->users->find($userId)?->toArray() ?? []; $groupIds = array_map('intval', $this->users->groups()->groupIdsForUser($userId));
        $this->page('Manage user', __DIR__ . '/views/index.php', [
            'user'=>$user,'groups'=>$this->users->groups()->all(),'groupIds'=>$groupIds,'definitions'=>$this->permissions->definitions(),
            'resolved'=>$this->permissions->resolvedFor($userId,$groupIds),'overrides'=>$this->permissions->overridesFor('user',$userId),
            'canEditUser'=>$canEditUser,'canManageUsers'=>$canManageUsers,'canPermissions'=>$canPermissions,'canGroups'=>$canGroups,
            'isPlatformOwnerActor'=>$this->auth->isPlatformOwner(),'message'=>$message,'csrf'=>csrf_token(),
        ]);
    }

    private function saveProfile(int $userId, array $current, bool $canManageUsers): void
    {
        $name=trim((string)($_POST['name']??'')); $email=strtolower(trim((string)($_POST['email']??'')));
        if ($name==='' || !filter_var($email,FILTER_VALIDATE_EMAIL)) throw new RuntimeException('Enter a name and valid email address.');
        $existing=$this->users->findByEmail($email); if ($existing && (int)$existing->id!==$userId) throw new RuntimeException('That email address is already in use.');
        $data=['name'=>$name,'email'=>$email];
        if ($canManageUsers) $data['active']=isset($_POST['active']);
        if ($this->auth->isPlatformOwner() && ($current['platform_role']??'')!=='platform_owner') { $role=(string)($_POST['platform_role']??'member'); if(in_array($role,['member','pisowifi_owner'],true)) $data['platform_role']=$role; }
        $this->users->update($userId,$data,$this->context());
    }

    private function savePermissions(int $userId): void
    {
        $submitted=(array)($_POST['permissions']??[]);
        foreach($this->permissions->definitions() as $permission=>$_definition){$value=(string)($submitted[$permission]??'inherit');if($value==='allow')$this->permissions->set('user',$userId,$permission,true,$this->context());elseif($value==='deny')$this->permissions->set('user',$userId,$permission,false,$this->context());else $this->permissions->clear('user',$userId,$permission,$this->context());}
    }

    private function saveAvatar(int $userId,string $data): void
    {
        if(!preg_match('#^data:image/(?:jpeg|png|webp);base64,(.+)$#',$data,$m))throw new RuntimeException('Choose and crop an image first.');
        $binary=base64_decode($m[1],true);if($binary===false||strlen($binary)>6*1024*1024)throw new RuntimeException('The image is too large.');
        if(!function_exists('imagecreatefromstring')||!function_exists('imagewebp'))throw new RuntimeException('Server image processing is unavailable.');
        $image=@imagecreatefromstring($binary);if(!$image)throw new RuntimeException('Invalid image.');$size=512;$output=imagecreatetruecolor($size,$size);imagecopyresampled($output,$image,0,0,0,0,$size,$size,imagesx($image),imagesy($image));
        $dir=$this->root.'/public/uploads/avatars';if(!is_dir($dir)&&!mkdir($dir,0755,true)&&!is_dir($dir))throw new RuntimeException('Avatar storage is unavailable.');$filename='user-'.$userId.'-'.bin2hex(random_bytes(6)).'.webp';if(!imagewebp($output,$dir.'/'.$filename,82))throw new RuntimeException('Could not store the profile picture.');imagedestroy($image);imagedestroy($output);
        $old=$this->users->find($userId)?->toArray()['avatar_url']??null;$url='/uploads/avatars/'.$filename;$this->users->update($userId,['avatar_url'=>$url],$this->context());if(is_string($old)&&str_starts_with($old,'/uploads/avatars/'))@unlink($this->root.'/public'.$old);
    }

    private function context(): array { return ['actor_id'=>$this->auth->auth()->id(),'ip_address'=>$_SERVER['REMOTE_ADDR']??null,'user_agent'=>$_SERVER['HTTP_USER_AGENT']??null]; }
}
