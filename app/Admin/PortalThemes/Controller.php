<?php

declare(strict_types=1);

namespace PixiePoint\App\Admin\PortalThemes;

use PixiePoint\App\Admin\Shared\FeatureController;
use PixiePoint\App\Services\PortalThemeManager;

final class Controller extends FeatureController
{
    public function __construct(
        \PDO $db,
        \PixiePoint\App\Services\AuthContext $auth,
        \PixiePoint\App\Services\View $view,
        \Tihloh\Prefab\Logs\Services\LogManager $logs,
        private PortalThemeManager $themes,
    ) {
        parent::__construct($db, $auth, $view, $logs);
    }

    public function index(): never
    {
        $this->auth->requirePermission('routers.view', $this->view);

        $this->page('Portal Themes', __DIR__ . '/views/index.php', [
            'themes' => $this->themes->all(),
        ]);
    }
}
