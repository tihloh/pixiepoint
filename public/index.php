<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(503);
    exit('PixiePoint dependencies are not installed. Run composer install.');
}
require $autoload;
require $root . '/src/App.php';

$app = new App($root);
$sessionPath = $root . '/data/sessions';
if (!is_dir($sessionPath)) {
    mkdir($sessionPath, 0775, true);
}
session_save_path($sessionPath);
session_name($app->config['session_name'] ?? 'pixiepoint_session');
session_set_cookie_params([
    'httponly' => true,
    'secure' => (bool)($app->config['cookie_secure'] ?? true) && (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
    'samesite' => 'Lax',
]);
session_start();

$prefabAdmin = PixiePoint\PrefabAdmin::boot($app->db);
$adminUsers = $prefabAdmin['users'];
$adminAuth = $prefabAdmin['auth'];

$path = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/') ?: '/';
$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');

function page(string $title, string $content, bool $admin = false): never
{
    global $app;
    $name = e($app->config['app_name'] ?? 'PixiePoint Wi-Fi');
    $nav = $admin ? '<nav><div class="wrap"><strong>' . $name . '</strong><div class="navlinks"><a href="/admin">Dashboard</a><a href="/admin/routers">Routers</a><a href="/admin/vouchers">Vouchers</a><a href="/admin/devices">Devices</a><a href="/admin/sessions">Sessions</a><a href="/admin/logout">Log out</a></div></div></nav>' : '';
    $class = $admin ? '<main class="wrap main">' : '<main class="portal">';
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#07111f"><title>' . e($title) . ' · ' . $name . '</title><link rel="stylesheet" href="/assets/app.css"></head><body>' . $nav . $class . $content . '</main></body></html>';
    exit;
}

function portal_card(string $body): string
{
    return '<section class="card"><div class="brand"><div class="logo">P</div><div><strong>PixiePoint Wi-Fi</strong><div class="muted">Secure guest access</div></div></div>' . $body . '</section>';
}

function admin_count(): int
{
    global $app;
    return (int)$app->db->query('SELECT COUNT(*) FROM admins')->fetchColumn();
}

function find_router(string $identity): array|false
{
    global $app;
    $stmt = $app->db->prepare('SELECT * FROM routers WHERE identity = ? AND enabled = 1');
    $stmt->execute([$identity]);
    return $stmt->fetch();
}

function router_login_url_is_safe(array $context, array $router): bool
{
    $url = parse_url((string)($context['login_url'] ?? ''));
    if (!$url || !in_array(strtolower((string)($url['scheme'] ?? '')), ['http', 'https'], true)) return false;
    $actualHost = strtolower(rtrim((string)($url['host'] ?? ''), '.'));
    $configured = trim((string)($router['public_host'] ?? ''));
    if ($configured === '') return false;
    $configuredUrl = str_contains($configured, '://') ? $configured : 'https://' . $configured;
    $expectedHost = strtolower(rtrim((string)(parse_url($configuredUrl, PHP_URL_HOST) ?: ''), '.'));
    $actualScheme = strtolower((string)$url['scheme']);
    $expectedScheme = strtolower((string)(parse_url($configuredUrl, PHP_URL_SCHEME) ?: 'https'));
    $actualPort = (int)($url['port'] ?? ($actualScheme === 'https' ? 443 : 80));
    $expectedPort = (int)(parse_url($configuredUrl, PHP_URL_PORT) ?: ($expectedScheme === 'https' ? 443 : 80));
    return $actualHost !== '' && $expectedHost !== '' && hash_equals($expectedHost, $actualHost) && $actualPort === $expectedPort;
}

if ($path === '/hotspot/health') {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    try {
        $app->db->query('SELECT 1')->fetchColumn();
        echo json_encode(['ready' => true, 'service' => 'pixiepoint', 'time' => gmdate(DATE_ATOM)]);
    } catch (Throwable $exception) {
        http_response_code(503);
        echo json_encode(['ready' => false]);
    }
    exit;
}

if ($path === '/' && $method === 'GET') {
    page('Portal', portal_card('<h1>Wi-Fi portal</h1><p class="muted">Connect to the guest Wi-Fi network to begin a session.</p>'));
}

if ($path === '/' && $method === 'POST') {
    $context = [
        'mac' => client_mac((string)($_POST['mac'] ?? '')),
        'ip' => substr((string)($_POST['ip'] ?? ''), 0, 45),
        'username' => substr((string)($_POST['username'] ?? ''), 0, 128),
        'router_identity' => substr((string)($_POST['router_identity'] ?? ''), 0, 128),
        'interface' => substr((string)($_POST['interface'] ?? ''), 0, 128),
        'server_address' => substr((string)($_POST['server_address'] ?? ''), 0, 255),
        'login_url' => substr((string)($_POST['login_url'] ?? ''), 0, 1000),
        'original_url' => substr((string)($_POST['original_url'] ?? ''), 0, 1000),
        'chap_id' => (string)($_POST['chap_id'] ?? ''),
        'chap_challenge' => (string)($_POST['chap_challenge'] ?? ''),
    ];
    $_SESSION['hotspot'] = $context;

    if ($context['mac'] !== '') {
        $stmt = $app->db->prepare('INSERT INTO devices(mac,last_ip,user_agent,last_seen_at) VALUES(?,?,?,?) ON DUPLICATE KEY UPDATE last_ip=VALUES(last_ip),user_agent=VALUES(user_agent),last_seen_at=VALUES(last_seen_at)');
        $stmt->execute([$context['mac'], $context['ip'], substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500), now()]);
    }

    $router = find_router($context['router_identity']);
    $notice = $router ? '' : '<div class="alert">This hotspot router is not registered or is disabled. Ask the network administrator to add identity <span class="code">' . e($context['router_identity']) . '</span>.</div>';
    $form = $router ? '<form method="post" action="/hotspot/authenticate"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="field"><label for="voucher">Access code</label><input id="voucher" name="voucher" autocomplete="one-time-code" autocapitalize="characters" required autofocus></div><button class="button full" type="submit">Connect this device</button></form>' : '';
    page('Sign in', portal_card('<h1>Connect to Wi-Fi</h1><p class="muted">Enter your access code to start this device’s session.</p>' . $notice . '<div class="context"><div><small>Device</small>' . e($context['mac'] ?: 'Unknown') . '</div><div><small>Router</small>' . e($context['router_identity'] ?: 'Unknown') . '</div></div>' . $form));
}

