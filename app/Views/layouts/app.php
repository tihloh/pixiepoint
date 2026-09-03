<?php
/** @var string $title */
/** @var string $name */
/** @var string $content */
/** @var bool $dashboard */
/** @var array<string,bool> $access */
/** @var string $cssVersion */
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#07111f">
    <title><?= e($title) ?> · <?= $name ?></title>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB"
        crossorigin="anonymous"
    >
    <link rel="stylesheet" href="/assets/app.css?v=<?= e($cssVersion) ?>">
</head>
<body>
    <?php if ($dashboard): ?>
        <nav class="navbar navbar-expand-lg border-bottom sticky-top shadow-sm">
            <div class="container-xl py-1">
                <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="/dashboard">
                    <span class="logo">P</span>
                    <span><?= $name ?></span>
                </a>

                <button
                    class="navbar-toggler"
                    type="button"
                    data-bs-toggle="collapse"
                    data-bs-target="#pixiepoint-nav"
                    aria-controls="pixiepoint-nav"
                    aria-expanded="false"
                    aria-label="Toggle navigation"
                >
                    <span class="navbar-toggler-icon"></span>
                </button>

                <div class="collapse navbar-collapse" id="pixiepoint-nav">
                    <div class="navbar-nav ms-auto align-items-lg-center gap-lg-1 py-2 py-lg-0">
                        <a class="nav-link" href="/dashboard">Dashboard</a>

                        <?php if ($access['users'] ?? false): ?>
                            <a class="nav-link" href="/admin/users">Users</a>
                        <?php endif; ?>

                        <?php if ($access['groups'] ?? false): ?>
                            <a class="nav-link" href="/admin/groups">Groups</a>
                        <?php endif; ?>

                        <?php if ($access['routers'] ?? false): ?>
                            <a class="nav-link" href="/admin/routers">Routers</a>
                        <?php endif; ?>

                        <?php if ($access['vendos'] ?? false): ?>
                            <a class="nav-link" href="/admin/vendos">Vendos</a>
                        <?php endif; ?>

                        <?php if ($access['vouchers'] ?? false): ?>
                            <a class="nav-link" href="/admin/vouchers">Vouchers</a>
                        <?php endif; ?>

                        <?php if ($access['devices'] ?? false): ?>
                            <a class="nav-link" href="/admin/devices">Devices</a>
                        <?php endif; ?>

                        <?php if ($access['sessions'] ?? false): ?>
                            <a class="nav-link" href="/admin/sessions">Sessions</a>
                        <?php endif; ?>

                        <?php if ($access['sales'] ?? false): ?>
                            <a class="nav-link" href="/admin/sales">Sales</a>
                        <?php endif; ?>

                        <?php if ($access['logs'] ?? false): ?>
                            <a class="nav-link" href="/admin/logs">Logs</a>
                        <?php endif; ?>

                        <div class="vr d-none d-lg-block mx-2"></div>
                        <a class="nav-link" href="/profile">Profile</a>
                        <a class="nav-link" href="/logout">Log out</a>
                    </div>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <main class="<?= $dashboard ? 'container-xl py-4 py-lg-5' : 'portal container-fluid' ?>">
        <?= $content ?>
    </main>

    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI"
        crossorigin="anonymous"
    ></script>
</body>
</html>
