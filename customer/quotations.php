<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_auth('customer');

$controller = app(\App\Controllers\Customer\QuotationController::class);
$request = \App\Core\Request::capture();
$id = (int) ($request->query('id') ?? $request->input('id') ?? 0);

if ($id > 0) {
    $action = (string) ($request->query('action') ?? $request->input('action') ?? '');
    if ($action === 'accept' && $request->method() === 'POST') {
        $response = $controller->accept($request, $id);
    } elseif ($action === 'reject' && $request->method() === 'POST') {
        $response = $controller->reject($request, $id);
    } elseif ($action === 'pdf' || $request->query('pdf')) {
        header('Location: ../print-quotation.php?id=' . $id);
        exit;
    } else {
        $response = $controller->show($id);
    }
} else {
    $response = $controller->index();
}

$response->send();