if ($path === '/hotspot/authenticate' && $method === 'POST') {
    require_csrf();
    $context = $_SESSION['hotspot'] ?? null;
    if (!is_array($context) || empty($context['login_url'])) {
        page('Session expired', portal_card('<h1>Start again</h1><div class="alert">The hotspot context has expired. Reconnect to the Wi-Fi network.</div>'));
    }
    $router = find_router((string)$context['router_identity']);
    if (!$router) {
        http_response_code(403);
        page('Router unavailable', portal_card('<h1>Router unavailable</h1><div class="alert">This hotspot is not registered or has been disabled.</div>'));
    }
    if (!router_login_url_is_safe($context, $router)) {
        http_response_code(403);
        page('Router address mismatch', portal_card('<h1>Router address mismatch</h1><div class="alert">The router login address does not match the hostname registered by the administrator. No credential was sent.</div>'));
    }
    $code = strtoupper(trim((string)($_POST['voucher'] ?? '')));
    $stmt = $app->db->prepare("SELECT * FROM vouchers WHERE code=? AND enabled=1 AND uses<max_uses AND (expires_at IS NULL OR expires_at='' OR expires_at>?)");
    $stmt->execute([$code, now()]);
    $voucher = $stmt->fetch();
    if (!$voucher) {
        page('Invalid code', portal_card('<h1>Code not accepted</h1><div class="alert">That access code is invalid, expired, or has already been used.</div><a class="button full" href="javascript:history.back()">Try another code</a>'));
    }
    $deviceStmt = $app->db->prepare('SELECT id FROM devices WHERE mac=?');
    $deviceStmt->execute([$context['mac']]);
    $deviceId = $deviceStmt->fetchColumn() ?: null;

    $app->db->beginTransaction();
    $app->db->prepare('UPDATE vouchers SET uses=uses+1 WHERE id=?')->execute([$voucher['id']]);
    $app->db->prepare("INSERT INTO sessions(voucher_id,router_id,device_id,username,client_ip,status,started_at,updated_at) VALUES(?,?,?,?,?,'authorizing',?,?)")
        ->execute([$voucher['id'], $router['id'], $deviceId, $voucher['code'], $context['ip'], now(), now()]);
    $app->db->prepare('UPDATE routers SET last_seen_at=? WHERE id=?')->execute([now(), $router['id']]);
    $app->db->commit();

    $action = e((string)$context['login_url']);
    $destination = e((string)($context['original_url'] ?: '/hotspot/session'));
    $body = '<h1>Authorizing…</h1><p class="muted">Your access code was accepted. Connecting this device now.</p><form id="router-login" action="' . $action . '" method="post"><input type="hidden" name="username" value="' . e($voucher['code']) . '"><input type="hidden" name="password" value="' . e($voucher['password']) . '"><input type="hidden" name="dst" value="' . $destination . '"><input type="hidden" name="popup" value="true"></form><script>document.getElementById("router-login").submit()</script><noscript><button class="button full" type="submit" form="router-login">Continue</button></noscript>';
    page('Authorizing', portal_card($body));
}

