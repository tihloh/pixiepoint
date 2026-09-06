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

        $routerStmt = $this->db->prepare('SELECT identity FROM routers WHERE id=? LIMIT 1');
        $routerStmt->execute([$routerId]);
        $routerIdentity = trim((string) $routerStmt->fetchColumn());

        $stmt = $this->db->prepare(
            'SELECT d.*,u.email,COUNT(DISTINCT s.id) sessions
             FROM devices d
             LEFT JOIN users u ON u.id=d.user_id
             LEFT JOIN sessions s ON s.device_id=d.id AND s.router_id=?
             WHERE d.id IN (
                 SELECT device_id FROM sessions WHERE router_id=? AND device_id IS NOT NULL
                 UNION
                 SELECT device_id FROM router_login_events WHERE router_id=? AND device_id IS NOT NULL
                 UNION
                 SELECT device_id
                 FROM device_identities
                 WHERE device_id IS NOT NULL
                   AND (? <> "")
                   AND (scope_key=? OR scope_key LIKE CONCAT(?, "|%"))
             )
             GROUP BY d.id
             ORDER BY d.last_seen_at DESC',
        );
        $stmt->execute([$routerId, $routerId, $routerId, $routerIdentity, $routerIdentity, $routerIdentity]);
        $devices = $stmt->fetchAll();

        $this->page('Devices', __DIR__ . '/views/index.php', ['devices' => $devices]);
    }
}
