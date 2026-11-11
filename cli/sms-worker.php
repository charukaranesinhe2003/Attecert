<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap/autoload.php';
require base_path('bootstrap/app.php');
$service = app(App\Services\SmsService::class);
if (!$service->ready()) { fwrite(STDERR, "SMSlenz is not configured. No SMS messages were sent.\n"); exit(1); }
$options = getopt('', ['limit:']);
$results = $service->processQueue((int) ($options['limit'] ?? 20));
echo 'SMS worker: ' . json_encode(array_count_values(array_column($results, 'status'))) . "\n";