if ($path === '/hotspot/session' && $method === 'POST') {
    $mac = client_mac((string)($_POST['mac'] ?? ''));
    $in = max(0, (int)($_POST['bytes_in'] ?? 0));
    $out = max(0, (int)($_POST['bytes_out'] ?? 0));
    $uptime = max(0, (int)($_POST['uptime'] ?? 0));
    page('Session', portal_card('<h1>You’re connected</h1><p class="muted">Live Wi-Fi session for ' . e($mac ?: 'this device') . '.</p><div class="context"><div><small>Connected</small>' . e(duration_nice($uptime)) . '</div><div><small>IP address</small>' . e($_POST['ip'] ?? '') . '</div><div><small>Downloaded</small>' . e(bytes_nice($out)) . '</div><div><small>Uploaded</small>' . e(bytes_nice($in)) . '</div></div><form method="post" action="' . e($_POST['logout_url'] ?? '#') . '"><button class="button full" type="submit">Disconnect</button></form>'));
}

if ($path === '/hotspot/disconnected' && $method === 'POST') {
    page('Disconnected', portal_card('<h1>You’re offline</h1><p class="muted">The Wi-Fi session has ended.</p><div class="context"><div><small>Session time</small>' . e(duration_nice((int)($_POST['uptime'] ?? 0))) . '</div><div><small>Total transfer</small>' . e(bytes_nice((int)($_POST['bytes_in'] ?? 0) + (int)($_POST['bytes_out'] ?? 0))) . '</div></div><a class="button full" href="' . e($_POST['login_url'] ?? '#') . '">Connect again</a>'));
}

