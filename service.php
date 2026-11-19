<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$request = \App\Core\Request::capture();
$slug = (string) ($request->query('slug') ?? '');

if ($slug === '') {
    header('Location: services.php');
    exit;
}

$controller = app(\App\Controllers\PageController::class);
try {
    $response = $controller->service($request, $slug);
    $response->send();
} catch (\Throwable $e) {
    header('Location: services.php');
    exit;
}
