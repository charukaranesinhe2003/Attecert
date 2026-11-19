<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_auth('customer');

$controller = app(\App\Controllers\Customer\DocumentController::class);
$request = \App\Core\Request::capture();
$id = (int) ($request->query('download') ?? $request->query('id') ?? 0);

if ($id > 0) {
    $response = $controller->download($request, $id);
} else {
    $response = $controller->index();
}

$response->send();