if ($path === '/api/accounting' && $method === 'POST') {
    header('Content-Type: application/json');
    $provided = preg_replace('/^Bearer\s+/i', '', (string)($_SERVER['HTTP_AUTHORIZATION'] ?? ''));
    if (!hash_equals((string)$app->config['accounting_key'], $provided)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'unauthorized']);
        exit;
    }
    $payload = json_decode(file_get_contents('php://input'), true);
    if (!is_array($payload) || empty($payload['session_id'])) {
        http_response_code(422);
        echo json_encode(['ok' => false, 'error' => 'session_id required']);
        exit;
    }
    $status = in_array($payload['status'] ?? '', ['start', 'update', 'stop'], true) ? $payload['status'] : 'update';
    $mapped = $status === 'stop' ? 'stopped' : 'active';
    $sessionId = substr((string)$payload['session_id'], 0, 128);
    $username = substr((string)($payload['username'] ?? ''), 0, 128);
    $clientIp = substr((string)($payload['client_ip'] ?? ''), 0, 45);
    $mac = client_mac((string)($payload['mac'] ?? ''));
    $routerIdentity = substr((string)($payload['router_identity'] ?? ''), 0, 128);
    $deviceId = null;
    $routerId = null;

    $app->db->beginTransaction();
    if ($mac !== '') {
        $app->db->prepare('INSERT INTO devices(mac,last_ip,last_seen_at) VALUES(?,?,?) ON DUPLICATE KEY UPDATE last_ip=VALUES(last_ip),last_seen_at=VALUES(last_seen_at)')->execute([$mac,$clientIp,now()]);
        $lookup = $app->db->prepare('SELECT id FROM devices WHERE mac=?');
        $lookup->execute([$mac]);
        $deviceId = $lookup->fetchColumn() ?: null;
    }
    if ($routerIdentity !== '') {
        $lookup = $app->db->prepare('SELECT id FROM routers WHERE identity=?');
        $lookup->execute([$routerIdentity]);
        $routerId = $lookup->fetchColumn() ?: null;
        if ($routerId) $app->db->prepare('UPDATE routers SET last_seen_at=? WHERE id=?')->execute([now(),$routerId]);
    }

    $existing = $app->db->prepare('SELECT id FROM sessions WHERE radius_session_id=?');
    $existing->execute([$sessionId]);
    $recordId = $existing->fetchColumn();
    if (!$recordId && $status === 'start') {
        $candidate = $app->db->prepare("SELECT id FROM sessions WHERE username=? AND status='authorizing' ORDER BY id DESC LIMIT 1");
        $candidate->execute([$username]);
        $recordId = $candidate->fetchColumn();
    }
    if ($recordId) {
        $stmt = $app->db->prepare('UPDATE sessions SET radius_session_id=?,router_id=COALESCE(?,router_id),device_id=COALESCE(?,device_id),client_ip=?,status=?,started_at=COALESCE(started_at,?),updated_at=?,ended_at=?,uptime_seconds=?,bytes_in=?,bytes_out=?,terminate_cause=? WHERE id=?');
        $stmt->execute([$sessionId,$routerId,$deviceId,$clientIp,$mapped,$status==='start'?now():null,now(),$status==='stop'?now():null,max(0,(int)($payload['uptime']??0)),max(0,(int)($payload['bytes_in']??0)),max(0,(int)($payload['bytes_out']??0)),substr((string)($payload['terminate_cause']??''),0,128),$recordId]);
    } else {
        $stmt = $app->db->prepare('INSERT INTO sessions(radius_session_id,router_id,device_id,username,client_ip,status,started_at,updated_at,ended_at,uptime_seconds,bytes_in,bytes_out,terminate_cause) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $stmt->execute([$sessionId,$routerId,$deviceId,$username,$clientIp,$mapped,$status==='start'?now():null,now(),$status==='stop'?now():null,max(0,(int)($payload['uptime']??0)),max(0,(int)($payload['bytes_in']??0)),max(0,(int)($payload['bytes_out']??0)),substr((string)($payload['terminate_cause']??''),0,128)]);
    }
    $app->db->commit();
    echo json_encode(['ok' => true]);
    exit;
}

