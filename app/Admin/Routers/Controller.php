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

        $selectId = max(0, (int) ($_GET['select'] ?? 0));
        if ($selectId > 0) {
            if (!$access->canView($selectId, $userId, $platformOwner)) {
                $_SESSION['admin_flash'] = '<div class="alert">Router not found or access denied.</div>';
                redirect('/admin/routers');
            }

            $stmt = $this->db->prepare('SELECT id FROM routers WHERE id=? AND enabled=1 LIMIT 1');
            $stmt->execute([$selectId]);
            if (!$stmt->fetchColumn()) {
                $_SESSION['admin_flash'] = '<div class="alert">Router is unavailable.</div>';
                redirect('/admin/routers');
            }

            $_SESSION['pixiepoint_selected_router_id'] = $selectId;

            $return = (string) ($_GET['return'] ?? '/admin/vendos');
            if ($return === '' || !str_starts_with($return, '/') || !preg_match('#^/(?:admin|dashboard)(?:/|$)#', $return)) {
                $return = '/admin/vendos';
            }
            redirect($return);
        }

        if ($this->isPost()) {
            require_csrf();
            $action = (string) ($_POST['action'] ?? '');

            if ($action !== 'update') {
                $_SESSION['admin_flash'] = '<div class="alert">Register new routers from the RouterOS Terminal command on your dashboard.</div>';
                redirect('/admin/routers');
            }

            $result = Input::fromRequest()->process([
                'name' => 'trim|required|string|max:160',
                'public_host' => 'trim|null_if_empty|nullable|string|max:255',
                'location' => 'trim|null_if_empty|nullable|string|max:255',
                'enabled' => 'default:0|integer|min:0|max:1',
            ]);

            if ($result->fails()) {
                $message = $this->errors($result->errors());
            } else {
                $data = $result->validated();

                try {
                    $id = max(0, (int) ($_POST['id'] ?? 0));
                    if ($id < 1 || !$access->canManage($id, $userId, $platformOwner)) {
                        throw new RuntimeException('Router not found or access denied.');
                    }

                    $stmt = $this->db->prepare(
                        'UPDATE routers SET name=?,public_host=?,location=?,enabled=? WHERE id=?',
                    );
                    $stmt->execute([
                        $data['name'],
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
                    );
                    $message = '<div class="alert ok">Router updated.</div>';
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

        foreach ($routers as &$router) {
            $router['can_manage_team'] = $access->canManageTeam(
                (int) $router['id'],
                $userId,
                $platformOwner,
            );
        }
        unset($router);

        $this->page('Routers', __DIR__ . '/views/index.php', [
            'message' => $message,
            'routers' => $routers,
            'canManageRouters' => $this->auth->can('routers.manage'),
            'canCreateRouters' => false,
            'csrf' => csrf_token(),
        ]);
    }
}
