<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$autoload = $root . '/vendor/autoload.php';

if (!is_file($autoload)) {
    http_response_code(503);
    exit('PixiePoint dependencies are not installed. Run composer install.');
}

require $autoload;

PixiePoint\App\Application::run($root);