if ($path === '/setup') {
    if (admin_count() > 0) redirect('/admin/login');
    $error = '';
    if ($method === 'POST') {
        require_csrf();
        $name = trim((string)($_POST['name'] ?? ''));
        $email = strtolower(trim((string)($_POST['email'] ?? '')));
        $password = (string)($_POST['password'] ?? '');
        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            $error = '<div class="alert">Use a valid name and email plus a password of at least 12 characters.</div>';
        } else {
            $stmt = $app->db->prepare('INSERT INTO admins(name,email,password_hash) VALUES(?,?,?)');
            $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            redirect('/admin/login');
        }
    }
    page('Initial setup', portal_card('<h1>Create administrator</h1><p class="muted">Complete the one-time setup for this installation.</p>' . $error . '<form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="field"><label>Name</label><input name="name" required></div><div class="field"><label>Email</label><input name="email" type="email" required></div><div class="field"><label>Password</label><input name="password" type="password" minlength="12" required></div><button class="button full">Create administrator</button></form>'));
}

if ($path === '/admin/login') {
    if (admin_count() === 0) redirect('/setup');
    if ($adminAuth->check()) redirect('/admin');
    $error = '';
    if ($method === 'POST') {
        require_csrf();
        $result = $adminAuth->attempt(
            strtolower(trim((string)($_POST['email'] ?? ''))),
            (string)($_POST['password'] ?? ''),
            [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            ],
        );
        if ($result->success) {
            session_regenerate_id(true);
            redirect('/admin');
        }
        $error = '<div class="alert">The email or password is incorrect.</div>';
    }
    page('Admin login', portal_card('<h1>Management login</h1><p class="muted">Administrator authentication is powered by Tihloh Prefab Auth.</p>' . $error . '<form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="field"><label>Email</label><input name="email" type="email" autocomplete="username" required autofocus></div><div class="field"><label>Password</label><input name="password" type="password" autocomplete="current-password" required></div><button class="button full">Log in</button></form>'));
}

if ($path === '/admin/logout') {
    if ($adminAuth->check()) {
        $adminAuth->logout([
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);
    }
    session_regenerate_id(true);
    redirect('/admin/login');
}

if (str_starts_with($path, '/admin')) {
    admin_required();
}

if ($path === '/admin') {
    $metrics = [
        'Routers' => (int)$app->db->query('SELECT COUNT(*) FROM routers WHERE enabled=1')->fetchColumn(),
        'Active sessions' => (int)$app->db->query("SELECT COUNT(*) FROM sessions WHERE status='active'")->fetchColumn(),
        'Known devices' => (int)$app->db->query('SELECT COUNT(*) FROM devices')->fetchColumn(),
        'Available vouchers' => (int)$app->db->query('SELECT COUNT(*) FROM vouchers WHERE enabled=1 AND uses<max_uses')->fetchColumn(),
    ];
    $cards = '';
    foreach ($metrics as $label => $value) $cards .= '<div class="metric"><small>' . e($label) . '</small><strong>' . e($value) . '</strong></div>';
    $recent = $app->db->query('SELECT s.*,d.mac,r.name router_name FROM sessions s LEFT JOIN devices d ON d.id=s.device_id LEFT JOIN routers r ON r.id=s.router_id ORDER BY s.updated_at DESC LIMIT 8')->fetchAll();
    $rows = '';
    foreach ($recent as $session) $rows .= '<tr><td>' . e($session['username'] ?: '—') . '</td><td class="code">' . e($session['mac'] ?: '—') . '</td><td>' . e($session['router_name'] ?: '—') . '</td><td><span class="badge ' . ($session['status']==='active'?'':'off') . '">' . e($session['status']) . '</span></td><td>' . e($session['updated_at']) . '</td></tr>';
    $currentAdmin = $adminAuth->user();
    page('Dashboard', '<div class="heading"><div><h1>Network overview</h1><p class="muted">Welcome back, ' . e($currentAdmin?->name ?? 'Administrator') . '.</p></div></div><section class="grid">' . $cards . '</section><section class="panel"><h2>Recent sessions</h2><table><thead><tr><th>User</th><th>Device</th><th>Router</th><th>Status</th><th>Updated</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="5" class="empty">No sessions recorded yet.</td></tr>') . '</tbody></table></section>', true);
}

