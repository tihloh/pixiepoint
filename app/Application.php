<?php

declare(strict_types=1);

namespace PixiePoint\App;

use PixiePoint\App\Api\AccountingController;
use PixiePoint\App\Controllers\AdminController;
use PixiePoint\App\Controllers\AuthController;
use PixiePoint\App\Controllers\DashboardController;
use PixiePoint\App\Controllers\HotspotController;
use PixiePoint\App\Http\Request;
use PixiePoint\App\Http\Router as HttpRouter;
use PixiePoint\App\Models\Router as RouterModel;
use PixiePoint\App\Models\User;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\GoogleOAuth;
use PixiePoint\App\Services\View;
use PixiePoint\PrefabAdmin;

final class Application
{
    public static function run(string $root): never
    {
        require_once $root . '/src/App.php';

        $app = new \App($root);
        self::startSession($root, $app->config);

        $prefab = PrefabAdmin::boot($app->db);
        $auth = new AuthContext($app->db, $prefab['auth']);
        $view = new View($app->config);
        $google = new GoogleOAuth($app->db, $app->config);

        $controllers = [
            'auth' => new AuthController(new User($app->db), $auth, $google, $view),
            'dashboard' => new DashboardController($app->db, $auth, $view),
            'hotspot' => new HotspotController($app->db, new RouterModel($app->db), $auth, $view),
            'admin' => new AdminController($app->db, $auth, $view),
            'api' => new AccountingController($app->db, $app->config),
        ];

        $router = new HttpRouter();
        (require $root . '/app/Routes/web.php')($router, $controllers);
        (require $root . '/app/Routes/api.php')($router, $controllers);

        $request = Request::capture();
        if ($router->dispatch($request) === null) {
            http_response_code(404);
            $view->page('Not found', $view->portalCard('<h1>Page not found</h1><p class="muted">The requested page does not exist.</p>'));
        }
        exit;
    }

    private static function startSession(string $root, array $config): void
    {
        $sessionPath = $root . '/data/sessions';
        if (!is_dir($sessionPath)) mkdir($sessionPath, 0775, true);
        session_save_path($sessionPath);
        session_name($config['session_name'] ?? 'pixiepoint_session');
        session_set_cookie_params([
            'httponly' => true,
            'secure' => (bool)($config['cookie_secure'] ?? true) && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}
