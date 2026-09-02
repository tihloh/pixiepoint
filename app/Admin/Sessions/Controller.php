<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Sessions;

use PixiePoint\App\Admin\Shared\FeatureController;

final class Controller extends FeatureController
{
    public function index(): never
    {
        $sessions = $this->db->query('SELECT s.*,d.mac,r.name router_name,u.email account_email FROM sessions s LEFT JOIN devices d ON d.id=s.device_id LEFT JOIN routers r ON r.id=s.router_id LEFT JOIN users u ON u.id=s.user_id ORDER BY s.updated_at DESC LIMIT 250')->fetchAll();
        $this->page('Sessions', __DIR__ . '/views/index.php', ['sessions' => $sessions]);
    }
}
