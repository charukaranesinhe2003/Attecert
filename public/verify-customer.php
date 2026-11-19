<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$controller = app(\App\Controllers\CustomerVerificationController::class);
$request = \App\Core\Request::capture();

if ($request->method() === 'POST') {
    if (str_contains($request->path(), '/send') || isset($_POST['channel'])) {
        $response = $controller->send($request);
    } else {
        $response = $controller->verify($request);
    }
} else {
    $response = $controller->show();
}

$response->send();
