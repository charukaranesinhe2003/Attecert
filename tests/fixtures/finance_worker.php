<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/vendor/autoload.php';

use App\Core\Config;
use App\Core\Container;
use App\Services\PaymentService;
use App\Services\InvoiceService;

// Receive fixture configuration through stdin, never process arguments or a credentials file.
$configuration = json_decode((string) fgets(STDIN), true, 512, JSON_THROW_ON_ERROR);
if (!preg_match('/^attecert_test_[a-f0-9]{16}$/', (string) ($configuration['database']['database'] ?? ''))) {
    throw new RuntimeException('Finance worker requires a disposable test database.');
}
$configuration['mail']['host'] = '';
(new ReflectionProperty(Config::class, 'items'))->setValue(null, $configuration);
date_default_timezone_set((string) config('app.timezone', 'Asia/Colombo'));
$GLOBALS['app_container'] = new Container();
$_SESSION = ['locale' => 'en'];
$_SERVER['REMOTE_ADDR'] = '127.0.0.1';
echo "READY\n";
flush();
if (trim((string) fgets(STDIN)) !== 'GO') {
    exit(1);
}
try {
    if (($configuration['operation'] ?? 'payment') === 'invoice') {
        $invoiceId = app(InvoiceService::class)->createFromQuotation((int) $configuration['quotation_id'], (int) $configuration['staff_id']);
        echo json_encode(['ok' => true, 'invoice_id' => $invoiceId]);
    } else {
        $paymentId = app(PaymentService::class)->record((int) $configuration['invoice_id'], ['amount' => '60.00', 'method' => 'bank_transfer', 'paid_at' => date('Y-m-d')], (int) $configuration['staff_id']);
        echo json_encode(['ok' => true, 'payment_id' => $paymentId]);
    }
} catch (Throwable $exception) {
    echo json_encode(['ok' => false, 'error' => $exception->getMessage()]);
}
