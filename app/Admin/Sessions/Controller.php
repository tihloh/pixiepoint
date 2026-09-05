<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Sessions;

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
            'SELECT s.*,d.mac,r.name router_name,u.email account_email
             FROM sessions s
             LEFT JOIN devices d ON d.id=s.device_id
             LEFT JOIN routers r ON r.id=s.router_id
             LEFT JOIN users u ON u.id=s.user_id
             WHERE s.router_id=?
             ORDER BY s.updated_at DESC
             LIMIT 250',
        );
        $stmt->execute([$routerId]);
        $sessions = $stmt->fetchAll();

        $this->page('Sessions', __DIR__ . '/views/index.php', ['sessions' => $sessions]);
    }
}
