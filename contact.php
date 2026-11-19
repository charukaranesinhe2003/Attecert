<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$controller = app(\App\Controllers\PageController::class);
$request = \App\Core\Request::capture();

if ($request->method() === 'POST') {
    $response = $controller->sendContact($request);
} else {
    $response = $controller->contact();
}

$response->send();
