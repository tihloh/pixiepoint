<?php

declare(strict_types=1);

use PixiePoint\AdminUserFactory;
use Tihloh\Prefab\Auth\Services\AuthManager;
use Tihloh\Prefab\Users\Mapping\UserMap;
use Tihloh\Prefab\Users\Repositories\PdoUserProvider;
use Tihloh\Prefab\Users\Services\UserManager;

$root = dirname(__DIR__, 2);

require $root . '/src/App.php';

if (!is_file($root . '/vendor/autoload.php')) {
    http_response_code(503);
    exit('PixiePoint admin dependencies are not installed. Run composer install.');
}

require $root . '/vendor/autoload.php';

$app = new App($root);
$sessionPath = $root . '/data/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_save_path($sessionPath);
    session_name($app->config['session_name'] ?? 'pixiepoint_session');
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (bool)($app->config['cookie_secure'] ?? true) && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

$adminMap = new UserMap(
    table: 'admins',
    id: 'id',
    name: 'name',
    email: 'email',
    active: null,
    attributes: ['password_hash' => 'password_hash'],
    allowDelete: false,
);

$adminProvider = new PdoUserProvider($app->db, $adminMap, new AdminUserFactory());
$users = new UserManager($adminProvider);
$auth = new AuthManager();
$page = strtolower(trim((string)($_GET['page'] ?? 'dashboard')));
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function admin_total(): int
{
    global $app;
    return (int)$app->db->query('SELECT COUNT(*) FROM admins')->fetchColumn();
}

function admin_url(string $page = 'dashboard'): string
{
    return $page === 'dashboard' ? '/admin/' : '/admin/?page=' . rawurlencode($page);
}

function admin_render(string $title, string $content, bool $authenticated = true): never
{
    global $app, $auth;

    $name = e($app->config['app_name'] ?? 'PixiePoint Wi-Fi');
    $current = $authenticated ? $auth->user() : null;
    $nav = '';

    if ($authenticated) {
        $links = [
            'dashboard' => 'Dashboard',
            'routers' => 'Routers',
            'vouchers' => 'Vouchers',
            'devices' => 'Devices',
            'sessions' => 'Sessions',
            'administrators' => 'Administrators',
        ];
        $items = '';
        foreach ($links as $route => $label) {
            $items .= '<a href="' . e(admin_url($route)) . '">' . e($label) . '</a>';
        }
        $items .= '<a href="' . e(admin_url('logout')) . '">Log out</a>';
        $nav = '<nav><div class="wrap"><strong>' . $name . '</strong><div class="navlinks">' . $items . '</div></div></nav>';
    }

    $mainClass = $authenticated ? 'wrap main' : 'portal';
    $subtitle = $authenticated && $current ? '<div class="muted">Signed in as ' . e($current->name ?: $current->email ?: ('Admin #' . $current->id)) . '</div>' : '';

    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#07111f"><title>' . e($title) . ' · ' . $name . '</title><link rel="stylesheet" href="/assets/app.css"></head><body>' . $nav . '<main class="' . $mainClass . '">' . $subtitle . $content . '</main></body></html>';
    exit;
}

function admin_card(string $body): string
{
    return '<section class="card"><div class="brand"><div class="logo">P</div><div><strong>PixiePoint Admin</strong><div class="muted">Powered by Tihloh Prefab</div></div></div>' . $body . '</section>';
}

