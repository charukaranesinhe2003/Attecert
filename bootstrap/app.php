<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Container;
use App\Core\Kernel;
use App\Core\Router;

load_env(base_path('.env'));
Config::load(base_path('config'));
date_default_timezone_set((string) config('app.timezone', 'Asia/Colombo'));

if (PHP_SAPI !== 'cli') {
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

$container = new Container();
$GLOBALS['app_container'] = $container;
$router = new Router();
$routes = require base_path('routes/web.php');
$routes($router);
$container->instance(Router::class, $router);

return new Kernel($container, $router);
