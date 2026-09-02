<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Sales;

use PixiePoint\App\Admin\Shared\FeatureController;

final class Controller extends FeatureController
{
    public function index(): never
    {
        $summary = $this->db->query('SELECT COALESCE(SUM(amount_pesos),0) total,COUNT(*) transactions,COALESCE(SUM(points_awarded),0) points FROM router_login_events WHERE created_at >= CURDATE()')->fetch() ?: [];
        $events = $this->db->query('SELECT e.*,r.name router_name,d.mac device_mac FROM router_login_events e JOIN routers r ON r.id=e.router_id LEFT JOIN devices d ON d.id=e.device_id ORDER BY e.created_at DESC LIMIT 250')->fetchAll();
        $this->page('Sales', __DIR__ . '/views/index.php', ['summary' => $summary, 'events' => $events]);
    }
}
