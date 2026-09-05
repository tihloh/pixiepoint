<?php
/** @var string $title */
/** @var string $name */
/** @var string $content */
/** @var bool $dashboard */
/** @var array<string,bool> $access */
/** @var string $cssVersion */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$active = static fn (string $href): string => $path === $href || ($href !== '/dashboard' && str_starts_with($path, $href . '/')) ? ' active' : '';
$sidebarUser = $dashboard ? (is_array($GLOBALS['pixiepoint_sidebar_user'] ?? null) ? $GLOBALS['pixiepoint_sidebar_user'] : []) : [];
$sidebarRouters = $dashboard ? (is_array($GLOBALS['pixiepoint_sidebar_routers'] ?? null) ? $GLOBALS['pixiepoint_sidebar_routers'] : []) : [];
$selectedRouter = $dashboard && is_array($GLOBALS['pixiepoint_selected_router'] ?? null) ? $GLOBALS['pixiepoint_selected_router'] : null;
$isOverview = $path === '/dashboard';
$sidebarName = trim((string) ($sidebarUser['name'] ?? '')) ?: 'User';
$sidebarRole = match ((string) ($sidebarUser['platform_role'] ?? 'member')) {
    'platform_owner' => 'Platform owner',
    'pisowifi_owner' => 'PisoWiFi owner',
    default => 'Member',
};
$sidebarAvatar = trim((string) ($sidebarUser['avatar_url'] ?? ''));
$sidebarPoints = isset($sidebarUser['points']) ? max(0, (int) $sidebarUser['points']) : null;
$sidebarInitial = strtoupper(substr($sidebarName, 0, 1));
$sidebarReturn = preg_match('#^/(?:admin|dashboard)(?:/|$)#', $path) ? $path : '/dashboard';
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07111f">
    <title><?= e($title) ?> · <?= $name ?></title>
    <style>html,body{min-height:100%;background:#07111f}body{margin:0;background:radial-gradient(circle at 10% 0,#302265 0,transparent 31rem),#07111f}</style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/app.css?v=<?= e($cssVersion) ?>">
    <link rel="stylesheet" href="/assets/admin.css?v=<?= e($cssVersion) ?>">
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js" crossorigin="anonymous"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" crossorigin="anonymous"></script>
</head>
<body>
<?php if ($dashboard): ?>
    <nav class="navbar border-bottom d-lg-none sticky-top">
        <div class="container-fluid px-3">
            <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/dashboard"><span class="logo">P</span><span><?= $name ?></span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="offcanvas" data-bs-target="#pixiepoint-sidebar" aria-controls="pixiepoint-sidebar" aria-label="Open navigation"><span class="navbar-toggler-icon"></span></button>
        </div>
    </nav>
    <div class="d-lg-flex min-vh-100">
        <aside class="offcanvas-lg offcanvas-start border-end pixie-sidebar" tabindex="-1" id="pixiepoint-sidebar" aria-labelledby="pixiepoint-sidebar-label">
            <div class="offcanvas-header border-bottom d-lg-none"><h5 class="offcanvas-title" id="pixiepoint-sidebar-label"><?= $name ?></h5><button type="button" class="btn-close" data-bs-dismiss="offcanvas" data-bs-target="#pixiepoint-sidebar" aria-label="Close"></button></div>
            <div class="offcanvas-body d-flex flex-column p-3">
                <a class="d-none d-lg-flex flex-column align-items-center text-decoration-none text-body fw-bold mb-4 px-2 pixie-brand" href="/dashboard"><span class="logo pixie-brand-logo">P</span><span class="mt-2"><?= $name ?></span></a>

                <nav class="nav nav-pills flex-column gap-1">
                    <div class="nav-group-label">Overview</div>
                    <a class="nav-link<?= $active('/dashboard') ?>" href="/dashboard">Dashboard</a>

                    <?php if ($isOverview): ?>
                        <?php if ($access['routers'] ?? false): ?>
                            <div class="nav-group-label mt-3">Network</div>
                            <a class="nav-link<?= $active('/admin/routers') ?>" href="/admin/routers">Routers</a>
                        <?php endif; ?>
                    <?php elseif ($access['routers'] ?? false): ?>
                        <div class="nav-group-label mt-3">Network</div>

                        <form method="get" action="/admin/routers" class="px-2 pt-1 pb-2" id="sidebar-router-form">
                            <input type="hidden" name="return" value="<?= e($sidebarReturn) ?>">
                            <label class="visually-hidden" for="sidebar-router-select">Select router</label>
                            <select class="form-select form-select-sm" id="sidebar-router-select" name="select">
                                <?php foreach ($sidebarRouters as $router): ?>
                                    <option value="<?= e($router['id']) ?>" <?= $selectedRouter && (int) $selectedRouter['id'] === (int) $router['id'] ? 'selected' : '' ?>>
                                        <?= e($router['name']) ?> · <?= e($router['identity']) ?>
                                    </option>
                                <?php endforeach; ?>
                                <option value="__add__">＋ Add new router</option>
                            </select>
                            <?php if ($selectedRouter): ?>
                                <div class="small text-body-secondary mt-1 text-truncate" title="<?= e($selectedRouter['identity']) ?>">
                                    <?= e($selectedRouter['identity']) ?>
                                </div>
                                <a class="nav-link mt-2 px-2 py-1<?= $active('/admin/routers/' . (int) $selectedRouter['id']) ?>" href="/admin/routers/<?= e($selectedRouter['id']) ?>">Router dashboard</a>
                            <?php endif; ?>
                        </form>

                        <?php if ($selectedRouter): ?>
                            <div class="nav-group-label mt-2">Router management</div>
                            <?php if ($access['vendos'] ?? false): ?><a class="nav-link<?= $active('/admin/vendos') ?>" href="/admin/vendos">Vendos</a><?php endif; ?>
                            <?php if ($access['vouchers'] ?? false): ?><a class="nav-link<?= $active('/admin/vouchers') ?>" href="/admin/vouchers">Vouchers</a><?php endif; ?>
                            <?php if ($access['devices'] ?? false): ?><a class="nav-link<?= $active('/admin/devices') ?>" href="/admin/devices">Devices</a><?php endif; ?>
                            <?php if ($access['sessions'] ?? false): ?><a class="nav-link<?= $active('/admin/sessions') ?>" href="/admin/sessions">Sessions</a><?php endif; ?>
                            <?php if ($access['sales'] ?? false): ?><a class="nav-link<?= $active('/admin/sales') ?>" href="/admin/sales">Sales</a><?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>

                    <?php if (($access['users'] ?? false) || ($access['groups'] ?? false) || ($access['permissions'] ?? false)): ?>
                        <div class="nav-group-label mt-3">Administration</div>
                        <?php if ($access['users'] ?? false): ?><a class="nav-link<?= $active('/admin/users') ?>" href="/admin/users">Users</a><?php endif; ?>
                        <?php if ($access['groups'] ?? false): ?><a class="nav-link<?= $active('/admin/groups') ?>" href="/admin/groups">Groups</a><?php endif; ?>
                        <?php if ($access['permissions'] ?? false): ?><a class="nav-link<?= $active('/admin/permissions') ?>" href="/admin/permissions">Permissions</a><?php endif; ?>
                    <?php endif; ?>

                    <?php if ($access['logs'] ?? false): ?>
                        <div class="nav-group-label mt-3">System</div>
                        <a class="nav-link<?= $active('/admin/logs') ?>" href="/admin/logs">Logs</a>
                    <?php endif; ?>
                </nav>

                <div class="card border-0 rounded-3 mt-auto pixie-user-card">
                    <div class="card-body p-3">
                        <div class="d-flex align-items-center gap-3">
                            <?php if ($sidebarAvatar !== ''): ?>
                                <img src="<?= e($sidebarAvatar) ?>" alt="" class="pixie-user-avatar">
                            <?php else: ?>
                                <span class="pixie-user-avatar pixie-user-avatar-fallback" aria-hidden="true"><?= e($sidebarInitial) ?></span>
                            <?php endif; ?>
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-semibold text-truncate"><?= e($sidebarName) ?></div>
                                <div class="text-body-secondary small text-truncate"><?= e($sidebarRole) ?></div>
                                <?php if ($sidebarPoints !== null): ?>
                                    <div class="small mt-1"><span aria-hidden="true">★</span> <?= e(number_format($sidebarPoints)) ?> points</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="d-flex gap-2 mt-3">
                            <a class="btn btn-outline-light btn-sm flex-fill<?= $active('/profile') ?>" href="/profile">Profile</a>
                            <a class="btn btn-outline-danger btn-sm flex-fill" href="/logout">Log out</a>
                        </div>
                    </div>
                </div>
            </div>
        </aside>
        <main class="container-fluid px-3 px-md-4 py-4 pixie-content"><?= $content ?></main>
    </div>
<?php else: ?>
    <main class="portal container-fluid"><?= $content ?></main>
<?php endif; ?>
<script>
    $(function () {
        $('#sidebar-router-select').on('change', function () {
            if (this.value === '__add__') {
                const modal = document.getElementById('register-router-modal');

                if (modal && window.bootstrap?.Modal) {
                    window.bootstrap.Modal.getOrCreateInstance(modal).show();
                    return;
                }

                window.location.href = '/dashboard?register=1';
            } else {
                $('#sidebar-router-form').trigger('submit');
            }
        });
    });
</script>
</body>
</html>
