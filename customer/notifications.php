<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_auth('customer');

$controller = app(\App\Controllers\Customer\NotificationController::class);
$request = \App\Core\Request::capture();

if ($request->method() === 'POST') {
    $id = (int) ($request->query('id') ?? $request->input('id') ?? 0);
    $response = $id > 0 ? $controller->read($request, $id) : $controller->readAll($request);
} else {
    $response = $controller->index();
}

$response->send();
