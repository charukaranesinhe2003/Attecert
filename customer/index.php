<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_auth('customer');

$controller = app(\App\Controllers\Customer\DashboardController::class);
$response = $controller->index();
$response->send();
