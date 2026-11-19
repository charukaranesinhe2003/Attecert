<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$controller = app(\App\Controllers\AuthController::class);
$request = \App\Core\Request::capture();

if ($request->method() === 'POST') {
    $response = $controller->forgot($request);
} else {
    $response = $controller->forgotForm();
}

$response->send();
