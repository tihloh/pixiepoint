<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\Logs;

use PixiePoint\App\Admin\Shared\FeatureController;

final class Controller extends FeatureController
{
    public function index(): never
    {
        $this->page('Logs', __DIR__ . '/views/index.php', ['logs' => $this->logs->recent(200)]);
    }
}
