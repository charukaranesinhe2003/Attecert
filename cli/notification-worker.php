<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/bootstrap/autoload.php';
require base_path('bootstrap/app.php');
$options = getopt('', ['limit:']);
$limit = max(1, min(100, (int) ($options['limit'] ?? 20)));
$mail = app(App\Services\MailService::class);
$sms = app(App\Services\SmsService::class);
$result = [
    'email' => $mail->smtpReady() ? $mail->processQueue($limit) : ['status' => 'not_configured'],
    'sms' => $sms->ready() ? $sms->processQueue($limit) : ['status' => 'not_configured'],
];
echo json_encode($result, JSON_UNESCAPED_SLASHES) . "\n";
