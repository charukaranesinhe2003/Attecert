<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$controller = app(\App\Controllers\PageController::class);
$response = $controller->about(\App\Core\Request::capture());
$response->send();
