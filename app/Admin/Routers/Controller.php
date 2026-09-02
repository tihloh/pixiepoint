<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Routers;

use PixiePoint\App\Admin\Shared\FeatureController;
use PixiePoint\App\Admin\Shared\RouterAccess;
use RuntimeException;
use Throwable;
use Tihloh\Prefab\Input\Input;

final class Controller extends FeatureController
{
    public function index(): never
    {
        $user = $this->auth->requireAccount();
        $userId = (int) $user['id'];
        $platformOwner = $this->auth->isPlatformOwner();
        $access = new RouterAccess($this->db);

        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        if ($this->isPost()) {
            require_csrf();
            $action = (string) ($_POST['action'] ?? 'create');
            $result = Input::fromRequest()->process([
                'name' => 'trim|required|string|max:160',
                'identity' => 'trim|required|string|max:160',
                'public_host' => 'trim|null_if_empty|nullable|string|max:255',
                'location' => 'trim|null_if_empty|nullable|string|max:255',
                'enabled' => 'default:0|integer|min:0|max:1',
            ]);

            if ($result->fails()) {
                $message = $this->errors($result->errors());
            } else {
                $data = $result->validated();

                try {
                    if ($action === 'update') {
                        $id = max(0, (int) ($_POST['id'] ?? 0));
                        if ($id < 1 || !$access->canManage($id, $userId, $platformOwner)) {
                            throw new RuntimeException('Router not found or access denied.');
                        }

                        $stmt = $this->db->prepare(
                            'UPDATE routers SET name=?,identity=?,public_host=?,location=?,enabled=? WHERE id=?',
                        );
                        $stmt->execute([
                            $data['name'],
                            $data['identity'],
                            $data['public_host'] ?? null,
                            $data['location'] ?? null,
                            (int) ($data['enabled'] ?? 0),
                            $id,
                        ]);

                        $this->audit(
                            'router.updated',
                            'router',
                            $id,
                            'MikroTik router was updated.',
                            ['identity' => $data['identity']],
                        );
                        $message = '<div class="alert ok">Router updated.</div>';
                    } else {
                        $stmt = $this->db->prepare(
                            'INSERT INTO routers(name,identity,public_host,location,api_key) VALUES(?,?,?,?,?)',
                        );
                        $stmt->execute([
                            $data['name'],
                            $data['identity'],
                            $data['public_host'] ?? null,
                            $data['location'] ?? null,
                            bin2hex(random_bytes(24)),
                        ]);

                        $id = (int) $this->db->lastInsertId();
                        $access->addOwner($id, $userId);

                        $this->audit(
                            'router.created',
                            'router',
                            $id,
                            'MikroTik router was registered.',
                            ['identity' => $data['identity']],
                        );
                        $message = '<div class="alert ok">Router registered.</div>';
                    }
                } catch (Throwable $e) {
                    $message = '<div class="alert">The router could not be saved. '
                        . e($e->getMessage())
                        . '</div>';
                }
            }

            $_SESSION['admin_flash'] = $message;
            redirect('/admin/routers');
        }

        if ($platformOwner) {
            $routers = $this->db
                ->query('SELECT * FROM routers ORDER BY created_at DESC')
                ->fetchAll();
        } else {
            $stmt = $this->db->prepare(
                'SELECT r.*,rm.role team_role
                 FROM routers r
                 JOIN router_members rm ON rm.router_id=r.id
                 WHERE rm.user_id=?
                 ORDER BY r.created_at DESC',
            );
            $stmt->execute([$userId]);
            $routers = $stmt->fetchAll();
        }

        $this->page('Routers', __DIR__ . '/views/index.php', [
            'message' => $message,
            'routers' => $routers,
            'canManageRouters' => $this->auth->can('routers.manage'),
            'csrf' => csrf_token(),
        ]);
    }
}