if (admin_total() === 0) {
    $error = '';
    if ($method === 'POST') {
        require_csrf();
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            $error = '<div class="alert">Use a valid name and email plus a password of at least 12 characters.</div>';
        } else {
            try {
                $users->create([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                redirect('/admin/');
            } catch (Throwable $exception) {
                $error = '<div class="alert">The administrator could not be created.</div>';
            }
        }
    }

    admin_render('Initial setup', admin_card('<h1>Create administrator</h1><p class="muted">Complete the one-time PixiePoint setup.</p>' . $error . '<form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="field"><label>Name</label><input name="name" required autofocus></div><div class="field"><label>Email</label><input name="email" type="email" required></div><div class="field"><label>Password</label><input name="password" type="password" minlength="12" required></div><button class="button full">Create administrator</button></form>'), false);
}

if ($page === 'logout') {
    $auth->logout();
    redirect('/admin/');
}

if (!$auth->check()) {
    $error = '';
    if ($method === 'POST') {
        require_csrf();
        $result = $auth->attempt(
            strtolower(trim((string)($_POST['email'] ?? ''))),
            (string)($_POST['password'] ?? ''),
        );
        if ($result->success) {
            redirect('/admin/');
        }
        $error = '<div class="alert">The email or password is incorrect.</div>';
    }

    admin_render('Admin login', admin_card('<h1>Management login</h1><p class="muted">Sign in to manage PixiePoint.</p>' . $error . '<form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="field"><label>Email</label><input name="email" type="email" autocomplete="username" required autofocus></div><div class="field"><label>Password</label><input name="password" type="password" autocomplete="current-password" required></div><button class="button full">Log in</button></form>'), false);
}

if ($page === 'dashboard') {
    $metrics = [
        'Routers' => (int)$app->db->query('SELECT COUNT(*) FROM routers WHERE enabled=1')->fetchColumn(),
        'Active sessions' => (int)$app->db->query("SELECT COUNT(*) FROM sessions WHERE status='active'")->fetchColumn(),
        'Known devices' => (int)$app->db->query('SELECT COUNT(*) FROM devices')->fetchColumn(),
        'Available vouchers' => (int)$app->db->query('SELECT COUNT(*) FROM vouchers WHERE enabled=1 AND uses<max_uses')->fetchColumn(),
    ];
    $cards = '';
    foreach ($metrics as $label => $value) {
        $cards .= '<div class="metric"><small>' . e($label) . '</small><strong>' . e($value) . '</strong></div>';
    }

    $recent = $app->db->query('SELECT s.*,d.mac,r.name router_name FROM sessions s LEFT JOIN devices d ON d.id=s.device_id LEFT JOIN routers r ON r.id=s.router_id ORDER BY s.updated_at DESC LIMIT 8')->fetchAll();
    $rows = '';
    foreach ($recent as $session) {
        $rows .= '<tr><td>' . e($session['username'] ?: '—') . '</td><td class="code">' . e($session['mac'] ?: '—') . '</td><td>' . e($session['router_name'] ?: '—') . '</td><td><span class="badge ' . ($session['status'] === 'active' ? '' : 'off') . '">' . e($session['status']) . '</span></td><td>' . e($session['updated_at']) . '</td></tr>';
    }

    admin_render('Dashboard', '<div class="heading"><div><h1>Network overview</h1><p class="muted">Live PixiePoint hotspot status.</p></div></div><section class="grid">' . $cards . '</section><section class="panel"><h2>Recent sessions</h2><table><thead><tr><th>User</th><th>Device</th><th>Router</th><th>Status</th><th>Updated</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="5" class="empty">No sessions recorded yet.</td></tr>') . '</tbody></table></section>');
}

if ($page === 'routers') {
    $message = '';
    if ($method === 'POST') {
        require_csrf();
        $name = trim((string)($_POST['name'] ?? ''));
        $identity = trim((string)($_POST['identity'] ?? ''));
        if ($name !== '' && $identity !== '') {
            try {
                $stmt = $app->db->prepare('INSERT INTO routers(name,identity,public_host,location,api_key) VALUES(?,?,?,?,?)');
                $stmt->execute([$name, $identity, trim((string)($_POST['public_host'] ?? '')), trim((string)($_POST['location'] ?? '')), bin2hex(random_bytes(24))]);
                $message = '<div class="alert ok">Router registered.</div>';
            } catch (PDOException $exception) {
                $message = '<div class="alert">That RouterOS identity is already registered.</div>';
            }
        }
    }

    $rows = '';
    foreach ($app->db->query('SELECT * FROM routers ORDER BY created_at DESC') as $router) {
        $rows .= '<tr><td>' . e($router['name']) . '<div class="muted">' . e($router['location']) . '</div></td><td class="code">' . e($router['identity']) . '</td><td>' . e($router['public_host'] ?: '—') . '</td><td><span class="badge ' . ($router['enabled'] ? '' : 'off') . '">' . ($router['enabled'] ? 'Enabled' : 'Disabled') . '</span></td><td>' . e($router['last_seen_at'] ?: 'Never') . '</td></tr>';
    }

    admin_render('Routers', '<div class="heading"><div><h1>Routers</h1><p class="muted">Register each MikroTik using its exact RouterOS identity.</p></div></div>' . $message . '<section class="panel"><h2>Add router</h2><form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="form-grid"><div class="field"><label>Display name</label><input name="name" required></div><div class="field"><label>RouterOS identity</label><input name="identity" required></div><div class="field"><label>Public hostname / VPN IP</label><input name="public_host"></div><div class="field"><label>Location</label><input name="location"></div></div><button class="button">Register router</button></form></section><section class="panel"><table><thead><tr><th>Router</th><th>Identity</th><th>Address</th><th>Status</th><th>Last seen</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="5" class="empty">No routers registered.</td></tr>') . '</tbody></table></section>');
}

