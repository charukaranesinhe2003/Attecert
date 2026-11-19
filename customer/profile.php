<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_auth('customer');

$controller = app(\App\Controllers\Customer\ProfileController::class);
$request = \App\Core\Request::capture();

if ($request->method() === 'POST') {
    $response = $request->input('action') === 'password' || isset($_POST['password'])
        ? $controller->password($request)
        : $controller->update($request);
} else {
    $response = $controller->edit();
}

$response->send();
