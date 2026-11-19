<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$controller = app(\App\Controllers\AuthController::class);
$response = $controller->logout();
$response->send();