if ($page === 'vouchers') {
    $message = '';
    if ($method === 'POST') {
        require_csrf();
        $code = strtoupper(trim((string)($_POST['code'] ?? '')) ?: substr(strtoupper(bin2hex(random_bytes(5))), 0, 10));
        $password = bin2hex(random_bytes(8));
        try {
            $stmt = $app->db->prepare('INSERT INTO vouchers(code,password,label,duration_minutes,data_limit_mb,max_devices,max_uses,expires_at) VALUES(?,?,?,?,?,?,?,?)');
            $stmt->execute([$code, $password, trim((string)($_POST['label'] ?? '')), max(1, (int)($_POST['duration_minutes'] ?? 60)), ($_POST['data_limit_mb'] ?? '') === '' ? null : max(1, (int)$_POST['data_limit_mb']), max(1, (int)($_POST['max_devices'] ?? 1)), max(1, (int)($_POST['max_uses'] ?? 1)), trim((string)($_POST['expires_at'] ?? '')) ?: null]);
            $message = '<div class="alert ok">Voucher <span class="code">' . e($code) . '</span> created.</div>';
        } catch (PDOException $exception) {
            $message = '<div class="alert">That voucher code already exists.</div>';
        }
    }

    $rows = '';
    foreach ($app->db->query('SELECT * FROM vouchers ORDER BY created_at DESC LIMIT 100') as $voucher) {
        $rows .= '<tr><td class="code">' . e($voucher['code']) . '</td><td>' . e($voucher['label'] ?: '—') . '</td><td>' . e($voucher['duration_minutes']) . ' min</td><td>' . e($voucher['uses'] . ' / ' . $voucher['max_uses']) . '</td><td>' . e($voucher['expires_at'] ?: 'Never') . '</td><td><span class="badge ' . ($voucher['enabled'] ? '' : 'off') . '">' . ($voucher['enabled'] ? 'Enabled' : 'Disabled') . '</span></td></tr>';
    }

    admin_render('Vouchers', '<div class="heading"><div><h1>Access vouchers</h1><p class="muted">Issue time- and usage-limited Wi-Fi credentials.</p></div></div>' . $message . '<section class="panel"><h2>Create voucher</h2><form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="form-grid"><div class="field"><label>Code (blank for automatic)</label><input name="code"></div><div class="field"><label>Label</label><input name="label"></div><div class="field"><label>Duration in minutes</label><input name="duration_minutes" type="number" min="1" value="60"></div><div class="field"><label>Data limit in MB (optional)</label><input name="data_limit_mb" type="number" min="1"></div><div class="field"><label>Maximum devices</label><input name="max_devices" type="number" min="1" value="1"></div><div class="field"><label>Maximum uses</label><input name="max_uses" type="number" min="1" value="1"></div><div class="field"><label>Expires at (optional)</label><input name="expires_at" type="datetime-local"></div></div><button class="button">Create voucher</button></form></section><section class="panel"><table><thead><tr><th>Code</th><th>Label</th><th>Duration</th><th>Uses</th><th>Expires</th><th>Status</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="6" class="empty">No vouchers created.</td></tr>') . '</tbody></table></section>');
}