if ($path === '/admin/routers') {
    $message = '';
    if ($method === 'POST') {
        require_csrf();
        $name = trim((string)($_POST['name'] ?? ''));
        $identity = trim((string)($_POST['identity'] ?? ''));
        if ($name !== '' && $identity !== '') {
            try {
                $stmt = $app->db->prepare('INSERT INTO routers(name,identity,public_host,location,api_key) VALUES(?,?,?,?,?)');
                $stmt->execute([$name,$identity,trim((string)($_POST['public_host']??'')),trim((string)($_POST['location']??'')),bin2hex(random_bytes(24))]);
                $message = '<div class="alert ok">Router registered.</div>';
            } catch (PDOException $exception) { $message = '<div class="alert">That RouterOS identity is already registered.</div>'; }
        }
    }
    $rows = '';
    foreach ($app->db->query('SELECT * FROM routers ORDER BY created_at DESC') as $router) $rows .= '<tr><td>' . e($router['name']) . '<div class="muted">' . e($router['location']) . '</div></td><td class="code">' . e($router['identity']) . '</td><td>' . e($router['public_host'] ?: '—') . '</td><td><span class="badge ' . ($router['enabled']?'':'off') . '">' . ($router['enabled']?'Enabled':'Disabled') . '</span></td><td>' . e($router['last_seen_at'] ?: 'Never') . '</td></tr>';
    page('Routers', '<div class="heading"><div><h1>Routers</h1><p class="muted">Register each MikroTik using its exact RouterOS identity.</p></div></div>' . $message . '<section class="panel"><h2>Add router</h2><form method="post"><input type="hidden" name="_csrf" value="' . e(csrf_token()) . '"><div class="form-grid"><div class="field"><label>Display name</label><input name="name" required></div><div class="field"><label>RouterOS identity</label><input name="identity" required></div><div class="field"><label>Public hostname / VPN IP</label><input name="public_host"></div><div class="field"><label>Location</label><input name="location"></div></div><button class="button">Register router</button></form></section><section class="panel"><table><thead><tr><th>Router</th><th>Identity</th><th>Address</th><th>Status</th><th>Last seen</th></tr></thead><tbody>' . ($rows ?: '<tr><td colspan="5" class="empty">No routers registered.</td></tr>') . '</tbody></table></section>', true);
}

if ($path === '/admin/vouchers') {
    $message = '';
    if ($method === 'POST') {
        require_csrf();
        $code = strtoupper(trim((string)($_POST['code'] ?? '')) ?: substr(strtoupper(bin2hex(random_bytes(5))),0,10));
        $password = bin2hex(random_bytes(8));
        try {
            $stmt=$app->db->prepare('INSERT INTO vouchers(code,password,label,duration_minutes,data_limit_mb,max_devices,max_uses,expires_at) VALUES(?,?,?,?,?,?,?,?)');
            $stmt->execute([$code,$password,trim((string)($_POST['label']??'')),max(1,(int)($_POST['duration_minutes']??60)),($_POST['data_limit_mb']??'')===''?null:max(1,(int)$_POST['data_limit_mb']),max(1,(int)($_POST['max_devices']??1)),max(1,(int)($_POST['max_uses']??1)),trim((string)($_POST['expires_at']??''))?:null]);
            $message='<div class="alert ok">Voucher <span class="code">'.e($code).'</span> created.</div>';
        } catch(PDOException $exception){$message='<div class="alert">That voucher code already exists.</div>';}
    }
    $rows='';
    foreach($app->db->query('SELECT * FROM vouchers ORDER BY created_at DESC LIMIT 100') as $v)$rows.='<tr><td class="code">'.e($v['code']).'</td><td>'.e($v['label']?:'—').'</td><td>'.e($v['duration_minutes']).' min</td><td>'.e($v['uses'].' / '.$v['max_uses']).'</td><td>'.e($v['expires_at']?:'Never').'</td><td><span class="badge '.($v['enabled']?'':'off').'">'.($v['enabled']?'Enabled':'Disabled').'</span></td></tr>';
    page('Vouchers','<div class="heading"><div><h1>Access vouchers</h1><p class="muted">Issue time- and usage-limited Wi-Fi credentials.</p></div></div>'.$message.'<section class="panel"><h2>Create voucher</h2><form method="post"><input type="hidden" name="_csrf" value="'.e(csrf_token()).'"><div class="form-grid"><div class="field"><label>Code (blank for automatic)</label><input name="code"></div><div class="field"><label>Label</label><input name="label"></div><div class="field"><label>Duration in minutes</label><input name="duration_minutes" type="number" min="1" value="60"></div><div class="field"><label>Data limit in MB (optional)</label><input name="data_limit_mb" type="number" min="1"></div><div class="field"><label>Maximum devices</label><input name="max_devices" type="number" min="1" value="1"></div><div class="field"><label>Maximum uses</label><input name="max_uses" type="number" min="1" value="1"></div><div class="field"><label>Expires at (optional)</label><input name="expires_at" type="datetime-local"></div></div><button class="button">Create voucher</button></form></section><section class="panel"><table><thead><tr><th>Code</th><th>Label</th><th>Duration</th><th>Uses</th><th>Expires</th><th>Status</th></tr></thead><tbody>'.($rows?:'<tr><td colspan="6" class="empty">No vouchers created.</td></tr>').'</tbody></table></section>',true);
}

