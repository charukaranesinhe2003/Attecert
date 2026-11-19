<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_auth('staff');

$controller = app(\App\Controllers\Admin\DashboardController::class);
$response = $controller->index(\App\Core\Request::capture());
$response->send();
