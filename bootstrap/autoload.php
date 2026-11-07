<?php

declare(strict_types=1);

/**
 * AtteCert Pure-PHP Autoloader
 * Eliminates Composer requirement for cPanel, XAMPP, and native PHP environments.
 */

// Register PSR-4 autoloader for App\ namespace
spl_autoload_register(static function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $relative = substr($class, 4);
        $file = dirname(__DIR__) . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }

    if (str_starts_with($class, 'Database\\Seeders\\')) {
        $relative = substr($class, 17);
        $file = dirname(__DIR__) . '/database/seeders/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require_once $file;
            return;
        }
    }
});

// Optionally load Composer autoload if it exists (for dev/local environments)
$composerAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($composerAutoload)) {
    require_once $composerAutoload;
}

// Load helper functions if not already loaded by Composer
if (!function_exists('env')) {
    $helpers = dirname(__DIR__) . '/app/Helpers/functions.php';
    if (is_file($helpers)) {
        require_once $helpers;
    }
}
