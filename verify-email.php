<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$request = \App\Core\Request::capture();
$token = (string) ($request->query('token') ?? '');

if ($token === '') {
    header('Location: login.php');
    exit;
}

$controller = app(\App\Controllers\AuthController::class);
$response = $controller->verify($request, $token);
$response->send();
