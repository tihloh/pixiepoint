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
            redirect('/admin/routers/' . $selectId);
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

    public function settings(int|string $id): never
    {
        $user = $this->auth->requireAccount();
        $userId = (int) $user['id'];
        $routerId = max(0, (int) $id);
        $platformOwner = $this->auth->isPlatformOwner();
        $access = new RouterAccess($this->db);

        if ($routerId < 1 || !$access->canManage($routerId, $userId, $platformOwner)) {
            $_SESSION['admin_flash'] = '<div class="alert">Router not found or access denied.</div>';
            redirect('/admin/routers');
        }

        $stmt = $this->db->prepare('SELECT * FROM routers WHERE id=? LIMIT 1');
        $stmt->execute([$routerId]);
        $router = $stmt->fetch();

        if (!$router) {
            $_SESSION['admin_flash'] = '<div class="alert">Router not found.</div>';
            redirect('/admin/routers');
        }

        $message = (string) ($_SESSION['admin_flash'] ?? '');
        unset($_SESSION['admin_flash']);

        if ($this->isPost()) {
            require_csrf();

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
                    $stmt = $this->db->prepare(
                        'UPDATE routers SET name=?,public_host=?,location=?,enabled=? WHERE id=?',
                    );
                    $stmt->execute([
                        $data['name'],
                        $data['public_host'] ?? null,
                        $data['location'] ?? null,
                        (int) ($data['enabled'] ?? 0),
                        $routerId,
                    ]);

                    $this->audit(
                        'router.updated',
                        'router',
                        $routerId,
                        'MikroTik router was updated.',
                    );
                    $_SESSION['admin_flash'] = '<div class="alert ok">Router updated.</div>';
                    redirect('/admin/routers/' . $routerId . '/settings');
                } catch (Throwable $e) {
                    $message = '<div class="alert">The router could not be saved. '
                        . e($e->getMessage())
                        . '</div>';
                }
            }
        }

        $this->page('Router Settings', __DIR__ . '/views/settings.php', [
            'router' => $router,
            'message' => $message,
            'csrf' => csrf_token(),
        ]);
    }

    public function dashboard(int|string $id): never
    {
        $user = $this->auth->requireAccount();
        $userId = (int) $user['id'];
        $routerId = max(0, (int) $id);
        $platformOwner = $this->auth->isPlatformOwner();
        $access = new RouterAccess($this->db);

        if ($routerId < 1 || !$access->canView($routerId, $userId, $platformOwner)) {
            $_SESSION['admin_flash'] = '<div class="alert">Router not found or access denied.</div>';
            redirect('/admin/routers');
        }

        $stmt = $this->db->prepare('SELECT * FROM routers WHERE id=? AND enabled=1 LIMIT 1');
        $stmt->execute([$routerId]);
        $router = $stmt->fetch();

        if (!$router) {
            $_SESSION['admin_flash'] = '<div class="alert">Router is unavailable.</div>';
            redirect('/admin/routers');
        }

        $_SESSION['pixiepoint_selected_router_id'] = $routerId;

        $metrics = [];
        $metricQueries = [
            'vendos' => ['Vendos', 'vendos', 'SELECT COUNT(*) FROM vendos WHERE router_id=?'],
            'vouchers' => ['Vouchers', 'vouchers', 'SELECT COUNT(*) FROM vouchers WHERE router_id=?'],
            'devices' => [
                'Devices',
                'devices',
                'SELECT COUNT(DISTINCT device_id) FROM (
                    SELECT device_id FROM sessions WHERE router_id=? AND device_id IS NOT NULL
                    UNION
                    SELECT device_id FROM router_login_events WHERE router_id=? AND device_id IS NOT NULL
                ) router_devices',
            ],
            'sessions' => ['Sessions', 'sessions', 'SELECT COUNT(*) FROM sessions WHERE router_id=?'],
            'sales' => [
                'Sales today',
                'sales',
                "SELECT COALESCE(SUM(amount_pesos),0) FROM router_login_events WHERE router_id=? AND created_at >= CURDATE()",
            ],
        ];

        foreach ($metricQueries as $key => [$label, $permission, $sql]) {
            if (!$this->auth->can($permission . '.view')) {
                continue;
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute(substr_count($sql, '?') === 2 ? [$routerId, $routerId] : [$routerId]);
            $metrics[$key] = ['label' => $label, 'value' => $stmt->fetchColumn()];
        }

        $recentSessions = [];
        if ($this->auth->can('sessions.view')) {
            $stmt = $this->db->prepare(
                'SELECT s.*,d.mac FROM sessions s LEFT JOIN devices d ON d.id=s.device_id '
                . 'WHERE s.router_id=? ORDER BY s.updated_at DESC LIMIT 8',
            );
            $stmt->execute([$routerId]);
            $recentSessions = $stmt->fetchAll();
        }

        $this->page('Router Dashboard', __DIR__ . '/views/dashboard.php', [
            'router' => $router,
            'metrics' => $metrics,
            'recentSessions' => $recentSessions,
            'canManageRouters' => $this->auth->can('routers.manage'),
            'canManageTeam' => $access->canManageTeam($routerId, $userId, $platformOwner),
            'canViewSales' => $this->auth->can('sales.view'),
            'csrf' => csrf_token(),
        ]);
    }
}
