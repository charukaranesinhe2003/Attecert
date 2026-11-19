<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/init.php';
require_auth('customer');

$controller = app(\App\Controllers\Customer\InvoiceController::class);
$request = \App\Core\Request::capture();
$id = (int) ($request->query('id') ?? 0);

if ($id > 0) {
    if ($request->query('pdf')) {
        header('Location: ../print-invoice.php?id=' . $id);
        exit;
    }
    $response = $controller->show($id);
} else {
    $response = $controller->index();
}

$response->send();