if ($path === '/admin/devices') {
    $rows='';foreach($app->db->query('SELECT d.*,COUNT(s.id) sessions FROM devices d LEFT JOIN sessions s ON s.device_id=d.id GROUP BY d.id ORDER BY d.last_seen_at DESC') as $d)$rows.='<tr><td class="code">'.e($d['mac']).'</td><td>'.e($d['last_ip']?:'—').'</td><td>'.e($d['sessions']).'</td><td>'.e($d['first_seen_at']).'</td><td>'.e($d['last_seen_at']).'</td></tr>';
    page('Devices','<div class="heading"><div><h1>Devices</h1><p class="muted">Devices observed by the captive portal.</p></div></div><section class="panel"><table><thead><tr><th>MAC address</th><th>Last IP</th><th>Sessions</th><th>First seen</th><th>Last seen</th></tr></thead><tbody>'.($rows?:'<tr><td colspan="5" class="empty">No devices observed.</td></tr>').'</tbody></table></section>',true);
}

if ($path === '/admin/sessions') {
    $rows='';foreach($app->db->query('SELECT s.*,d.mac,r.name router_name FROM sessions s LEFT JOIN devices d ON d.id=s.device_id LEFT JOIN routers r ON r.id=s.router_id ORDER BY s.updated_at DESC LIMIT 250') as $s)$rows.='<tr><td>'.e($s['username']?:'—').'</td><td class="code">'.e($s['mac']?:'—').'</td><td>'.e($s['router_name']?:'—').'</td><td><span class="badge '.($s['status']==='active'?'':'off').'">'.e($s['status']).'</span></td><td>'.e(duration_nice((int)$s['uptime_seconds'])).'</td><td>'.e(bytes_nice((int)$s['bytes_in']+(int)$s['bytes_out'])).'</td><td>'.e($s['updated_at']).'</td></tr>';
    page('Sessions','<div class="heading"><div><h1>Sessions</h1><p class="muted">Authentication and accounting history.</p></div></div><section class="panel"><table><thead><tr><th>User</th><th>Device</th><th>Router</th><th>Status</th><th>Uptime</th><th>Transfer</th><th>Updated</th></tr></thead><tbody>'.($rows?:'<tr><td colspan="7" class="empty">No sessions recorded.</td></tr>').'</tbody></table></section>',true);
}

http_response_code(404);
page('Not found', portal_card('<h1>Page not found</h1><p class="muted">The requested page does not exist.</p>'));
