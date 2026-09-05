<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Devices;

use PixiePoint\App\Admin\Shared\FeatureController;
use PixiePoint\App\Admin\Shared\RouterAccess;

final class Controller extends FeatureController
{
    public function index(): never
    {
        $user = $this->auth->requireAccount();
        $userId = (int) $user['id'];
        $platformOwner = $this->auth->isPlatformOwner();
        $access = new RouterAccess($this->db);
        $routerId = max(0, (int) ($_SESSION['pixiepoint_selected_router_id'] ?? 0));

        if ($routerId < 1 || !$access->canView($routerId, $userId, $platformOwner)) {
            unset($_SESSION['pixiepoint_selected_router_id']);
            redirect('/admin/routers');
        }

        $stmt = $this->db->prepare(
            'SELECT d.*,u.email,COUNT(s.id) sessions
             FROM devices d
             LEFT JOIN users u ON u.id=d.user_id
             LEFT JOIN sessions s ON s.device_id=d.id
             WHERE d.router_id=?
             GROUP BY d.id
             ORDER BY d.last_seen_at DESC',
        );
        $stmt->execute([$routerId]);
        $devices = $stmt->fetchAll();

        $this->page('Devices', __DIR__ . '/views/index.php', ['devices' => $devices]);
    }
}
