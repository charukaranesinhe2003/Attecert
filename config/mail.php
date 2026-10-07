<?php

declare(strict_types=1);

return [
    'host' => (string) env('MAIL_HOST', ''),
    'port' => (int) env('MAIL_PORT', '587'),
    'username' => (string) env('MAIL_USERNAME', ''),
    'password' => (string) env('MAIL_PASSWORD', ''),
    'encryption' => (string) env('MAIL_ENCRYPTION', 'tls'),
    'from_address' => (string) env('MAIL_FROM_ADDRESS', 'hello@attecert.lk'),
    'from_name' => (string) env('MAIL_FROM_NAME', 'AtteCert'),
];
