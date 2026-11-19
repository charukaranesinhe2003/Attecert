<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$controller = app(\App\Controllers\QuoteController::class);
$request = \App\Core\Request::capture();

if ($request->method() === 'POST') {
    $response = $controller->submit($request);
} else {
    $response = $controller->form();
}

$response->send();
