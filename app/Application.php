<?php

declare(strict_types=1);

namespace PixiePoint\App;

use PixiePoint\App\Api\AccountingController;
use PixiePoint\App\Controllers\AdminController;
use PixiePoint\App\Controllers\AuthController;
use PixiePoint\App\Controllers\DashboardController;
use PixiePoint\App\Controllers\DeviceInfoController;
use PixiePoint\App\Controllers\HotspotController;
use PixiePoint\App\Controllers\VendoController;
use PixiePoint\App\Models\Router as RouterModel;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\DeviceIdentity;
use PixiePoint\App\Services\GoogleOAuth;
use PixiePoint\App\Services\NetworkDeviceIdentity;
use PixiePoint\App\Services\PointWallet;
use PixiePoint\App\Services\PrefabKernel;
use PixiePoint\App\Services\View;
use Tihloh\Prefab\Routes\RouteMatch;

final class Application
{
    public static function run(string $root): never
    {
        require_once $root . '/src/App.php';

        $app = new \App($root);
        self::startSession($root, $app->config);

        $prefab = PrefabKernel::boot($app->db, $root);
        $auth = new AuthContext($prefab['users'], $prefab['auth'], $prefab['permissions']);
        $view = new View($app->config);
        $google = new GoogleOAuth($app->db, $app->config, $prefab['users']);
        $devices = new DeviceIdentity($app->db);
        $networkDevices = new NetworkDeviceIdentity($app->db);
        $points = new PointWallet($app->db);
        $logs = $prefab['logs'];
        $routes = $prefab['routes'];

        $controllers = [
            'auth' => new AuthController($prefab['users'], $auth, $google, $view),
            'dashboard' => new DashboardController($app->db, $auth, $view, $devices, $points),
            'hotspot' => new HotspotController($app->db, new RouterModel($app->db), $auth, $view, $devices),
            'vendo' => new VendoController($app->db),
            'device_info' => new DeviceInfoController($app->db, $networkDevices, $points),
            'admin' => new AdminController($app->db, $auth, $view, $logs),
            'api' => new AccountingController($app->db, $app->config, $networkDevices),
        ];

        $routes->middleware('prefab.access', static function (callable $next, RouteMatch $match) use ($auth, $view, $logs) {
            $meta = $match->route()->metadata();

            if ($meta['auth'] ?? false) $auth->requireAccount();
            if (!empty($meta['permission'])) $auth->requirePermission((string)$meta['permission'], $view);
            if (!empty($meta['log'])) {
                $logs->record([
                    'action' => (string)$meta['log'],
                    'subject_type' => 'route',
                    'subject_id' => $match->route()->routeName(),
                    'actor_id' => $auth->auth()->id(),
                    'metadata' => [
                        'path' => $match->route()->path(),
                        'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                    ],
                    'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                ]);
            }

            return $next();
        });

        (require $root . '/app/Routes/web.php')($routes, $controllers);
        (require $root . '/app/Routes/api.php')($routes, $controllers);

        $routes->fallback(static function () use ($view): never {
            http_response_code(404);
            $view->page('Not found', $view->portalCard('<h1>Page not found</h1><p class="muted">The requested page does not exist.</p>'));
        });

        $routes->dispatch();
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
