<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_auth('customer');

$controller = app(\App\Controllers\Customer\ProfileController::class);
$request = \App\Core\Request::capture();

if ($request->method() === 'POST') {
    $response = $controller->sendSupport($request);
} else {
    $response = $controller->support();
}

$response->send();
