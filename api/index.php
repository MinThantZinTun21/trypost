<?php

declare(strict_types=1);

/*
 * Vercel entry point (vercel-php runtime). A function can only write to /tmp,
 * so Laravel's storage and bootstrap caches are redirected there before the
 * normal front controller boots. The runtime prints PHP errors into the
 * response by default; they belong in the function logs, not the page.
 */
ini_set('display_errors', '0');
ini_set('log_errors', '1');

$writablePaths = [
    'LARAVEL_STORAGE_PATH' => '/tmp/storage',
    'APP_CONFIG_CACHE' => '/tmp/bootstrap/config.php',
    'APP_EVENTS_CACHE' => '/tmp/bootstrap/events.php',
    'APP_PACKAGES_CACHE' => '/tmp/bootstrap/packages.php',
    'APP_ROUTES_CACHE' => '/tmp/bootstrap/routes.php',
    'APP_SERVICES_CACHE' => '/tmp/bootstrap/services.php',
    'VIEW_COMPILED_PATH' => '/tmp/storage/framework/views',
];

foreach ($writablePaths as $key => $value) {
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

foreach (['/tmp/bootstrap', '/tmp/storage/app/private', '/tmp/storage/app/public', '/tmp/storage/framework/views', '/tmp/storage/framework/cache/data', '/tmp/storage/logs'] as $directory) {
    if (! is_dir($directory)) {
        mkdir($directory, 0755, true);
    }
}

require __DIR__.'/../public/index.php';
