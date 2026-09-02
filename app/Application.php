<?php

declare(strict_types=1);

namespace PixiePoint\App;

use PixiePoint\App\Admin\Devices\Controller as DevicesController;
use PixiePoint\App\Admin\Logs\Controller as LogsController;
use PixiePoint\App\Admin\Routers\AgentController as RouterAgentController;
use PixiePoint\App\Admin\Routers\CommandQueue as RouterCommandQueue;
use PixiePoint\App\Admin\Routers\Controller as RoutersController;
use PixiePoint\App\Admin\Routers\RegistrationController as RouterRegistrationController;
use PixiePoint\App\Admin\Routers\TeamController as RouterTeamController;
use PixiePoint\App\Admin\Sales\Controller as SalesController;
use PixiePoint\App\Admin\Sessions\Controller as SessionsController;
use PixiePoint\App\Admin\Vendos\Api as VendoApi;
use PixiePoint\App\Admin\Vendos\Controller as VendosController;
use PixiePoint\App\Admin\Vendos\HotspotController as VendoHotspotController;
use PixiePoint\App\Admin\Vouchers\Controller as VouchersController;
use PixiePoint\App\Api\AccountingController;
use PixiePoint\App\Controllers\AuthController;
use PixiePoint\App\Controllers\DashboardController;
use PixiePoint\App\Controllers\DeviceInfoController;
use PixiePoint\App\Controllers\HotspotController;
use PixiePoint\App\Models\Router as RouterModel;
use PixiePoint\App\Services\AuthContext;
use PixiePoint\App\Services\DeviceIdentity;
use PixiePoint\App\Services\GoogleOAuth;
use PixiePoint\App\Services\NetworkDeviceIdentity;
use PixiePoint\App\Services\PointWallet;
use PixiePoint\App\Services\PrefabKernel;
use PixiePoint\App\Services\View;
use Tihloh\Prefab\Routes\RouteMatch;

/**
 * Boots PixiePoint, wires application services/controllers, and dispatches routes.
 *
 * Keep business logic out of this class. It should only assemble dependencies
 * and configure the request lifecycle.
 */
final class Application
{
    public static function run(string $root): never
    {
        require_once $root . '/src/App.php';

        $app = new \App($root);
        self::startSession($app->config);

        // Prefab owns the reusable platform services used throughout PixiePoint.
        $prefab = PrefabKernel::boot($app->db, $root);
        $auth = new AuthContext(
            $prefab['users'],
            $prefab['auth'],
            $prefab['permissions'],
            $app->db,
        );

        // Application-level services shared by multiple features.
        $view = new View($app->config);
        $google = new GoogleOAuth($app->db, $app->config, $prefab['users']);
        $devices = new DeviceIdentity($app->db);
        $networkDevices = new NetworkDeviceIdentity($app->db);
        $points = new PointWallet($app->db);
        $logs = $prefab['logs'];
        $routes = $prefab['routes'];
        $vendoApi = new VendoApi($app->db);
        $routerQueue = new RouterCommandQueue($app->db);

        // Controllers are registered by a stable key so feature route files can
        // stay small and avoid constructing dependencies themselves.
        $controllers = [
            'auth' => new AuthController(
                $prefab['users'],
                $auth,
                $google,
                $view,
                $app->db,
            ),
            'dashboard' => new DashboardController(
                $app->db,
                $auth,
                $view,
                $devices,
                $points,
            ),
            'hotspot' => new HotspotController(
                $app->db,
                new RouterModel($app->db),
                $auth,
                $view,
                $devices,
            ),
            'device_info' => new DeviceInfoController(
                $app->db,
                $networkDevices,
                $points,
            ),
            'admin.routers' => new RoutersController(
                $app->db,
                $auth,
                $view,
                $logs,
            ),
            'admin.router-team' => new RouterTeamController(
                $app->db,
                $auth,
                $view,
                $logs,
            ),
            'router.registration' => new RouterRegistrationController(
                $app->db,
                $logs,
            ),
            'router.agent' => new RouterAgentController(
                $app->db,
                $routerQueue,
            ),
            'admin.vendos' => new VendosController(
                $app->db,
                $auth,
                $view,
                $logs,
            ),
            'vendos.hotspot' => new VendoHotspotController($vendoApi, $view),
            'admin.vouchers' => new VouchersController(
                $app->db,
                $auth,
                $view,
                $logs,
            ),
            'admin.devices' => new DevicesController(
                $app->db,
                $auth,
                $view,
                $logs,
            ),
            'admin.sessions' => new SessionsController(
                $app->db,
                $auth,
                $view,
                $logs,
            ),
            'admin.sales' => new SalesController(
                $app->db,
                $auth,
                $view,
                $logs,
            ),
            'admin.logs' => new LogsController(
                $app->db,
                $auth,
                $view,
                $logs,
            ),
            'api' => new AccountingController(
                $app->db,
                $app->config,
                $networkDevices,
            ),
        ];

        // Central access middleware applies authentication, permissions, and
        // optional route-level audit logging before a controller is executed.
        $routes->middleware(
            'prefab.access',
            static function (
                callable $next,
                RouteMatch $match,
            ) use ($auth, $view, $logs) {
                $route = $match->route();
                $meta = $route->metadata();

                if ($meta['auth'] ?? false) {
                    $auth->requireAccount();
                }

                if (!empty($meta['permission'])) {
                    $auth->requirePermission((string) $meta['permission'], $view);
                }

                if (!empty($meta['log'])) {
                    $logs->record([
                        'action' => (string) $meta['log'],
                        'subject_type' => 'route',
                        'subject_id' => $route->routeName(),
                        'actor_id' => $auth->auth()->id(),
                        'metadata' => [
                            'path' => $route->path(),
                            'method' => $_SERVER['REQUEST_METHOD'] ?? 'GET',
                        ],
                        'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                    ]);
                }

                return $next();
            },
        );

        // Each route file delegates to feature-local route declarations.
        (require $root . '/app/Routes/web.php')($routes, $controllers);
        (require $root . '/app/Routes/api.php')($routes, $controllers);

        $routes->fallback(
            static function () use ($view): never {
                http_response_code(404);
                $view->page(
                    'Not found',
                    $view->portalCard(
                        '<h1>Page not found</h1>'
                        . '<p class="muted">The requested page does not exist.</p>',
                    ),
                );
            },
        );

        $routes->dispatch();
        exit;
    }

    /**
     * Starts the application session with secure browser defaults.
     */
    private static function startSession(array $config): void
    {
        session_save_path(sys_get_temp_dir());
        session_name($config['session_name'] ?? 'pixiepoint_session');

        session_set_cookie_params([
            'httponly' => true,
            'secure' => (bool) ($config['cookie_secure'] ?? true)
                && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
            'samesite' => 'Lax',
            'path' => '/',
        ]);

        session_start();
    }
}
