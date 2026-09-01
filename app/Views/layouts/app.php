<?php
/** @var string $title */
/** @var string $name */
/** @var string $content */
/** @var bool $dashboard */
/** @var array<string,bool> $access */
/** @var string $cssVersion */

$manage = '';
if ($dashboard) {
    if ($access['routers'] ?? false) $manage .= '<a class="nav-link" href="/admin/routers">Routers</a>';
    if ($access['vouchers'] ?? false) $manage .= '<a class="nav-link" href="/admin/vouchers">Vouchers</a>';
    if ($access['devices'] ?? false) $manage .= '<a class="nav-link" href="/admin/devices">Devices</a>';
    if ($access['sessions'] ?? false) $manage .= '<a class="nav-link" href="/admin/sessions">Sessions</a>';
    if ($access['sales'] ?? false) $manage .= '<a class="nav-link" href="/admin/sales">Sales</a>';
    if ($access['logs'] ?? false) $manage .= '<a class="nav-link" href="/admin/logs">Logs</a>';
}
?>
<!doctype html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="theme-color" content="#07111f">
  <title><?= e($title) ?> · <?= $name ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-sRIl4kxILFvY47J16cr9ZwB07vP4J8+LH7qKQnuqkuIAvNWLzeN8tE5YBujZqJLB" crossorigin="anonymous">
  <link rel="stylesheet" href="/assets/app.css?v=<?= e($cssVersion) ?>">
</head>
<body>
<?php if ($dashboard): ?>
  <nav class="navbar navbar-expand-lg bg-body-tertiary border-bottom sticky-top">
    <div class="container-xl">
      <a class="navbar-brand fw-semibold" href="/dashboard"><?= $name ?></a>
      <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#pixiepoint-nav" aria-controls="pixiepoint-nav" aria-expanded="false" aria-label="Toggle navigation">
        <span class="navbar-toggler-icon"></span>
      </button>
      <div class="collapse navbar-collapse" id="pixiepoint-nav">
        <div class="navbar-nav ms-auto align-items-lg-center">
          <a class="nav-link" href="/dashboard">Dashboard</a>
          <?= $manage ?>
          <a class="nav-link" href="/logout">Log out</a>
        </div>
      </div>
    </div>
  </nav>
<?php endif; ?>

<main class="<?= $dashboard ? 'container-xl py-4' : 'portal container-fluid' ?>">
  <?= $content ?>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.8/dist/js/bootstrap.bundle.min.js" integrity="sha384-FKyoEForCGlyvwx9Hj09JcYn3nv7wiPVlz7YYwJrWVcXK/BmnVDxM+D2scQbITxI" crossorigin="anonymous"></script>
</body>
</html>
