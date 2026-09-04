<?php
/** @var string $title */
/** @var string $name */
/** @var string $content */
/** @var bool $dashboard */
/** @var array<string,bool> $access */
/** @var string $cssVersion */
$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$active = static fn (string $href): string => $path === $href || ($href !== '/dashboard' && str_starts_with($path, $href . '/')) ? ' active' : '';
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07111f">
    <title><?= e($title) ?> · <?= $name ?></title>
    <style>html,body{min-height:100%;background:#07111f}body{margin:0;background:radial-gradient(circle at 10% 0,#302265 0,transparent 31rem),#07111f}</style>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
    <link rel="stylesheet" href="/assets/app.css?v=<?= e($cssVersion) ?>">
    <link rel="stylesheet" href="/assets/admin.css?v=<?= e($cssVersion) ?>">
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
                <a class="d-none d-lg-flex align-items-center gap-2 text-decoration-none text-body fw-bold fs-5 mb-4 px-2" href="/dashboard"><span class="logo">P</span><span><?= $name ?></span></a>
                <nav class="nav nav-pills flex-column gap-1">
                    <div class="nav-group-label">Overview</div>
                    <a class="nav-link<?= $active('/dashboard') ?>" href="/dashboard">Dashboard</a>

                    <?php if (($access['routers'] ?? false) || ($access['vendos'] ?? false) || ($access['vouchers'] ?? false)): ?>
                        <div class="nav-group-label mt-3">Wi-Fi</div>
                        <?php if ($access['routers'] ?? false): ?><a class="nav-link<?= $active('/admin/routers') ?>" href="/admin/routers">Routers</a><?php endif; ?>
                        <?php if ($access['vendos'] ?? false): ?><a class="nav-link<?= $active('/admin/vendos') ?>" href="/admin/vendos">Vendos</a><?php endif; ?>
                        <?php if ($access['vouchers'] ?? false): ?><a class="nav-link<?= $active('/admin/vouchers') ?>" href="/admin/vouchers">Vouchers</a><?php endif; ?>
                    <?php endif; ?>

                    <?php if (($access['users'] ?? false) || ($access['groups'] ?? false) || ($access['devices'] ?? false)): ?>
                        <div class="nav-group-label mt-3">Access</div>
                        <?php if ($access['users'] ?? false): ?><a class="nav-link<?= $active('/admin/users') ?>" href="/admin/users">Users</a><?php endif; ?>
                        <?php if ($access['groups'] ?? false): ?><a class="nav-link<?= $active('/admin/groups') ?>" href="/admin/groups">Groups</a><?php endif; ?>
                        <?php if ($access['devices'] ?? false): ?><a class="nav-link<?= $active('/admin/devices') ?>" href="/admin/devices">Devices</a><?php endif; ?>
                    <?php endif; ?>

                    <?php if (($access['sessions'] ?? false) || ($access['sales'] ?? false) || ($access['logs'] ?? false)): ?>
                        <div class="nav-group-label mt-3">Activity</div>
                        <?php if ($access['sessions'] ?? false): ?><a class="nav-link<?= $active('/admin/sessions') ?>" href="/admin/sessions">Sessions</a><?php endif; ?>
                        <?php if ($access['sales'] ?? false): ?><a class="nav-link<?= $active('/admin/sales') ?>" href="/admin/sales">Sales</a><?php endif; ?>
                        <?php if ($access['logs'] ?? false): ?><a class="nav-link<?= $active('/admin/logs') ?>" href="/admin/logs">Logs</a><?php endif; ?>
                    <?php endif; ?>
                </nav>
                <nav class="nav nav-pills flex-column gap-1 mt-auto pt-4 border-top">
                    <a class="nav-link<?= $active('/profile') ?>" href="/profile">Profile</a>
                    <a class="nav-link" href="/logout">Log out</a>
                </nav>
            </div>
        </aside>
        <main class="container-fluid px-3 px-md-4 py-4 pixie-content"><?= $content ?></main>
    </div>
<?php else: ?>
    <main class="portal container-fluid"><?= $content ?></main>
<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
