<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Sales;

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
        $selectedRouterId = max(0, (int) ($_SESSION['pixiepoint_selected_router_id'] ?? 0));

        if ($selectedRouterId < 1 || !$access->canView($selectedRouterId, $userId, $platformOwner)) {
            redirect('/admin/routers');
        }

        $routerStmt = $this->db->prepare('SELECT id,name,identity FROM routers WHERE id=? AND enabled=1 LIMIT 1');
        $routerStmt->execute([$selectedRouterId]);
        $router = $routerStmt->fetch();
        if (!$router) {
            unset($_SESSION['pixiepoint_selected_router_id']);
            redirect('/admin/routers');
        }

        $summaryStmt = $this->db->prepare(
            "SELECT COALESCE(SUM(amount_pesos),0) total,COUNT(*) transactions,COALESCE(SUM(points_awarded),0) points
             FROM router_login_events
             WHERE router_id=? AND created_at >= CURDATE()",
        );
        $summaryStmt->execute([$selectedRouterId]);
        $summary = $summaryStmt->fetch() ?: [];

        $eventsStmt = $this->db->prepare(
            'SELECT e.*,r.name router_name,d.mac device_mac
             FROM router_login_events e
             JOIN routers r ON r.id=e.router_id
             LEFT JOIN devices d ON d.id=e.device_id
             WHERE e.router_id=?
             ORDER BY e.created_at DESC
             LIMIT 250',
        );
        $eventsStmt->execute([$selectedRouterId]);

        $this->page('Sales', __DIR__ . '/views/index.php', [
            'summary' => $summary,
            'events' => $eventsStmt->fetchAll(),
            'router' => $router,
        ]);
    }
}
