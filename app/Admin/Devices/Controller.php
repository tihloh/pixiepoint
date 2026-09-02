<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Devices;

use PixiePoint\App\Admin\Shared\FeatureController;

final class Controller extends FeatureController
{
    public function index(): never
    {
        $devices = $this->db->query('SELECT d.*,u.email,COUNT(s.id) sessions FROM devices d LEFT JOIN users u ON u.id=d.user_id LEFT JOIN sessions s ON s.device_id=d.id GROUP BY d.id ORDER BY d.last_seen_at DESC')->fetchAll();
        $this->page('Devices', __DIR__ . '/views/index.php', ['devices' => $devices]);
    }
}
