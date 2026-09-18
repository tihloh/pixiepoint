<?php

declare(strict_types=1);

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
require $root . '/src/App.php';

try {
    $app = new App($root);
    (new Tihloh\VendoGateway\Database\Migrator($app->db))->migrate();
    fwrite(STDOUT, "PixiePoint and Vendo Gateway database migrations completed.\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Migration failed: {$exception->getMessage()}\n");
    exit(1);
}
