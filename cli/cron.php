<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';
require dirname(__DIR__) . '/bootstrap/app.php';

$invoices = app(App\Services\InvoiceService::class);
$updated = $invoices->refreshOverdue();
$expired = app(App\Core\Database::class)->run(
    "UPDATE quotations SET status = 'expired' WHERE status IN ('sent', 'viewed') AND valid_until IS NOT NULL AND valid_until < CURDATE()"
)->rowCount();
echo "Overdue invoices updated: {$updated}. Quotations expired: {$expired}.\n";