if ($page === 'devices') {
    $rows = '';
    foreach ($app->db->query('SELECT d.*,COUNT(s.id) sessions FROM devices d LEFT JOIN sessions s ON s.device_id=d.id GROUP BY d.id ORDER BY d.last_seen_at DESC') as $device) {
        $rows .= '<tr><td class="code">' . e($device['mac']) . '</td><td>' . e($device['last_ip'] ?: '—') . '</td><td>' . e($device['sessions']) . '</td><td>' . e($device['first_seen_at']) . '</td><td>' . e($device['last_seen_at']) . '</td></tr>';
    }
    admin_render('Devices', '<div class="heading"><div><h1>Devices</h1><p class="muted">Devices observed by the captive portal.</p></div></div><section class="panel"><table><thead><tr><th>MAC address</th><th>Last IP</th><th>Sessions</th><th>First seen</th><th>Last seen</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="5" class="empty">No devices observed.</td></tr>') . '</tbody></table></section>');
}

if ($page === 'sessions') {
    $rows = '';
    foreach ($app->db->query('SELECT s.*,d.mac,r.name router_name FROM sessions s LEFT JOIN devices d ON d.id=s.device_id LEFT JOIN routers r ON r.id=s.router_id ORDER BY s.updated_at DESC LIMIT 250') as $session) {
        $rows .= '<tr><td>' . e($session['username'] ?: '—') . '</td><td class="code">' . e($session['mac'] ?: '—') . '</td><td>' . e($session['router_name'] ?: '—') . '</td><td><span class="badge ' . ($session['status'] === 'active' ? '' : 'off') . '">' . e($session['status']) . '</span></td><td>' . e(duration_nice((int)$session['uptime_seconds'])) . '</td><td>' . e(bytes_nice((int)$session['bytes_in'] + (int)$session['bytes_out'])) . '</td><td>' . e($session['updated_at']) . '</td></tr>';
    }
    admin_render('Sessions', '<div class="heading"><div><h1>Sessions</h1><p class="muted">Authentication and accounting history.</p></div></div><section class="panel"><table><thead><tr><th>User</th><th>Device</th><th>Router</th><th>Status</th><th>Uptime</th><th>Transfer</th><th>Updated</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="7" class="empty">No sessions recorded.</td></tr>') . '</tbody></table></section>');
}

if ($page === 'administrators') {
    $message = '';
    if ($method === 'POST') {
        require_csrf();
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            $message = '<div class="alert">Use a valid name and email plus a password of at least 12 characters.</div>';
        } else {
            try {
                $users->create([
                    'name' => $name,
                    'email' => $email,
                    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                ]);
                $message = '<div class="alert ok">Administrator created.</div>';
            } catch (Throwable $exception) {
                $message = '<div class="alert">That email may already be registered.</div>';
            }
        }
    }

    $rows = '';
    foreach ($users->all(250) as $admin) {
        $rows .= '<tr><td>' . e($admin->name ?: '—') . '</td><td>' . e($admin->email ?: '—') . '</td><td class="code">#' . e($admin->id) . '</td></tr>';
    }

    admin_render('Administrators', '<div class="heading"><div><h1>Administrators</h1><p class="muted">Managed through Tihloh Prefab Users and authenticated by Prefab Auth.</p></div></div>' . $message . '<section class="panel"><h2>Add administrator</h2><form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="form-grid"><div class="field"><label>Name</label><input name="name" required></div><div class="field"><label>Email</label><input name="email" type="email" required></div><div class="field"><label>Temporary password</label><input name="password" type="password" minlength="12" required></div></div><button class="button">Create administrator</button></form></section><section class="panel"><table><thead><tr><th>Name</th><th>Email</th><th>ID</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="3" class="empty">No administrators found.</td></tr>') . '</tbody></table></section>');
}

http_response_code(404);
admin_render('Not found', '<section class="panel"><h1>Admin page not found</h1><p class="muted">Choose a section from the navigation above.</p></section>');
