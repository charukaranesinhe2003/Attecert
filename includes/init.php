<?php

declare(strict_types=1);

/**
 * AtteCert Pure-PHP Initialization Bootstrap
 * Sets up environment, database, container, and session for flat PHP scripts.
 */

require_once dirname(__DIR__) . '/bootstrap/autoload.php';

use App\Core\Config;
use App\Core\Container;
use App\Core\Database;
use App\Core\Router;
use App\Services\AuthService;
use App\Services\SettingsService;
use App\Services\TranslationService;

// Load environment configuration
load_env(base_path('.env'));
Config::load(base_path('config'));
date_default_timezone_set((string) config('app.timezone', 'Asia/Colombo'));

// Safe session startup
if (session_status() === PHP_SESSION_NONE) {
    $secure = str_starts_with((string) config('app.url'), 'https://');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_name('attecert_session');
    session_start();
}

// Ensure container and router exist
if (empty($GLOBALS['app_container'])) {
    $container = new Container();
    $GLOBALS['app_container'] = $container;
    $router = new Router();
    $routesFile = base_path('routes/web.php');
    if (is_file($routesFile)) {
        $routes = require $routesFile;
        if (is_callable($routes)) {
            $routes($router);
        }
    }
    $container->instance(Router::class, $router);
}

// Flat PHP helper functions
if (!function_exists('db')) {
    function db(): Database
    {
        return app(Database::class);
    }
}

if (!function_exists('auth')) {
    function auth(): AuthService
    {
        return app(AuthService::class);
    }
}

if (!function_exists('current_user')) {
    function current_user(): ?array
    {
        return auth()->user();
    }
}

if (!function_exists('require_auth')) {
    function require_auth(?string $role = null): array
    {
        $user = current_user();
        if ($user === null) {
            $loginUrl = base_url_path() . '/login.php';
            header('Location: ' . $loginUrl);
            exit;
        }

        if ($role === 'staff' && !auth()->isStaff()) {
            http_response_code(403);
            echo "Access denied: Staff area.";
            exit;
        }

        if ($role === 'customer' && !auth()->isCustomer()) {
            http_response_code(403);
            echo "Access denied: Customer area.";
            exit;
        }

        if (auth()->isCustomer() && auth()->needsVerification()) {
            $verifyUrl = base_url_path() . '/verify-customer';
            header('Location: ' . $verifyUrl);
            exit;
        }

        return $user;
    }
}

if (!function_exists('require_perm')) {
    function require_perm(string $permission): void
    {
        require_auth('staff');
        if (!auth()->can($permission)) {
            http_response_code(403);
            echo "Access denied: You do not have permission for '" . htmlspecialchars($permission) . "'.";
            exit;
        }
    }
}
