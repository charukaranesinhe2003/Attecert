<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$controller = app(\App\Controllers\TrackController::class);
$request = \App\Core\Request::capture();

if ($request->method() === 'POST') {
    $response = $controller->lookup($request);
} else {
    $response = $controller->show($request);
}

$response->send();
