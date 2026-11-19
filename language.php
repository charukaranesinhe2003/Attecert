<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';

$request = \App\Core\Request::capture();
$lang = (string) ($request->query('lang') ?? $request->query('locale') ?? 'en');

$controller = app(\App\Controllers\HomeController::class);
$response = $controller->language($request, $lang);
$response->send();
