<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/App.php';

try {
    new App(dirname(__DIR__));
    fwrite(STDOUT, "PixiePoint database migrations completed.\n");
    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, "Migration failed: {$exception->getMessage()}\n");
    exit(1);
}
