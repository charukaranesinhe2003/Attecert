<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$controller = app(\App\Controllers\AuthController::class);
$request = \App\Core\Request::capture();

if ($request->method() === 'POST') {
    $response = $controller->register($request);
} else {
    $response = $controller->registerForm();
}

$response->send();
