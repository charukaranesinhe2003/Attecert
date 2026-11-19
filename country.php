<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$request = \App\Core\Request::capture();
$slug = (string) ($request->query('slug') ?? '');

if ($slug === '') {
    header('Location: countries.php');
    exit;
}

$controller = app(\App\Controllers\PageController::class);
try {
    $response = $controller->country($request, $slug);
    $response->send();
} catch (\Throwable $e) {
    header('Location: countries.php');
    exit;
}
